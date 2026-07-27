<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'cabys_code',
        'commercial_code_type',
        'commercial_code',
        'description',
        'quantity',
        'unit_of_measure',
        'commercial_unit_of_measure',
        'item_type',
        'unit_price',
        'discount_percent',
        'discount_total',
        'tax_code',
        'tax_rate',
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

    public static function computeFromInput(array $input): array
    {
        $quantity = (float) ($input['quantity'] ?? 1);
        $unitPrice = (float) ($input['unit_price'] ?? 0);
        $discountPercent = (float) ($input['discount_percent'] ?? 0);
        $taxRate = (float) ($input['tax_rate'] ?? 0);

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
            'unit_of_measure' => $input['unit_of_measure'] ?? 'Unid',
            'commercial_unit_of_measure' => $input['commercial_unit_of_measure'] ?? null,
            'item_type' => $input['item_type'] ?? 'service',
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'discount_total' => $discountTotal,
            'tax_code' => $input['tax_code'] ?? ($taxRate > 0 ? '01' : null),
            'tax_rate' => $taxRate,
            'tax_total' => $taxTotal,
            'subtotal' => $subtotal,
            'line_total' => $lineTotal,
        ];
    }
}
