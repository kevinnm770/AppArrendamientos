<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    public const CONCEPT_OPTIONS = [
        'rent' => 'Alquiler',
        'service' => 'Servicio',
        'deposit' => 'Depósito',
        'discount' => 'Descuento',
        'late_fee' => 'Morosidad',
        'repair' => 'Reparación',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'invoice_id',
        'cabys_code',
        'commercial_code_type',
        'commercial_code',
        'description',
        'quantity',
        'concept',
        'unit_of_measure',
        'transaction_type',
        'commercial_unit_of_measure',
        'item_type',
        'unit_price',
        'discount_percent',
        'discount_total',
        'tax_code',
        'tax_rate',
        'tax_condition',
        'tax_total',
        'subtotal',
        'line_total',
        'position',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public static function conceptOptions(): array
    {
        return self::CONCEPT_OPTIONS;
    }

    public static function computeFromInput(array $input): array
    {
        $quantity = (float) ($input['quantity'] ?? 1);
        $concept = $input['concept'] ?? null;
        $unitPrice = (float) ($input['unit_price'] ?? 0);
        // Un "Descuento" resta del total del comprobante: se guarda como monto negativo
        // para que Invoice::recalculateTotalsFromItems() (que solo suma line_total) lo reste solo.
        if ($concept === 'discount') {
            $unitPrice = -abs($unitPrice);
        }
        $discountPercent = (float) ($input['discount_percent'] ?? 0);
        $taxCondition = $input['tax_condition'] ?? 'gravado';
        // Exento y No Sujeto son categorías legales distintas en Costa Rica, pero ninguna
        // lleva tarifa de IVA — se fuerza a 0 sin importar lo que traiga el formulario.
        $taxRate = $taxCondition === 'gravado' ? (float) ($input['tax_rate'] ?? 0) : 0.0;

        $gross = round($quantity * $unitPrice, 2);
        $discountTotal = round($gross * ($discountPercent / 100), 2);
        $subtotal = round($gross - $discountTotal, 2);
        $taxTotal = round($subtotal * ($taxRate / 100), 2);
        $lineTotal = round($subtotal + $taxTotal, 2);

        return [
            'cabys_code' => $input['cabys_code'] ?? null,
            'commercial_code_type' => $input['commercial_code_type'] ?? null,
            'commercial_code' => $input['commercial_code'] ?? null,
            'description' => $input['description'],
            'quantity' => $quantity,
            'concept' => $concept,
            'unit_of_measure' => $input['unit_of_measure'] ?? 'Unid',
            'transaction_type' => $input['transaction_type'] ?? null,
            'commercial_unit_of_measure' => $input['commercial_unit_of_measure'] ?? null,
            'item_type' => $input['item_type'] ?? 'service',
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'discount_total' => $discountTotal,
            'tax_code' => $input['tax_code'] ?? ($taxRate > 0 ? '01' : null),
            'tax_rate' => $taxRate,
            'tax_condition' => $taxCondition,
            'tax_total' => $taxTotal,
            'subtotal' => $subtotal,
            'line_total' => $lineTotal,
        ];
    }
}
