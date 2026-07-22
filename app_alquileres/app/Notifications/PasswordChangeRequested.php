<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeRequested extends Notification
{
    public function __construct(protected string $url)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $notifiable->name ?? '';

        return (new MailMessage)
            ->subject('Confirma el cambio de tu contraseña - App Arrendamientos')
            ->greeting($name !== '' ? "¡Hola, {$name}!" : '¡Hola!')
            ->line('Solicitaste cambiar la contraseña de tu cuenta en App Arrendamientos.')
            ->action('Confirmar cambio de contraseña', $this->url)
            ->line('Este enlace vencerá en 60 minutos.')
            ->line('Si no solicitaste este cambio, ignora este mensaje; tu contraseña actual seguirá funcionando.')
            ->salutation('Saludos,<br>El equipo de App Arrendamientos');
    }
}
