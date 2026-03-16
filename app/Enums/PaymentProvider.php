<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case Airtel = 'airtel';
    case Moov = 'moov';

    public function label(): string
    {
        return match ($this) {
            self::Airtel => 'Airtel Money',
            self::Moov => 'Moov Money',
        };
    }
}
