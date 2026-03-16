<?php

namespace App\Exceptions;

class MediaNotFoundException extends BusinessException
{
    public function __construct(
        string $message = 'Contenu media introuvable',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 'MEDIA_NOT_FOUND', 404, $previous);
    }
}
