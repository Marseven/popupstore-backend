<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Sent synchronously (no queue) so the reset email goes out during the request,
// without depending on a running queue worker.
class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe — Popup Store')
            ->greeting('Bonjour,')
            ->line('Vous avez demandé la réinitialisation de votre mot de passe.')
            ->action('Réinitialiser le mot de passe', $this->resetUrl($notifiable))
            ->line('Ce lien expire dans 60 minutes.')
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.");
    }

    /** Reset happens in the SPA, so the link points at the frontend, not the API. */
    private function resetUrl(object $notifiable): string
    {
        $base = rtrim(config('app.frontend_url'), '/');
        $email = urlencode($notifiable->getEmailForPasswordReset());

        return "{$base}/reset-password?token={$this->token}&email={$email}";
    }
}
