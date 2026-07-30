<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\InvoiceItem;
use Carbon\Carbon;

/**
 * Calcula cuánto le debe el inquilino al arrendador a una fecha dada (alquiler, depósito
 * y morosidad de cada uno por separado), restando lo ya registrado en comprobantes previos
 * (simples o electrónicos, cualquiera que no esté anulado).
 *
 * El alquiler es recurrente: se recorre periodo por periodo desde el inicio del contrato,
 * resolviendo Agreement::effectiveTerms() en la fecha de inicio de CADA periodo (no en la
 * fecha del recibo), para que un adéndum con vigencia pasada siga rigiendo el monto y la
 * política de mora de los periodos que cubrió, aunque el cobro sea posterior. El depósito
 * es una obligación única, se resuelve con los términos vigentes a la fecha del recibo.
 */
class TenantBalanceService
{
    private const PERIOD_MONTHS = [
        'monthly' => 1,
        'bimonthly' => 2,
        'quarterly' => 3,
        'semiannual' => 6,
        'annual' => 12,
    ];

    // Tope de seguridad para no iterar indefinidamente ante datos inconsistentes.
    private const MAX_PERIODS = 1200;

    public function breakdownFor(Agreement $agreement, Carbon $asOf): array
    {
        $asOf = $asOf->copy()->endOfDay();

        $periods = $this->buildRentPeriods($agreement, $asOf);

        $rentPaidTotal = $this->sumByConcept($agreement, 'rent', $asOf);
        $depositPaidTotal = $this->sumByConcept($agreement, 'deposit', $asOf);
        $lateFeePaidTotal = $this->sumByConcept($agreement, 'late_fee', $asOf);

        $rentDue = 0.0;
        $rentLateFeeAccrued = 0.0;
        $remainingRentPaid = $rentPaidTotal;

        foreach ($periods as $period) {
            $rentDue += $period['amount'];

            $applied = min($remainingRentPaid, $period['amount']);
            $remainingRentPaid = round($remainingRentPaid - $applied, 2);
            $unpaid = round($period['amount'] - $applied, 2);

            if ($unpaid > 0 && $period['due_date']->lt($asOf)) {
                $rentLateFeeAccrued += $this->accrueLateFee(
                    $period['terms'],
                    $unpaid,
                    $period['amount'],
                    $period['due_date'],
                    $asOf,
                    ''
                );
            }
        }

        $rentBalance = round($rentDue - $rentPaidTotal, 2);

        $termsNow = $agreement->effectiveTerms($asOf);
        $depositDue = (float) ($termsNow['deposit'] ?? 0);
        $depositBalance = round($depositDue - $depositPaidTotal, 2);

        $depositLateFeeAccrued = 0.0;
        $depositDeadline = $termsNow['deadline_deposit'] ?? null;

        if ($depositBalance > 0 && $depositDeadline) {
            $depositDueDate = $depositDeadline instanceof Carbon
                ? $depositDeadline->copy()->endOfDay()
                : Carbon::parse($depositDeadline)->endOfDay();

            if ($depositDueDate->lt($asOf)) {
                $depositLateFeeAccrued = $this->accrueLateFee(
                    $termsNow,
                    $depositBalance,
                    $depositDue,
                    $depositDueDate,
                    $asOf,
                    '_deposit'
                );
            }
        }

        // Solo existe un concepto "Morosidad" en las líneas del comprobante (no distingue
        // si paga mora de alquiler o de depósito): lo cobrado se aplica primero a la mora
        // de alquiler pendiente y el remanente a la de depósito.
        $lateFeePaidToRent = min($lateFeePaidTotal, $rentLateFeeAccrued);
        $lateFeePaidToDeposit = min(round($lateFeePaidTotal - $lateFeePaidToRent, 2), $depositLateFeeAccrued);

        return [
            'currency' => $termsNow['currency'] ?? $agreement->currency,
            'rent' => [
                'due' => round($rentDue, 2),
                'paid' => round($rentPaidTotal, 2),
                'balance' => $rentBalance,
            ],
            'deposit' => [
                'due' => round($depositDue, 2),
                'paid' => round($depositPaidTotal, 2),
                'balance' => $depositBalance,
            ],
            'late_fee_rent' => [
                'accrued' => round($rentLateFeeAccrued, 2),
                'paid' => round($lateFeePaidToRent, 2),
                'balance' => round($rentLateFeeAccrued - $lateFeePaidToRent, 2),
            ],
            'late_fee_deposit' => [
                'accrued' => round($depositLateFeeAccrued, 2),
                'paid' => round($lateFeePaidToDeposit, 2),
                'balance' => round($depositLateFeeAccrued - $lateFeePaidToDeposit, 2),
            ],
        ];
    }

