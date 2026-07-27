<?php

namespace App\Providers;

use App\Services\Hacienda\Contracts\XmlSignerInterface;
use App\Services\Hacienda\XadesBesSigner;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(XmlSignerInterface::class, XadesBesSigner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            $name = $notifiable->name ?? '';

            return (new MailMessage)
                ->subject('Verifica tu correo electrónico - App Arrendamientos')
                ->greeting($name !== '' ? "¡Hola, {$name}!" : '¡Hola!')
                ->line('Gracias por registrarte en App Arrendamientos. Confirma tu correo electrónico para activar tu cuenta y comenzar a gestionar tus arrendamientos.')
                ->action('Verificar mi correo electrónico', $url)
                ->line('Este enlace de verificación vencerá en 60 minutos.')
                ->line('Si no creaste esta cuenta, puedes ignorar este mensaje.')
                ->salutation('Saludos,<br>El equipo de App Arrendamientos');
        });
    }
}
