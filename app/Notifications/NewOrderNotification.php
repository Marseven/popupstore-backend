<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewOrderNotification extends Notification
{
    public function __construct(private Order $order) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Nouvelle commande')
            ->body("Commande {$this->order->order_number} — " . number_format($this->order->total, 0, ',', ' ') . ' XAF')
            ->icon('/logo_color_1.PNG')
            ->data(['url' => "/admin/orders/{$this->order->id}"]);
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => "Nouvelle commande {$this->order->order_number} de {$this->order->customer_name} — " . number_format($this->order->total, 0, ',', ' ') . ' XAF',
            'icon' => 'order',
            'type' => 'info',
            'action_url' => "/admin/orders/{$this->order->id}",
            'meta' => [
                'order_number' => $this->order->order_number,
                'customer' => $this->order->customer_name,
                'total' => $this->order->total,
                'order_id' => $this->order->id,
            ],
        ];
    }
}
