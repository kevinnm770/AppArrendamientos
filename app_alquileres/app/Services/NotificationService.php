<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function create(
        int $notifyUserId,
        string $title,
        string $priority = 'medium',
        ?string $body = null,
        ?string $link = null
    ): Notification {
        $notification = Notification::create([
            'notify_id' => $notifyUserId,
            'title' => $title,
            'body' => $body ?? '',
            'priority' => $priority,
            'link' => $link,
            'status' => 'sent',
        ]);

        if (filled($body)) {
            $notification->update([
                'link' => $this->resolveNotificationViewLink($notifyUserId, $notification->id),
            ]);
        }

        return $notification;
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

    private function resolveNotificationViewLink(int $notifyUserId, int $notificationId): string
    {
        $user = User::query()->find($notifyUserId);

        if ($user?->isLessor()) {
            return route('admin.notifications.view', $notificationId);
        }

        if ($user?->isRoomer()) {
            return route('tenant.notifications.view', $notificationId);
        }

        return '#';
    }
}
