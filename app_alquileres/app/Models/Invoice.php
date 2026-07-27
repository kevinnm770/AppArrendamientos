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
        'check' => 'Cheque',
        'transfer' => 'Transferencia - depósito bancario',
        'collection' => 'Recaudado por terceros',
        'sinpe_movil' => 'SINPE Móvil',
        'digital_platform' => 'Plataforma digital',
        'other' => 'Otros',
    ];

    protected $fillable = [
        'agreement_id',
        'reference_invoice_id',
        'credit_note_reason_code',
        'credit_note_reason_text',
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
        'payment_methods',
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
        'payment_methods' => 'array',
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

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * Factura original que esta Nota de Crédito corrige/anula (solo aplica cuando el
     * documento electrónico es de tipo "03").
     */
    public function referenceInvoice()
    {
        return $this->belongsTo(Invoice::class, 'reference_invoice_id');
    }

    /**
     * Recalcula subtotal/descuento/impuesto/total agregados a partir de las líneas
     * ya persistidas, para que el comprobante XML (Fase 4) y esta tabla nunca diverjan.
     */
    public function recalculateTotalsFromItems(): void
    {
        $items = $this->items()->get();

        $subtotal = (float) $items->sum(fn (InvoiceItem $item) => (float) $item->subtotal + (float) $item->discount_total);
        $discountTotal = (float) $items->sum('discount_total');
        $taxTotal = (float) $items->sum('tax_total');
        // late_fee_total ya está representado como su propia línea dentro de $items
        // (ver InvoiceController::store); no se vuelve a sumar aquí para no duplicarlo.
        $total = (float) $items->sum('line_total');

        $this->forceFill([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'discount_percent' => $subtotal > 0 ? round($discountTotal / $subtotal * 100, 2) : 0,
            'tax_total' => round($taxTotal, 2),
            'tax_percent' => ($subtotal - $discountTotal) > 0 ? round($taxTotal / ($subtotal - $discountTotal) * 100, 2) : 0,
            'total' => round($total, 2),
        ])->save();
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

        return !empty($this->electronicDetail->hacienda_key)
            && !empty($this->electronicDetail->hacienda_consecutive);
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
}
