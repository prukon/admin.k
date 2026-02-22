<?php

namespace App\Services\BlogAi\Exceptions;

use RuntimeException;

class OpenAiRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $openAiErrorType = null,
    ) {
        parent::__construct($message);
    }
}

