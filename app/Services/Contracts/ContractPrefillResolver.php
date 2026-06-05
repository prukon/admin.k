<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\Team;
use App\Models\User;

class ContractPrefillResolver
{
    /**
     * @param array<int, array<string, mixed>> $fieldsSchema
     * @return array<string, string>
     */
    public function resolveForContract(Contract $contract, array $fieldsSchema): array
    {
        $contract->loadMissing(['user', 'team']);

        /** @var User|null $student */
        $student = $contract->user;
        /** @var Team|null $team */
        $team = $contract->team;

        $values = [];
        foreach ($fieldsSchema as $field) {
            $key = $field['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }

            $source = $field['prefill_source'] ?? null;
            $values[$key] = $this->valueForSource(is_string($source) ? $source : null, $student, $team);
        }

        return $values;
    }

    /**
     * @param array<string, string> $prefill
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function mergeInput(array $prefill, array $input): array
    {
        $merged = $prefill;
        foreach ($input as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $merged[$key] = trim((string) $value);
        }

        return $merged;
    }

    /**
     * Разбор ФИО подписанта из полей формы / users.
     *
     * @return array{lastname: string, firstname: string, middlename: ?string, phone: ?string}
     */
    public function resolveSignerParts(User $student, array $filledData): array
    {
        $parentFields = $student->parentFormFields();

        $lastname = trim((string) ($filledData['signer_lastname'] ?? $parentFields['parent_lastname'] ?? ''));
        $firstname = trim((string) ($filledData['signer_firstname'] ?? $parentFields['parent_firstname'] ?? ''));
        $middlename = trim((string) ($filledData['signer_middlename'] ?? $parentFields['parent_middlename'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) (
            $filledData['signer_phone']
            ?? $parentFields['parent_phone']
            ?? $student->phone
            ?? ''
        )) ?: '';

        if (strlen($phone) === 10 && str_starts_with($phone, '9')) {
            $phone = '7' . $phone;
        } elseif (strlen($phone) === 11 && $phone[0] === '8') {
            $phone[0] = '7';
        }

        if ($lastname === '' && isset($filledData['parent_lastname'])) {
            $lastname = trim((string) $filledData['parent_lastname']);
        }
        if ($firstname === '' && isset($filledData['parent_firstname'])) {
            $firstname = trim((string) $filledData['parent_firstname']);
        }

        return [
            'lastname'   => $lastname,
            'firstname'  => $firstname,
            'middlename' => $middlename !== '' ? $middlename : null,
            'phone'      => $phone !== '' ? $phone : null,
        ];
    }

    private function valueForSource(?string $source, ?User $student, ?Team $team): string
    {
        if ($source === null || $student === null) {
            return '';
        }

        $parentFields = $student->parentFormFields();

        return match ($source) {
            ContractTemplatePrefillSources::CHILD_FULL_NAME,
            ContractTemplatePrefillSources::STUDENT_FULL_NAME,
            'student_full_name' => $this->studentFullName($student),
            ContractTemplatePrefillSources::CHILD_LASTNAME  => trim((string) ($student->lastname ?? '')),
            ContractTemplatePrefillSources::CHILD_FIRSTNAME => trim((string) ($student->name ?? '')),
            ContractTemplatePrefillSources::CHILD_BIRTHDAY  => $this->studentBirthday($student),
            ContractTemplatePrefillSources::STUDENT_PHONE      => (string) ($student->phone ?? ''),
            ContractTemplatePrefillSources::STUDENT_EMAIL     => (string) ($student->email ?? ''),
            ContractTemplatePrefillSources::PARENT_FULL_NAME  => (string) ($student->parent_full_name ?? ''),
            ContractTemplatePrefillSources::PARENT_LASTNAME   => (string) ($parentFields['parent_lastname'] ?? ''),
            ContractTemplatePrefillSources::PARENT_FIRSTNAME  => (string) ($parentFields['parent_firstname'] ?? ''),
            ContractTemplatePrefillSources::PARENT_MIDDLENAME => (string) ($parentFields['parent_middlename'] ?? ''),
            ContractTemplatePrefillSources::PARENT_PASSPORT   => (string) ($parentFields['parent_passport'] ?? ''),
            ContractTemplatePrefillSources::PARENT_PASSPORT_ISSUED => (string) ($parentFields['parent_passport_issued'] ?? ''),
            ContractTemplatePrefillSources::PARENT_ADDRESS    => (string) ($parentFields['parent_address'] ?? ''),
            ContractTemplatePrefillSources::PARENT_PHONE      => (string) ($parentFields['parent_phone'] ?? ''),
            ContractTemplatePrefillSources::PARENT_EMAIL      => (string) ($parentFields['parent_email'] ?? ''),
            ContractTemplatePrefillSources::TEAM_TITLE        => (string) ($team?->title ?? ''),
            default => '',
        };
    }

    private function studentFullName(User $student): string
    {
        return trim((string) ($student->full_name ?? ''));
    }

    private function studentBirthday(User $student): string
    {
        $birthday = $student->birthday;

        return $birthday ? $birthday->format('d.m.Y') : '';
    }
}
