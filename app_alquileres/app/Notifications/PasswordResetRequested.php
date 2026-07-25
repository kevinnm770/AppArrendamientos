<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetRequested extends Notification
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
            ->subject('Restablece tu contraseña - App Arrendamientos')
            ->greeting($name !== '' ? "¡Hola, {$name}!" : '¡Hola!')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en App Arrendamientos.')
            ->action('Restablecer contraseña', $this->url)
            ->line('Este enlace vencerá en 60 minutos.')
            ->line('Si no solicitaste este cambio, puedes ignorar este mensaje; tu contraseña seguirá siendo la misma.')
            ->salutation('Saludos,<br>El equipo de App Arrendamientos');
    }
}
