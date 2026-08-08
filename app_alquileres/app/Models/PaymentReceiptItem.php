<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReceiptItem extends Model
{
    protected $fillable = [
        'payment_receipt_id',
        'concept',
        'is_return',
        'is_credit_application',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'balance_pending',
        'file_payment_id',
        'position',
    ];

    protected $casts = [
        'is_return' => 'boolean',
        'is_credit_application' => 'boolean',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'balance_pending' => 'decimal:2',
    ];

    public function paymentReceipt()
    {
        return $this->belongsTo(PaymentReceipt::class);
    }

    public function filePayment()
    {
        return $this->belongsTo(FilePayment::class);
    }

    /**
     * Misma regla de signo que InvoiceItem::computeFromInput(), sin la parte de
     * descuento/impuesto porque el comprobante simple nunca los usó.
     */
    public static function computeFromInput(array $input): array
    {
        $quantity = (float) ($input['quantity'] ?? 1);
        $concept = $input['concept'] ?? null;
        $isReturn = (bool) ($input['is_return'] ?? false);
        $unitPrice = (float) ($input['unit_price'] ?? 0);

        if ($concept === 'discount' || $isReturn) {
            $unitPrice = -abs($unitPrice);
        }

        return [
            'concept' => $concept,
            'is_return' => $isReturn,
            // Debe quedar guardado tal cual venga: TenantBalanceService::sumByConcept()
            // excluye estas líneas de su lado (PaymentReceiptItem) porque el monto ya se
            // cuenta aparte vía el CreditBalanceMovement que las acompaña — si esto se
            // guardara siempre en false, ese monto se contaría dos veces.
            'is_credit_application' => (bool) ($input['is_credit_application'] ?? false),
            'description' => $input['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
        ];
    }
}
