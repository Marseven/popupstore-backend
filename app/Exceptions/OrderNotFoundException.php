<?php

namespace App\Exceptions;

class OrderNotFoundException extends BusinessException
{
    public function __construct(
        string $message = 'Commande introuvable',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 'ORDER_NOT_FOUND', 404, $previous);
    }
}
