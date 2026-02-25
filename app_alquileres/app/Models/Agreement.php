<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agreement extends Model
{
    protected $fillable = [
        'property_id',
        'lessor_id',
        'roomer_id',
        'service_type',
        'start_at',
        'end_at',
        'terms',
        'status',
        'canceled_by',
        'canceled_date',
        'tenant_confirmed_at',
        'locked_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'canceled_date' => 'datetime',
        'tenant_confirmed_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function lessor()
    {
        return $this->belongsTo(Lessor::class);
    }

    public function roomer()
    {
        return $this->belongsTo(Roomer::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function ademdums()
    {
        return $this->hasMany(Ademdum::class);
    }

    public function latestAdemdum()
    {
        return $this->hasOne(Ademdum::class)->latestOfMany('created_at');
    }

    public function AdemdumUpdatePeriod()
    {
        return $this->hasOne(Ademdum::class)
            ->where('status', 'accepted')
            ->whereNotNull('update_start_date_agreement')
            ->whereNotNull('update_end_date_agreement')
            ->where('update_start_date_agreement', '<=', now())
            ->where('update_end_date_agreement', '>=', now())
            ->latestOfMany('update_start_date_agreement');
    }
}
