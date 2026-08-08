<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lessor;
use App\Models\PaymentReceipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de comprobante de pago compartida entre PaymentReceiptController e
 * InvoiceController (este último la usa al crear el comprobante vinculado a una factura
 * electrónica marcada como "ya pagada" — ver InvoiceController::store()).
 */
class PaymentReceiptService
{
    public function __construct(
        private readonly TenantBalanceService $tenantBalanceService,
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * Consecutivo interno propio (REC-{año}-{6 dígitos}), independiente de los
     * consecutivos de Hacienda que usa ClaveGenerator para las facturas electrónicas.
     */
    public function nextReceiptNumber(Lessor $lessor): string
    {
        return DB::transaction(function () use ($lessor) {
            $prefix = 'REC-'.now()->year.'-';

            $last = PaymentReceipt::where('lessor_id', $lessor->id)
                ->where('receipt_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->max('receipt_number');

            $nextSequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            return $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Guarda en cada línea (según su concepto) el saldo pendiente de ese concepto
     * inmediatamente después de este comprobante. Las líneas que son solo el reflejo de
     * una aplicación de saldo a favor no representan dinero nuevo por sí mismas, pero el
     * saldo restante que muestran es igual de válido para el inquilino.
     */
    public function applyConceptBalances(PaymentReceipt $receipt, Agreement $agreement): void
    {
        $breakdown = $this->tenantBalanceService->breakdownFor($agreement, Carbon::parse($receipt->date));

        $balanceByConcept = [
            'rent' => $breakdown['rent']['balance'],
            'deposit' => $breakdown['deposit']['balance'],
            'late_fee_rent' => $breakdown['late_fee_rent']['balance'],
            'late_fee_deposit' => $breakdown['late_fee_deposit']['balance'],
        ];

        foreach ($receipt->items as $item) {
            $item->update(['balance_pending' => $balanceByConcept[$item->concept] ?? null]);
        }
    }

    /**
     * Notifica por email al inquilino cuando se crea, edita o elimina un comprobante de
     * pago. En creación/edición el correo trae el desglose completo de líneas —incluyendo
     * las de aplicación de saldo a favor, marcadas aparte— en eliminación solo los datos
     * básicos que se están perdiendo.
     */
    public function notifyReceiptEvent(PaymentReceipt $receipt, string $action): void
    {
        $roomerUser = $receipt->roomer?->user;

        if (!$roomerUser) {
            return;
        }

        $contractLabel = trim(($receipt->agreement->contract_number ?? '').' - '.($receipt->agreement->property->name ?? ''), ' -');
        $dateLabel = optional($receipt->date)->format('d/m/Y') ?? '-';

        $subject = match ($action) {
            'created' => "Nuevo comprobante de pago #{$receipt->receipt_number}",
            'updated' => "Comprobante de pago #{$receipt->receipt_number} actualizado",
            'deleted' => "Comprobante de pago #{$receipt->receipt_number} eliminado",
        };

        if ($action === 'deleted') {
            $body = "Se eliminó el comprobante de pago N° {$receipt->receipt_number} del {$dateLabel}, "
                ."por {$receipt->currency} ".number_format((float) $receipt->total, 2)
                .($contractLabel !== '' ? ", correspondiente al contrato {$contractLabel}." : '.')."\n"
                .'Si tienes dudas sobre este cambio, contacta a tu arrendador.';

            $this->notificationService->emailUsers([$roomerUser], $subject, $body);

            return;
        }

        $verb = $action === 'created' ? 'Se registró' : 'Se actualizó';

        $lines = [
            "{$verb} un comprobante de pago".($contractLabel !== '' ? " para tu contrato {$contractLabel}." : '.'),
            "Fecha: {$dateLabel}",
            "Número: {$receipt->receipt_number}",
            "Moneda: {$receipt->currency}",
            'Detalle:',
        ];

        foreach ($receipt->items as $item) {
            $conceptLabel = InvoiceItem::CONCEPT_OPTIONS[$item->concept] ?? 'Otro';
            if ($item->is_credit_application) {
                $conceptLabel .= ' - saldo a favor aplicado';
            } elseif ($item->is_return) {
                $conceptLabel .= ' - retorno al inquilino';
            }
            $lines[] = "- [{$conceptLabel}] {$item->description}: {$receipt->currency} ".number_format((float) $item->line_total, 2);
        }

        $lines[] = "Total: {$receipt->currency} ".number_format((float) $receipt->total, 2);

        $paymentMethodLabels = collect($receipt->payment_methods ?? [])
            ->map(fn ($method) => Invoice::PAYMENT_METHOD_OPTIONS[$method] ?? $method)
            ->implode(', ');

        if ($paymentMethodLabels !== '') {
            $lines[] = "Métodos de pago: {$paymentMethodLabels}";
        }

        if (!empty($receipt->notes)) {
            $lines[] = "Notas: {$receipt->notes}";
        }

        $this->notificationService->emailUsers([$roomerUser], $subject, implode("\n", $lines));
    }
}
