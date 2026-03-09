<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceElectronicDetail extends Model
{
    protected $fillable = [
        'invoice_id',
        'hacienda_key',
        'hacienda_consecutive',
        'emisor_nit',
        'emisor_name',
        'receptor_nit',
        'receptor_name',
        'electronic_status',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'xml_content',
        'xml_hash',
        'request_id',
        'error_code',
        'ptec_response',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'ptec_response' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('electronic_status', $status);
    }

    public function scopeAccepted($query)
    {
        return $query->where('electronic_status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('electronic_status', 'rejected');
    }

    public function scopePending($query)
    {
        return $query->where('electronic_status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('electronic_status', 'sent');
    }

    // Métodos de utilidad
    public function isAccepted(): bool
    {
        return $this->electronic_status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->electronic_status === 'rejected';
    }

    public function isPending(): bool
    {
        return $this->electronic_status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->electronic_status === 'sent' && !empty($this->sent_at);
    }

    public function getHaciendaUrl(): string
    {
        // URL para consultar en Hacienda
        return "https://www.hacienda.go.cr/consultafactura?clave={$this->hacienda_key}";
    }

    public function markAsSent(): void
    {
        $this->update([
            'electronic_status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsAccepted(): void
    {
        $this->update([
            'electronic_status' => 'accepted',
            'accepted_at' => now(),
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
            'electronic_status' => 'rejected',
            'rejected_at' => now(),
        ];

        if ($reason) {
            $data['ptec_response'] = ['message' => $reason];
        }

        $this->update($data);

        // Actualizar factura padre
        $this->invoice->update([
            'status' => 'draft',
            'locked_at' => null,
        ]);
    }
}
