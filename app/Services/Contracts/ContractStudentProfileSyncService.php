<?php

namespace App\Services\Contracts;

use App\Models\User;

/**
 * Сохраняет данные ученика из заполненного договора в карточку users.
 */
class ContractStudentProfileSyncService
{
    /**
     * @param array<string, mixed> $filledData
     */
    public function syncFromFilledData(User $student, array $filledData): void
    {
        $updates = [];

        if (array_key_exists('child_lastname', $filledData)) {
            $lastname = trim((string) $filledData['child_lastname']);
            if ($lastname !== '') {
                $updates['lastname'] = $lastname;
            }
        }

        if (array_key_exists('child_firstname', $filledData)) {
            $firstname = trim((string) $filledData['child_firstname']);
            if ($firstname !== '') {
                $updates['name'] = $firstname;
            }
        }

        if (array_key_exists(ContractTemplatePrefillSources::CHILD_MIDDLENAME, $filledData)) {
            $middlename = trim((string) $filledData[ContractTemplatePrefillSources::CHILD_MIDDLENAME]);
            if ($middlename !== '') {
                $updates['middlename'] = mb_substr($middlename, 0, 100);
            }
        }

        if (array_key_exists(ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE, $filledData)) {
            $genitive = trim((string) $filledData[ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE]);
            if ($genitive !== '') {
                $updates['full_name_genitive'] = $genitive;
            }
        }

        if (array_key_exists('child_birthday', $filledData)) {
            $birthday = ContractTemplateVariablePresets::parseFillFormDate(
                (string) $filledData['child_birthday'],
            );
            if ($birthday !== null) {
                $updates['birthday'] = $birthday;
            }
        }

        if (array_key_exists(ContractTemplatePrefillSources::CHILD_ADDRESS, $filledData)) {
            $address = trim((string) $filledData[ContractTemplatePrefillSources::CHILD_ADDRESS]);
            $updates['address'] = $address !== '' ? $address : null;
        }

        if (array_key_exists(ContractTemplatePrefillSources::CHILD_PASSPORT, $filledData)) {
            $passport = trim((string) $filledData[ContractTemplatePrefillSources::CHILD_PASSPORT]);
            $updates['passport'] = $passport !== '' ? mb_substr($passport, 0, 100) : null;
        }

        if (array_key_exists(ContractTemplatePrefillSources::CHILD_PASSPORT_ISSUED_AT, $filledData)) {
            $rawIssuedAt = trim((string) $filledData[ContractTemplatePrefillSources::CHILD_PASSPORT_ISSUED_AT]);
            if ($rawIssuedAt === '') {
                $updates['passport_issued_at'] = null;
            } else {
                $issuedAt = ContractTemplateVariablePresets::parseFillFormDate($rawIssuedAt);
                if ($issuedAt !== null && $issuedAt->lte(now()->startOfDay())) {
                    $updates['passport_issued_at'] = $issuedAt->toDateString();
                }
            }
        }

        if ($updates === []) {
            return;
        }

        $student->forceFill($updates)->save();
    }
}
