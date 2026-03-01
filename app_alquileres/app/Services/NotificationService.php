<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public function create(
        int $notifyUserId,
        string $title,
        string $priority = 'medium',
        ?string $body = null,
        ?string $link = null
    ): Notification {
        return Notification::create([
            'notify_id' => $notifyUserId,
            'title' => $title,
            'body' => $body ?? '',
            'priority' => $priority,
            'link' => $link,
            'status' => 'sent',
        ]);
    }

    public function createForUsers(
        array $notifyUserIds,
        string $title,
        string $priority = 'medium',
        ?string $body = null,
        ?string $link = null
    ): void {
        foreach (array_unique($notifyUserIds) as $notifyUserId) {
            $this->create((int) $notifyUserId, $title, $priority, $body, $link);
        }
    }
}
