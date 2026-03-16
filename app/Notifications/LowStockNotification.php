<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class LowStockNotification extends Notification
{
    public function __construct(
        private Product $product,
        private string $size,
        private int $quantity
    ) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Stock bas')
            ->body("{$this->product->name} (taille {$this->size}) — {$this->quantity} restant(s)")
            ->icon('/logo_color_1.PNG')
            ->data(['url' => "/admin/products/{$this->product->id}/edit"]);
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => "Stock bas : {$this->product->name} (taille {$this->size}) — {$this->quantity} restant(s)",
            'icon' => 'alert',
            'type' => 'warning',
            'action_url' => "/admin/products/{$this->product->id}/edit",
            'meta' => [
                'product_name' => $this->product->name,
                'size' => $this->size,
                'quantity' => $this->quantity,
                'product_id' => $this->product->id,
            ],
        ];
    }
}
