<?php

namespace App\Enums;

enum MediaType: string
{
    case Audio = 'audio';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Audio => 'Audio',
            self::Video => 'Vidéo',
        };
    }
}
