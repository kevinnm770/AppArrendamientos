<?php

namespace App\Models;

use App\Services\PropertyPhotoStorageService;
use Illuminate\Database\Eloquent\Model;

class PropertyPhoto extends Model
{
    protected $table = 'propertyphotos';

    protected $fillable = [
        'property_id',
        'path',
        'media_type',
        'position',
        'caption',
        'taken_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    protected $appends = [
        'url',
    ];

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        return app(PropertyPhotoStorageService::class)->temporaryUrl($this->path);
    }
}
