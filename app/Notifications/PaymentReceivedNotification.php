<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PaymentReceivedNotification extends Notification
{
    public function __construct(
        private Order $order,
        private PaymentTransaction $transaction
    ) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Paiement reçu')
            ->body("{$this->order->order_number} — " . number_format($this->transaction->amount, 0, ',', ' ') . " XAF via {$this->transaction->provider}")
            ->icon('/logo_color_1.PNG')
            ->data(['url' => "/admin/orders/{$this->order->id}"]);
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => "Paiement reçu pour {$this->order->order_number} — " . number_format($this->transaction->amount, 0, ',', ' ') . " XAF via {$this->transaction->provider}",
            'icon' => 'payment',
            'type' => 'success',
            'action_url' => "/admin/orders/{$this->order->id}",
            'meta' => [
                'order_number' => $this->order->order_number,
                'amount' => $this->transaction->amount,
                'provider' => $this->transaction->provider,
                'order_id' => $this->order->id,
            ],
        ];
    }
}
