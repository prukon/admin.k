<?php

namespace App\Services\Users\Import;

final class UsersImportValidationResult
{
    /**
     * @param list<UsersImportRowError> $errors
     * @param list<UsersImportRow> $rows
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors,
        public readonly array $rows = [],
        public readonly int $createCount = 0,
        public readonly int $updateCount = 0,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => array_map(static fn (UsersImportRowError $error) => $error->toArray(), $this->errors),
            'summary' => [
                'total_rows' => count($this->rows),
                'create_count' => $this->createCount,
                'update_count' => $this->updateCount,
            ],
            'preview' => array_map(static function (UsersImportRow $row) {
                return [
                    'row' => $row->rowNumber,
                    'student' => trim($row->studentLastname . ' ' . $row->studentName),
                    'team' => $row->teamTitle,
                    'legal_entity' => $row->legalEntityTitle,
                    'mode' => $row->mode,
                    'email' => $row->studentEmail,
                ];
            }, $this->rows),
        ];
    }
}
