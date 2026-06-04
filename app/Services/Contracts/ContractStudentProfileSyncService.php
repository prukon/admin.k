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

        if (array_key_exists('child_birthday', $filledData)) {
            $birthday = ContractTemplateVariablePresets::parseFillFormDate(
                (string) $filledData['child_birthday'],
            );
            if ($birthday !== null) {
                $updates['birthday'] = $birthday;
            }
        }

        if ($updates === []) {
            return;
        }

        $student->forceFill($updates)->save();
    }
}
