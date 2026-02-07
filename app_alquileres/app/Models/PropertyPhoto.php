<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyPhoto extends Model
{
    protected $table = 'propertyphotos';

    protected $fillable = [
        'property_id',
        'path',
        'position',
        'caption',
        'taken_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
