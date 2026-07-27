<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabysCode extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'code';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'description',
        'tax_rate',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
    ];

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class, 'cabys_code', 'code');
    }
}
