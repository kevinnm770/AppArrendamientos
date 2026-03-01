<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
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

    // Accesos rápidos vía Agreement
    public function getLessorAttribute()
    {
        return $this->agreement->lessor;
    }

    public function getRoomerAttribute()
    {
        return $this->agreement->roomer;
    }

    public function getPropertyAttribute()
    {
        return $this->agreement->property;
    }

    // Scopes
    public function scopeSimple($query)
    {
        return $query->where('type', 'simple');
    }

    public function scopeElectronic($query)
    {
        return $query->where('type', 'electronic');
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

    // Métodos de utilidad
    public function isElectronic(): bool
    {
        return $this->type === 'electronic';
    }

    public function isSimple(): bool
    {
        return $this->type === 'simple';
    }

    public function total(): float
    {
        $tax = $this->subtotal * ($this->tax_percent / 100);
        $discount = $this->subtotal * ($this->discount_percent / 100);
        return $this->subtotal + $tax - $discount + $this->late_fee_total;
    }

    public function taxAmount(): float
    {
        return $this->subtotal * ($this->tax_percent / 100);
    }

    public function discountAmount(): float
    {
        return $this->subtotal * ($this->discount_percent / 100);
    }

    public function canBeSentToHacienda(): bool
    {
        if (!$this->isElectronic()) {
            return false;
        }

        if ($this->status !== 'draft') {
            return false;
        }

        if (!$this->electronicDetail) {
            return false;
        }

        return !empty($this->electronicDetail->emisor_nit)
            && !empty($this->electronicDetail->receptor_nit);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
