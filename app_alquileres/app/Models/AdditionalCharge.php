<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalCharge extends Model
{
    public const CONCEPT_OPTIONS = [
        'repair' => 'Reparación',
        'other' => 'Otro',
    ];

    public const STATUS_OPTIONS = [
        'pending' => 'Pendiente',
        'cancelled' => 'Cancelado',
    ];

    protected $fillable = [
        'agreement_id',
        'lessor_id',
        'roomer_id',
        'concept',
        'description',
        'amount',
        'currency',
        'charge_date',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'charge_date' => 'date',
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

    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public static function conceptOptions(): array
    {
        return self::CONCEPT_OPTIONS;
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }
}
