<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceElectronicDetail extends Model
{
    public const STATE_DRAFT = 'draft';
    public const STATE_PENDING = 'pending';
    public const STATE_QUEUED = 'queued';
    public const STATE_SENT = 'sent';
    public const STATE_ACCEPTED = 'accepted';
    public const STATE_REJECTED = 'rejected';
    public const STATE_ERROR = 'error';

    protected $fillable = [
        'invoice_id',
        'hacienda_key',
        'hacienda_consecutive',
        'sucursal',
        'terminal',
        'document_type',
        'internal_number',
        'emisor_nit',
        'emisor_name',
        'receptor_nit',
        'receptor_name',
        'electronic_status',
        'queued_at',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'status_checked_at',
        'error_at',
        'last_transition_message',
        'transition_log',
        'xml_content',
        'xml_hash',
        'request_id',
        'error_code',
        'ptec_response',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'status_checked_at' => 'datetime',
        'error_at' => 'datetime',
        'transition_log' => 'array',
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
        return $query->whereIn('electronic_status', ['pending', 'queued']);
    }

    public function scopeQueued($query)
    {
        return $query->where('electronic_status', 'queued');
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
        return in_array($this->electronic_status, ['pending', 'queued'], true);
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

    public function canTransitionTo(string $to): bool
    {
        $transitions = [
            self::STATE_PENDING => [self::STATE_QUEUED, self::STATE_ERROR],
            self::STATE_QUEUED => [self::STATE_SENT, self::STATE_ERROR],
            self::STATE_SENT => [self::STATE_ACCEPTED, self::STATE_REJECTED, self::STATE_ERROR],
            self::STATE_ERROR => [self::STATE_QUEUED],
            self::STATE_REJECTED => [self::STATE_QUEUED],
            self::STATE_ACCEPTED => [],
        ];

        return in_array($to, $transitions[$this->electronic_status] ?? [], true);
    }

    public function transitionTo(string $to, ?string $message = null): void
    {
        if ($this->electronic_status !== $to && !$this->canTransitionTo($to)) {
            return;
        }

        $now = now();
        $data = [
            'electronic_status' => $to,
            'last_transition_message' => $message,
        ];

        if ($to === self::STATE_QUEUED) {
            $data['queued_at'] = $now;
            $data['error_code'] = null;
        }

        if ($to === self::STATE_SENT) {
            $data['sent_at'] = $now;
        }

        if ($to === self::STATE_ACCEPTED) {
            $data['accepted_at'] = $now;
            $data['status_checked_at'] = $now;
            $data['error_at'] = null;
        }

        if ($to === self::STATE_REJECTED) {
            $data['rejected_at'] = $now;
            $data['status_checked_at'] = $now;
        }

        if ($to === self::STATE_ERROR) {
            $data['error_at'] = $now;
            $data['status_checked_at'] = $now;
        }

        $transitions = $this->transition_log ?? [];
        $transitions[] = [
            'from' => $this->electronic_status,
            'to' => $to,
            'message' => $message,
            'at' => $now->toIso8601String(),
        ];

        $data['transition_log'] = $transitions;

        $this->update($data);

        if ($to === self::STATE_ACCEPTED) {
            $this->invoice->update(['status' => 'confirmed', 'locked_at' => $now]);
        }

        if (in_array($to, [self::STATE_REJECTED, self::STATE_ERROR], true)) {
            $this->invoice->update(['status' => 'draft', 'locked_at' => null]);
        }

        if (in_array($to, [self::STATE_QUEUED, self::STATE_SENT], true)) {
            $this->invoice->update(['status' => 'sent']);
        }
    }
}
