<?php

namespace App\Services\Signatures;

use RuntimeException;

class PodpislonCredentialsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorKey = 'podpislon_api_key',
        public readonly string $errorCode = 'podpislon_credentials',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array{success: false, message: string, code: string, errors: array<string, list<string>>}
     */
    public function toSendFailure(): array
    {
        return [
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'errors' => [
                $this->errorKey => [$this->getMessage()],
            ],
        ];
    }
}
