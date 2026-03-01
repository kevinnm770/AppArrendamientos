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
}
