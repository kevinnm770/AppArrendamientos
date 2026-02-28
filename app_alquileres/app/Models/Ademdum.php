<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ademdum extends Model
{
    protected $fillable = [
        'agreement_id',
        'start_at',
        'end_at',
        'update_start_date_agreement',
        'update_end_date_agreement',
        'terms',
        'status',
        'tenant_confirmed_at',
        'locked_at',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'update_start_date_agreement' => 'datetime',
        'update_end_date_agreement' => 'datetime',
        'tenant_confirmed_at' => 'datetime',
        'locked_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }

    public function signedDoc()
    {
        return $this->hasOne(SignedDoc::class);
    }
}
