<?php

namespace App\Exceptions;

class UnauthorizedActionException extends BusinessException
{
    public function __construct(
        string $message = 'Action non autorisee',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 'UNAUTHORIZED', 403, $previous);
    }
}
