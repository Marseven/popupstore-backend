<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PaymentFailedNotification extends Notification
{
    public function __construct(
        private Order $order,
        private PaymentTransaction $transaction,
        private string $reason
    ) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Échec paiement')
            ->body("{$this->order->order_number} — {$this->reason}")
            ->icon('/logo_color_1.PNG')
            ->data(['url' => "/admin/orders/{$this->order->id}"]);
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => "Échec paiement {$this->order->order_number} — {$this->reason}",
            'icon' => 'payment',
            'type' => 'error',
            'action_url' => "/admin/orders/{$this->order->id}",
            'meta' => [
                'order_number' => $this->order->order_number,
                'reason' => $this->reason,
                'order_id' => $this->order->id,
            ],
        ];
    }
}
