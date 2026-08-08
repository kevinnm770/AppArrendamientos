<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditBalanceMovement extends Model
{
    public const TYPE_OPTIONS = [
        'generated' => 'Generado',
        'applied' => 'Aplicado',
    ];

    public const SOURCE_OPTIONS = [
        'overpayment' => 'Sobrepago en comprobante de pago',
        'credit_note' => 'Nota de crédito electrónica',
        'manual' => 'Ajuste manual',
    ];

    // Conceptos contra los que realmente se puede aplicar saldo a favor — deben coincidir
    // exactamente con lo que TenantBalanceService::sumByConcept() sabe sumar. Es un
    // subconjunto de InvoiceItem::CONCEPT_OPTIONS: 'service' y 'discount' no son "saldos"
    // que se puedan saldar con crédito, así que se excluyen a propósito. Fuente única para
    // el formulario de comprobante de pago, el de factura electrónica, y la pantalla
    // dedicada de Aplicación de saldo a favor.
    public const APPLIABLE_CONCEPTS = [
        'rent' => 'Alquiler',
        'deposit' => 'Depósito',
        'late_fee_rent' => 'Morosidad alquiler',
        'late_fee_deposit' => 'Morosidad depósito',
        'repair' => 'Otros cargos a cobrar - Reparación',
        'other' => 'Otros cargos a cobrar - Otro',
    ];

    protected $fillable = [
        'agreement_id',
        'lessor_id',
        'roomer_id',
        'type',
        'amount',
        'currency',
        'source',
        'applied_to_concept',
        'payment_receipt_id',
        'invoice_id',
        'reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }

    public function lessor()
    {
        return $this->belongsTo(Lessor::class);
    }

    public function roomer()
    {
        return $this->belongsTo(Roomer::class);
    }

    public function paymentReceipt()
    {
        return $this->belongsTo(PaymentReceipt::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }

    public static function sourceOptions(): array
    {
        return self::SOURCE_OPTIONS;
    }

    public static function appliableConceptOptions(): array
    {
        return self::APPLIABLE_CONCEPTS;
    }
}
