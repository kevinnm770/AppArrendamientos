<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppEventMail extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $body = '',
        private readonly ?string $link = null,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $notifiable->name ?? '';

        $message = (new MailMessage)
            ->subject($this->title)
            ->greeting($name !== '' ? "¡Hola, {$name}!" : '¡Hola!');

        $plainBody = trim(strip_tags(str_replace(['<p>', '</p>', '<br>', '<br/>', '<br />'], ["", "\n", "\n", "\n", "\n"], $this->body)));

        foreach (array_filter(array_map('trim', explode("\n", $plainBody))) as $line) {
            $message->line($line);
        }

        if ($plainBody === '') {
            $message->line($this->title);
        }

        if ($this->link) {
            $message->action('Ver detalle', $this->link);
        }

        return $message->salutation('Saludos,<br>El equipo de App Arrendamientos');
    }
}
