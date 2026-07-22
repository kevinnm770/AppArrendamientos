<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeVerificationRequested extends Notification
{
    public function __construct(
        protected string $accountName,
        protected string $verifyUrl
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifica este correo para tu cuenta - App Arrendamientos')
            ->greeting('¡Hola!')
            ->line("La cuenta de {$this->accountName} en App Arrendamientos solicitó usar este correo electrónico.")
            ->action('Verificar este correo', $this->verifyUrl)
            ->line('Este enlace vencerá en 60 minutos.')
            ->line('Si no reconoces esta solicitud, puedes ignorar este mensaje.')
            ->salutation('Saludos,<br>El equipo de App Arrendamientos');
    }
}
