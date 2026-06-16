<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestPaymentConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public PaymentTransaction $transaction) {}

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
            ->subject("Paiement confirmé — commande {$this->order->order_number}")
            ->greeting('Paiement confirmé !')
            ->line("Nous avons bien reçu votre paiement pour la commande **{$this->order->order_number}**.")
            ->line("Montant : {$this->formatTotal()} XAF.")
            ->action('Suivre ma commande', $this->trackUrl())
            ->line('Votre commande est en cours de préparation.');
    }

    public function toSms(object $notifiable): string
    {
        return "Popup Store : paiement confirmé pour la commande {$this->order->order_number} "
            ."({$this->formatTotal()} XAF). Suivi : {$this->trackUrl()}";
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
