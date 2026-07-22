<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeAuthorizationRequested extends Notification
{
    public function __construct(
        protected string $newEmail,
        protected string $authorizeUrl,
        protected string $cancelUrl
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $notifiable->name ?? '';

        return (new MailMessage)
            ->subject('Autoriza el cambio de correo de tu cuenta - App Arrendamientos')
            ->greeting($name !== '' ? "¡Hola, {$name}!" : '¡Hola!')
            ->line("Solicitaste cambiar el correo de tu cuenta en App Arrendamientos a: **{$this->newEmail}**.")
            ->line('Para completar el cambio necesitamos tu autorización desde este correo (el actual) y la verificación del correo nuevo.')
            ->action('Autorizar cambio de correo', $this->authorizeUrl)
            ->line('Este enlace vencerá en 60 minutos.')
            ->line('¿No reconoces esta solicitud? [Cancélala aquí](' . $this->cancelUrl . ') para que tu correo no cambie.')
            ->salutation('Saludos,<br>El equipo de App Arrendamientos');
    }
}
