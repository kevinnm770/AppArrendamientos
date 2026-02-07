<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'lessor_id',
        'name',
        'description',
        'location_text',
        'location_province',
        'location_canton',
        'location_district',
        'service_type',
        'rooms',
        'living_rooms',
        'kitchens',
        'bathrooms',
        'yards',
        'garages_capacity',
        'included_objects',
        'materials',
        'status',
    ];

    protected $casts = [
        'included_objects' => 'array',
        'materials' => 'array',
    ];

    public function lessor()
    {
        return $this->belongsTo(Lessor::class);
    }

    public function photos()
    {
        return $this->hasMany(PropertyPhoto::class);
    }
}
