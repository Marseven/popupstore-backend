<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Confirmed => 'Confirmée',
            self::Processing => 'En cours',
            self::Shipped => 'Expédiée',
            self::Delivered => 'Livrée',
            self::Cancelled => 'Annulée',
            self::Paid => 'Payée',
        };
    }
}
