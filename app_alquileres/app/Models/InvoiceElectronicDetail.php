<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceElectronicDetail extends Model
{
    protected $fillable = [
        'invoice_id',
        'activity_code',
        'economic_activity',
        'electronic_key',
        'consecutive_number',
        'document_type',
        'situation',
        'xml_name',
        'xml_signed',
        'hacienda_status',
        'sent_to_hacienda_at',
        'hacienda_response_at',
        'hacienda_response',
        'hacienda_track_id',
        'hacienda_message',
        'email_status',
        'sent_to_client_at',
        'last_sync_at',
    ];

    protected $casts = [
        'sent_to_hacienda_at' => 'datetime',
        'hacienda_response_at' => 'datetime',
        'sent_to_client_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
