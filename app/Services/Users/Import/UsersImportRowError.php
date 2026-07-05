<?php

namespace App\Services\Users\Import;

final class UsersImportRowError
{
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $field,
        public readonly string $message,
    ) {
    }

    /**
     * @return array{row: int, field: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'row' => $this->rowNumber,
            'field' => $this->field,
            'message' => $this->message,
        ];
    }
}
