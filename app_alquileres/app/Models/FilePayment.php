<?php

namespace App\Models;

use App\Services\InvoicePaymentFileStorageService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FilePayment extends Model
{
    use HasUuids;

    protected $table = 'file_payment';

    protected $fillable = [
        'name_file',
        'type',
        'weigth',
        'bucket',
    ];

    protected $appends = [
        'url',
    ];

    public function invoiceItem()
    {
        return $this->hasOne(InvoiceItem::class, 'file_payment_id');
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->bucket) {
            return null;
        }

        return app(InvoicePaymentFileStorageService::class)->temporaryUrl($this->bucket);
    }
}
