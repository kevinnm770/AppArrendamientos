<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    public const EDIT_WINDOW_MINUTES = 5;

    protected $fillable = [
        'conversation_id',
        'sender_user_id',
        'receiver_user_id',
        'type',
        'body',
        'file_id',
        'read_at',
        'edited_at',
        'deleted_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    public function file()
    {
        return $this->belongsTo(FileMessage::class, 'file_id');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isWithinEditWindow(): bool
    {
        return $this->created_at->addMinutes(self::EDIT_WINDOW_MINUTES)->isFuture();
    }

    public function isEditableBy(User $user): bool
    {
        return !$this->isDeleted()
            && $this->sender_user_id === $user->id
            && $this->isWithinEditWindow();
    }

    public function isDeletableBy(User $user): bool
    {
        return !$this->isDeleted()
            && $this->sender_user_id === $user->id
            && $this->isWithinEditWindow();
    }

    public function toChatArray(User $viewer): array
    {
        $isDeleted = $this->isDeleted();

        return [
            'id' => $this->id,
            'sender_user_id' => $this->sender_user_id,
            'is_own' => $this->sender_user_id === $viewer->id,
            'type' => $this->type,
            'body' => $isDeleted ? null : $this->body,
            'is_deleted' => $isDeleted,
            'is_edited' => !$isDeleted && $this->edited_at !== null,
            'file' => (!$isDeleted && $this->file) ? [
                'id' => $this->file->id,
                'name' => $this->file->name_file,
                'extension' => $this->file->type,
                'url' => $this->file->url,
                'duration_seconds' => $this->file->duration_seconds,
            ] : null,
            'read_at' => optional($this->read_at)->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_label' => $this->created_at->format('H:i'),
            'editable' => $this->isEditableBy($viewer),
            'deletable' => $this->isDeletableBy($viewer),
        ];
    }
}
