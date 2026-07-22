<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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
        //
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

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $name = $notifiable->name ?? '';
            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            $url = route('auth.password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return (new MailMessage)
                ->subject('Restablece tu contraseña - App Arrendamientos')
                ->greeting($name !== '' ? "¡Hola, {$name}!" : '¡Hola!')
                ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en App Arrendamientos.')
                ->action('Restablecer contraseña', $url)
                ->line("Este enlace vencerá en {$expire} minutos.")
                ->line('Si no solicitaste este cambio, puedes ignorar este mensaje; tu contraseña seguirá siendo la misma.')
                ->salutation('Saludos,<br>El equipo de App Arrendamientos');
        });
    }
}
