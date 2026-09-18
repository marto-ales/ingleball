<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPassword extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Restablecer tu contraseña de Ingleball')
            ->greeting('¡Hola '.$notifiable->name.'!')
            ->line('Recibiste este correo porque se solicitó restablecer tu contraseña.')
            ->line('Este enlace es válido por '.config('auth.passwords.users.expire', 60).' minutos.')
            ->action('Restablecer contraseña', $url)
            ->line('Si no pediste este cambio, podés ignorar el correo: tu contraseña actual sigue igual.')
            ->salutation('Saludos, el equipo de Ingleball');
    }
}
