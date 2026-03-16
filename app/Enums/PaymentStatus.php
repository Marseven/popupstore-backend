<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Processing => 'En cours',
            self::Success => 'Réussi',
            self::Failed => 'Échoué',
            self::Refunded => 'Remboursé',
        };
    }
}
