<?php

namespace App\Services\BlogVk\Exceptions;

use RuntimeException;

class VkApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $vkErrorCode = null,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message);
    }
}
