<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Active => 'Active',
            self::Closed => 'Terminée',
        };
    }
}
