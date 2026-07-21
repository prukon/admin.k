<?php

namespace App\Services\Users\Import;

final class UsersImportValidationResult
{
    /**
     * @param list<UsersImportRowError> $errors
     * @param list<UsersImportRow> $rows
     * @param array<int, list<UsersImportFieldChange>> $changesByRow keyed by Excel row number
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors,
        public readonly array $rows = [],
        public readonly int $createCount = 0,
        public readonly int $updateCount = 0,
        public readonly array $changesByRow = [],
        public readonly int $updateWithChangesCount = 0,
        public readonly int $updateUnchangedCount = 0,
        public readonly int $updateWithClearsCount = 0,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $withChanges = [];
        $withoutChanges = [];

        foreach ($this->rows as $row) {
            $changes = $this->changesByRow[$row->rowNumber] ?? [];
            $item = [
                'row' => $row->rowNumber,
                'student' => trim($row->studentLastname . ' ' . $row->studentName),
                'team' => $row->teamTitle,
                'legal_entity' => $row->legalEntityTitle,
                'mode' => $row->mode,
                'email' => $row->studentEmail,
                'changes' => array_map(
                    static fn (UsersImportFieldChange $change) => $change->toArray(),
                    $changes,
                ),
                'has_clears' => $this->rowHasClears($changes),
            ];

            if ($changes !== []) {
                $withChanges[] = $item;
            } else {
                $withoutChanges[] = $item;
            }
        }

        return [
            'valid' => $this->valid,
            'errors' => array_map(static fn (UsersImportRowError $error) => $error->toArray(), $this->errors),
            'summary' => [
                'total_rows' => count($this->rows),
                'create_count' => $this->createCount,
                'update_count' => $this->updateCount,
                'update_with_changes_count' => $this->updateWithChangesCount,
                'update_unchanged_count' => $this->updateUnchangedCount,
                'update_with_clears_count' => $this->updateWithClearsCount,
            ],
            // Сначала строки с реальными изменениями, затем остальные (порядок внутри групп — как в Excel).
            'preview' => array_merge($withChanges, $withoutChanges),
        ];
    }

    /**
     * @param list<UsersImportFieldChange> $changes
     */
    private function rowHasClears(array $changes): bool
    {
        foreach ($changes as $change) {
            if ($change->kind === UsersImportFieldChange::KIND_CLEARED) {
                return true;
            }
        }

        return false;
    }
}
