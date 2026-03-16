<?php

namespace App\Exceptions;

class PaymentException extends BusinessException
{
    public function __construct(
        string $message = 'Payment error',
        string $errorCode = 'PAYMENT_ERROR',
        int $statusCode = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode, $statusCode, $previous);
    }

    public static function connectionFailed(): static
    {
        return new static(
            message: 'Erreur de connexion au service de paiement',
            errorCode: 'PAYMENT_CONNECTION_FAILED',
            statusCode: 503,
        );
    }

    public static function billCreationFailed(string $reason): static
    {
        return new static(
            message: "Impossible de creer la facture : {$reason}",
            errorCode: 'PAYMENT_BILL_CREATION_FAILED',
            statusCode: 422,
        );
    }

    public static function alreadyPaid(): static
    {
        return new static(
            message: 'Cette commande a deja ete payee',
            errorCode: 'PAYMENT_ALREADY_PAID',
            statusCode: 409,
        );
    }
}
