<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Notifications\AppEventMail;

class NotificationService
{
    /**
     * Envía un correo a un conjunto de usuarios (omitiendo nulos), sin crear notificaciones en la app.
     * Usado para eventos puntuales del ciclo de vida de contratos/adendums (registro, ruptura/desestimación).
     *
     * @param iterable<User|null> $users
     */
    public function emailUsers(iterable $users, string $title, string $body = '', ?string $link = null): void
    {
        foreach ($users as $user) {
            $user?->notify(new AppEventMail($title, $body, $link));
        }
    }

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
