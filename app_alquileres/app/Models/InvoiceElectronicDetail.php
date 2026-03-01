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

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('hacienda_status', $status);
    }

    public function scopeAccepted($query)
    {
        return $query->where('hacienda_status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('hacienda_status', 'rejected');
    }

    public function scopePending($query)
    {
        return $query->where('hacienda_status', 'pending');
    }

    // Métodos de utilidad
    public function isAccepted(): bool
    {
        return $this->hacienda_status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->hacienda_status === 'rejected';
    }

    public function isSent(): bool
    {
        return !empty($this->sent_to_hacienda_at);
    }

    public function getHaciendaUrl(): string
    {
        // URL para consultar en Hacienda
        return "https://www.hacienda.go.cr/consultafactura?clave={$this->electronic_key}";
    }

    public function markAsSent(): void
    {
        $this->update([
            'hacienda_status' => 'pending',
            'sent_to_hacienda_at' => now(),
        ]);
    }

    public function markAsAccepted(): void
    {
        $this->update([
            'hacienda_status' => 'accepted',
            'hacienda_response_at' => now(),
        ]);

        // Actualizar factura padre
        $this->invoice->update([
            'status' => 'confirmed',
            'locked_at' => now(),
        ]);
    }

    public function markAsRejected(string $reason = null): void
    {
        $data = [
            'hacienda_status' => 'rejected',
            'hacienda_response_at' => now(),
        ];

        if ($reason) {
            $data['hacienda_message'] = $reason;
        }

        $this->update($data);

        // Actualizar factura padre
        $this->invoice->update([
            'status' => 'draft',
            'locked_at' => null,
        ]);
    }
}
