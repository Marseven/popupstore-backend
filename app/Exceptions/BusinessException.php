<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class BusinessException extends \RuntimeException
{
    protected int $statusCode;
    protected string $errorCode;

    public function __construct(
        string $message = 'An error occurred',
        string $errorCode = 'BUSINESS_ERROR',
        int $statusCode = 422,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;

        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ], $this->statusCode);
    }
}
