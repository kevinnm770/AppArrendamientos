<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agreement extends Model
{
    protected $fillable = [
        'id',
        'property_id',
        'lessor_id',
        'roomer_id',
        'service_type',
        'start_at',
        'end_at',
        'terms',
        'status',
        'tenant_confirmed_at',
        'locked_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'terms' => 'array',
    ];

    public function lessor()
    {
        return $this->hasOne(Lessor::class);
    }

    public function roomer()
    {
        return $this->hasOne(Roomer::class);
    }

    public function property()
    {
        return $this->hasOne(Property::class);
    }
}
