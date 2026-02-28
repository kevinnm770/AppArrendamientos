<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignedDoc extends Model
{
    protected $table = 'signedDocs';

    protected $fillable = [
        'agreement_id',
        'ademdum_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'compressed_size_bytes',
    ];

    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }

    public function ademdum()
    {
        return $this->belongsTo(Ademdum::class);
    }
}
