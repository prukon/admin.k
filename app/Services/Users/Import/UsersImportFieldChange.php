<?php

namespace App\Services\Users\Import;

/**
 * Одно отличие строки импорта от текущего состояния ученика (только для preview).
 */
final class UsersImportFieldChange
{
    public const KIND_CHANGED = 'changed';

    public const KIND_CLEARED = 'cleared';

    public function __construct(
        public readonly string $field,
        public readonly string $label,
        public readonly string $from,
        public readonly string $to,
        public readonly string $kind,
    ) {
    }

    /**
     * @return array{field: string, label: string, from: string, to: string, kind: string}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'label' => $this->label,
            'from' => $this->from,
            'to' => $this->to,
            'kind' => $this->kind,
        ];
    }
}
