<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailChangeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'new_email',
        'current_confirmed_at',
        'new_confirmed_at',
        'cancelled_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'current_confirmed_at' => 'datetime',
            'new_confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isCancelled(): bool
    {
        return !is_null($this->cancelled_at);
    }

    public function isFullyConfirmed(): bool
    {
        return !is_null($this->current_confirmed_at) && !is_null($this->new_confirmed_at);
    }
}
