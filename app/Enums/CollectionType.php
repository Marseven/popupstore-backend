<?php

namespace App\Enums;

enum CollectionType: string
{
    case Shop = 'shop';
    case Partner = 'partner';
    case Campaign = 'campaign';

    public function label(): string
    {
        return match ($this) {
            self::Shop => 'Boutique',
            self::Partner => 'Partenaire',
            self::Campaign => 'Campagne',
        };
    }
}
