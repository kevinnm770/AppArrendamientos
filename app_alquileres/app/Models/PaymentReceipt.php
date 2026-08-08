<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReceipt extends Model
{
    // Misma ventana de edición que hoy aplica Invoice::canEditOrDeleteReceipt().
    public const EDIT_WINDOW_HOURS = 24;

    protected $fillable = [
        'agreement_id',
        'invoice_id',
        'lessor_id',
        'roomer_id',
        'receipt_number',
        'date',
        'currency',
        'payment_methods',
        'payment_method_other_description',
        'reference_code',
        'notes',
        'total',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'payment_methods' => 'array',
        'total' => 'decimal:2',
    ];

    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Factura electrónica que este comprobante liquida, si el usuario la vinculó al
     * crearlo/editarlo (ver InvoiceController::store() y PaymentReceiptController).
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
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

    public function items()
    {
        return $this->hasMany(PaymentReceiptItem::class)->orderBy('position');
    }

    public function recalculateTotalFromItems(): void
    {
        $this->forceFill([
            'total' => round((float) $this->items()->sum('line_total'), 2),
        ])->save();
    }

    public function canEditOrDelete(): bool
    {
        if (!$this->created_at) {
            return false;
        }

        return now()->lt($this->created_at->copy()->addHours(self::EDIT_WINDOW_HOURS));
    }
}
