<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestOrderConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->routeNotificationFor('mail', $this)) {
            $channels[] = 'mail';
        }

        if ($notifiable->routeNotificationFor('sms', $this)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Commande {$this->order->order_number} reçue — Popup Store")
            ->greeting('Merci pour votre commande !')
            ->line("Votre commande **{$this->order->order_number}** a bien été enregistrée.")
            ->line("Montant : {$this->formatTotal()} XAF.")
            ->action('Suivre ma commande', $this->trackUrl())
            ->line('Conservez votre numéro de commande pour le suivi.');
    }

    public function toSms(object $notifiable): string
    {
        return "Popup Store : commande {$this->order->order_number} reçue ({$this->formatTotal()} XAF). "
            ."Suivi : {$this->trackUrl()}";
    }

    private function trackUrl(): string
    {
        $base = rtrim(config('app.frontend_url'), '/');

        return "{$base}/track?order_number={$this->order->order_number}";
    }

    private function formatTotal(): string
    {
        return number_format((float) $this->order->total, 0, ',', ' ');
    }
}
