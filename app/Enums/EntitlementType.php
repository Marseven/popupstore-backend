<?php

namespace App\Enums;

enum EntitlementType: string
{
    case Catalog = 'catalog';
    case ExclusiveTracks = 'exclusive_tracks';
    case FinaleTicket = 'finale_ticket';

    public function label(): string
    {
        return match ($this) {
            self::Catalog => 'Catalogue',
            self::ExclusiveTracks => 'Titres exclusifs',
            self::FinaleTicket => 'Ticket finale',
        };
    }
}
