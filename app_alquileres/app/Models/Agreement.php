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

    /*
        Agrega un método nuevo llamado "AdemdumUpdatePeriod()" en el modelo Agreement de modo
        que devuelva al ademdum que tiene status "accepted" y los campos "update_start_date_agreement"
        y "update_end_date_agreement" no nulos y vigentes (donde la fecha actual esté en dicho rango).
    */
}
