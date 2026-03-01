<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notification';

    const UPDATED_AT = null;

    protected $fillable = [
        'notify_id',
        'title',
        'body',
        'link',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'notify_id');
    }
}
