<?php

namespace App\Exceptions;

class InsufficientStockException extends BusinessException
{
    protected string $productName;
    protected int $requested;
    protected int $available;

    public function __construct(
        string $productName,
        int $requested,
        int $available,
        ?\Throwable $previous = null,
    ) {
        $this->productName = $productName;
        $this->requested = $requested;
        $this->available = $available;

        $message = $productName
            ? "Stock insuffisant pour \"{$productName}\" : {$requested} demande(s), {$available} disponible(s)"
            : "Stock insuffisant : {$requested} demande(s), {$available} disponible(s)";

        parent::__construct($message, 'INSUFFICIENT_STOCK', 422, $previous);
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getRequested(): int
    {
        return $this->requested;
    }

    public function getAvailable(): int
    {
        return $this->available;
    }
}
