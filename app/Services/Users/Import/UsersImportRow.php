<?php

namespace App\Services\Users\Import;

final class UsersImportRow
{
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $studentLastname,
        public readonly string $studentName,
        public readonly string $teamTitle,
        public readonly string $legalEntityTitle,
        public readonly ?string $studentEmail,
        public readonly ?string $studentPhone,
        public readonly ?string $birthday,
        public readonly bool $birthdayInvalid,
        public readonly bool $isEnabled,
        public readonly ?string $parentEmail,
        public readonly ?string $parentLastname,
        public readonly ?string $parentFirstname,
        public readonly ?string $parentMiddlename,
        public readonly ?string $parentPhone,
        public readonly string $mode,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toCacheArray(): array
    {
        return [
            'row_number' => $this->rowNumber,
            'student_lastname' => $this->studentLastname,
            'student_name' => $this->studentName,
            'team_title' => $this->teamTitle,
            'legal_entity_title' => $this->legalEntityTitle,
            'student_email' => $this->studentEmail,
            'student_phone' => $this->studentPhone,
            'birthday' => $this->birthday,
            'birthday_invalid' => $this->birthdayInvalid,
            'is_enabled' => $this->isEnabled,
            'parent_email' => $this->parentEmail,
            'parent_lastname' => $this->parentLastname,
            'parent_firstname' => $this->parentFirstname,
            'parent_middlename' => $this->parentMiddlename,
            'parent_phone' => $this->parentPhone,
            'mode' => $this->mode,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromCacheArray(array $data): self
    {
        return new self(
            rowNumber: (int) ($data['row_number'] ?? 0),
            studentLastname: (string) ($data['student_lastname'] ?? ''),
            studentName: (string) ($data['student_name'] ?? ''),
            teamTitle: (string) ($data['team_title'] ?? ''),
            legalEntityTitle: (string) ($data['legal_entity_title'] ?? ''),
            studentEmail: isset($data['student_email']) ? (string) $data['student_email'] : null,
            studentPhone: isset($data['student_phone']) ? (string) $data['student_phone'] : null,
            birthday: isset($data['birthday']) ? (string) $data['birthday'] : null,
            birthdayInvalid: (bool) ($data['birthday_invalid'] ?? false),
            isEnabled: (bool) ($data['is_enabled'] ?? true),
            parentEmail: isset($data['parent_email']) ? (string) $data['parent_email'] : null,
            parentLastname: isset($data['parent_lastname']) ? (string) $data['parent_lastname'] : null,
            parentFirstname: isset($data['parent_firstname']) ? (string) $data['parent_firstname'] : null,
            parentMiddlename: isset($data['parent_middlename']) ? (string) $data['parent_middlename'] : null,
            parentPhone: isset($data['parent_phone']) ? (string) $data['parent_phone'] : null,
            mode: (string) ($data['mode'] ?? 'create'),
        );
    }

    public function hasParentData(): bool
    {
        return $this->parentEmail !== null
            || $this->parentLastname !== null
            || $this->parentFirstname !== null
            || $this->parentMiddlename !== null
            || $this->parentPhone !== null;
    }

    /**
     * @return array<string, string|null>
     */
    public function parentFingerprintFields(): array
    {
        return [
            'parent_email' => $this->parentEmail,
            'parent_lastname' => $this->parentLastname,
            'parent_firstname' => $this->parentFirstname,
            'parent_middlename' => $this->parentMiddlename,
            'parent_phone' => $this->parentPhone,
        ];
    }
}
