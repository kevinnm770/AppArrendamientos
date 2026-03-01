<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public const STATUS_OPTIONS = [
        'draft' => 'Borrador',
        'sent' => 'Enviada',
        'confirmed' => 'Confirmada',
        'paid' => 'Pagada',
        'overdue' => 'Vencida',
        'void' => 'Anulada',
    ];

    public const SALE_CONDITION_OPTIONS = [
        'cash' => 'Contado',
        'credit' => 'Crédito',
        'consignment' => 'Consignación',
        'layaway' => 'Apartado',
        'service' => 'Cobro de servicio',
    ];

    public const PAYMENT_METHOD_OPTIONS = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        'check' => 'Cheque',
        'collection' => 'Recaudado por tercero',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'agreement_id',
        'lessor_id',
        'roomer_id',
        'invoice_number',
        'date',
        'issued_at',
        'due_date',
        'description',
        'currency',
        'exchange_rate',
        'subtotal',
        'tax_percent',
        'discount_percent',
        'discount_total',
        'tax_total',
        'late_fee_total',
        'total',
        'sale_condition',
        'payment_method',
        'reference_code',
        'notes',
        'status',
        'tenant_confirmed_at',
        'locked_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'issued_at' => 'datetime',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'late_fee_total' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'tenant_confirmed_at' => 'datetime',
        'locked_at' => 'datetime',
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function electronicDetail()
    {
        return $this->hasOne(InvoiceElectronicDetail::class);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->where('due_date', '>=', now()->subYears(5));
    }

    public function canBeSentToHacienda(): bool
    {
        if ($this->status !== 'draft') {
            return false;
        }

        if (!$this->electronicDetail) {
            return false;
        }

        return !empty($this->electronicDetail->electronic_key)
            && !empty($this->electronicDetail->consecutive_number);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function saleConditionOptions(): array
    {
        return self::SALE_CONDITION_OPTIONS;
    }

    public static function paymentMethodOptions(): array
    {
        return self::PAYMENT_METHOD_OPTIONS;
    }

    public static function generateElectronicKey(): string
    {
        return now()->format('dmy') . str_pad((string) random_int(0, 99999999999999999999999999999999999999), 38, '0', STR_PAD_LEFT);
    }

    public static function generateConsecutiveNumber(int $lessorId, int $invoiceId): string
    {
        return str_pad((string) $lessorId, 10, '0', STR_PAD_LEFT)
            . str_pad((string) $invoiceId, 10, '0', STR_PAD_LEFT);
    }
}
