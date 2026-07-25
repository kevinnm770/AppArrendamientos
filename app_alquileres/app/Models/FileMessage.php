<?php

namespace App\Models;

use App\Services\MessageAttachmentStorageService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FileMessage extends Model
{
    use HasUuids;

    protected $table = 'files_messages';

    protected $fillable = [
        'name_file',
        'type',
        'weigth',
        'bucket',
        'duration_seconds',
    ];

    protected $appends = [
        'url',
    ];

    public function message()
    {
        return $this->hasOne(Message::class, 'file_id');
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->bucket) {
            return null;
        }

        return app(MessageAttachmentStorageService::class)->temporaryUrl($this->bucket);
    }
}
