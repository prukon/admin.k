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
        $lastname = trim((string) ($filledData['signer_lastname'] ?? $student->parent_lastname ?? ''));
        $firstname = trim((string) ($filledData['signer_firstname'] ?? $student->parent_firstname ?? ''));
        $middlename = trim((string) ($filledData['signer_middlename'] ?? $student->parent_middlename ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($filledData['signer_phone'] ?? $student->phone ?? '')) ?: '';

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

        return match ($source) {
            ContractTemplatePrefillSources::STUDENT_FULL_NAME => trim(($student->lastname ?? '') . ' ' . ($student->name ?? '')),
            ContractTemplatePrefillSources::STUDENT_PHONE      => (string) ($student->phone ?? ''),
            ContractTemplatePrefillSources::STUDENT_EMAIL     => (string) ($student->email ?? ''),
            ContractTemplatePrefillSources::PARENT_FULL_NAME  => (string) ($student->parent_full_name ?? ''),
            ContractTemplatePrefillSources::PARENT_LASTNAME   => (string) ($student->parent_lastname ?? ''),
            ContractTemplatePrefillSources::PARENT_FIRSTNAME  => (string) ($student->parent_firstname ?? ''),
            ContractTemplatePrefillSources::PARENT_MIDDLENAME  => (string) ($student->parent_middlename ?? ''),
            ContractTemplatePrefillSources::TEAM_TITLE          => (string) ($team?->title ?? ''),
            default => '',
        };
    }
}
