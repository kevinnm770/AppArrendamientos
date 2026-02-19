<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ademdum extends Model
{
    protected $fillable = [
        'agreement_id',
        'start_at',
        'end_at',
        'terms',
        'status',
        'tenant_confirmed_at',
        'locked_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'tenant_confirmed_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }
}