    /**
     * Un periodo por cada ciclo de pago desde el inicio del contrato hasta la fecha del
     * recibo (o el fin del contrato, lo que ocurra antes). La duración y el monto de cada
     * periodo se resuelven con los términos vigentes en SU propia fecha de inicio.
     */
    private function buildRentPeriods(Agreement $agreement, Carbon $asOf): array
    {
        $limit = $asOf->lessThan($agreement->end_at) ? $asOf : $agreement->end_at->copy()->endOfDay();

        $periods = [];
        $periodStart = $agreement->start_at->copy()->startOfDay();
        $count = 0;

        while ($periodStart->lte($limit) && $count < self::MAX_PERIODS) {
            $terms = $agreement->effectiveTerms($periodStart);
            $months = self::PERIOD_MONTHS[$terms['frequency_pay']] ?? 1;

            $periods[] = [
                'start' => $periodStart->copy(),
                'amount' => (float) $terms['amount'],
                'due_date' => $agreement->suggestedDueDate($periodStart)->endOfDay(),
                'terms' => $terms,
            ];

            $periodStart = $periodStart->copy()->addMonths($months);
            $count++;
        }

        return $periods;
    }

    /**
     * Monto de mora acumulado para un solo periodo/obligación vencida. Misma fórmula que
     * Agreement::suggestedLateFee(), generalizada con $suffix ('' para alquiler,
     * '_deposit' para depósito) para poder reutilizarla en ambos casos.
     */
    private function accrueLateFee(array $terms, float $unpaidAmount, float $originalAmount, Carbon $dueDate, Carbon $asOf, string $suffix): float
    {
        $typeSanction = $terms["type_sanction{$suffix}"] ?? 'none';

        if ($typeSanction === 'none' || $typeSanction === null) {
            return 0.0;
        }

        $daysLate = (int) $dueDate->diffInDays($asOf, false);

        if ($daysLate <= 0) {
            return 0.0;
        }

        $maxDaysUnlimited = (bool) ($terms["max_days_unlimited{$suffix}"] ?? false);
        $maxDays = $terms["max_days{$suffix}"] ?? null;

        if (!$maxDaysUnlimited && $maxDays) {
            $daysLate = min($daysLate, (int) $maxDays);
        }

        $periodDays = match ($terms["frequency_sanction{$suffix}"] ?? 'monthly') {
            'daily' => 1,
            'weekly' => 7,
            default => 30,
        };

        $periods = max(1, (int) floor($daysLate / $periodDays));
        $base = $terms["base{$suffix}"] ?? 'original_amount';
        $baseAmount = $base === 'balance' ? $unpaidAmount : $originalAmount;

        return match ($typeSanction) {
            'percent' => round($baseAmount * ((float) ($terms["surcharge_delay{$suffix}"] ?? 0) / 100) * $periods, 2),
            'amount_fix' => round((float) ($terms["amount_delay{$suffix}"] ?? 0) * $periods, 2),
            default => 0.0,
        };
    }

    /**
     * Suma lo ya cobrado por un concepto dado, en cualquier comprobante (simple o
     * electrónico) del contrato que no esté anulado y con fecha hasta $asOf inclusive.
     */
    private function sumByConcept(Agreement $agreement, string $concept, Carbon $asOf): float
    {
        return (float) InvoiceItem::where('concept', $concept)
            ->whereHas('invoice', function ($query) use ($agreement, $asOf) {
                $query->where('agreement_id', $agreement->id)
                    ->where('status', '!=', 'void')
                    ->whereDate('date', '<=', $asOf);
            })
            ->sum('line_total');
    }
}
