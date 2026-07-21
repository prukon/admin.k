<?php

namespace App\Services\Users\Import;

use App\Models\ParentProfile;
use App\Models\PartnerLegalEntity;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

final class UsersImportValidator
{
    public function __construct(
        private readonly UsersImportPreviewDiffBuilder $previewDiffBuilder,
    ) {
    }

    /**
     * @param list<UsersImportRow> $rows
     * @param list<UsersImportRowError> $parseErrors
     */
    public function validate(array $rows, array $parseErrors, int $partnerId): UsersImportValidationResult
    {
        $errors = $parseErrors;
        $studentRoleId = (int) (Role::query()->where('name', 'user')->value('id') ?? 0);

        if ($studentRoleId <= 0) {
            $errors[] = new UsersImportRowError(0, 'file', 'Роль ученика не найдена в системе.');

            return new UsersImportValidationResult(false, $errors);
        }

        $legalEntities = $this->resolveLegalEntities($partnerId);
        $teams = $this->resolveTeams($partnerId);

        $this->validateCrossRowParentConsistency($rows, $errors);
        $this->validateCrossRowStudentEmailConsistency($rows, $errors);

        $validatedRows = [];
        $createCount = 0;
        $updateCount = 0;

        foreach ($rows as $row) {
            $rowErrors = $this->validateRow(
                $row,
                $partnerId,
                $studentRoleId,
                $legalEntities,
                $teams,
            );

            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);
                continue;
            }

            $mode = $row->studentEmail !== null
                ? $this->resolveStudentMode($row->studentEmail, $partnerId, $studentRoleId)
                : 'create';

            if ($mode === 'error') {
                $errors[] = new UsersImportRowError(
                    $row->rowNumber,
                    'Email ученика',
                    'Указанный email уже занят пользователем, который не является учеником текущей организации.'
                );
                continue;
            }

            $validatedRows[] = new UsersImportRow(
                rowNumber: $row->rowNumber,
                studentLastname: $row->studentLastname,
                studentName: $row->studentName,
                teamTitle: $row->teamTitle,
                legalEntityTitle: $row->legalEntityTitle,
                studentEmail: $row->studentEmail,
                studentPhone: $row->studentPhone,
                birthday: $row->birthday,
                birthdayInvalid: $row->birthdayInvalid,
                isEnabled: $row->isEnabled,
                parentEmail: $row->parentEmail,
                parentLastname: $row->parentLastname,
                parentFirstname: $row->parentFirstname,
                parentMiddlename: $row->parentMiddlename,
                parentPhone: $row->parentPhone,
                mode: $mode,
            );

            if ($mode === 'update') {
                $updateCount++;
            } else {
                $createCount++;
            }
        }

        $isValid = $errors === [];
        $changesByRow = [];
        $updateWithClearsCount = 0;
        $updateWithChangesCount = 0;
        $updateUnchangedCount = 0;

        if ($isValid && $updateCount > 0) {
            $diff = $this->buildPreviewDiff($validatedRows, $partnerId, $studentRoleId);
            $changesByRow = $diff['changes_by_row'];
            $updateWithChangesCount = $diff['update_with_changes_count'];
            $updateWithClearsCount = $diff['update_with_clears_count'];
            $updateUnchangedCount = max(0, $updateCount - $updateWithChangesCount);
        }

        return new UsersImportValidationResult(
            valid: $isValid,
            errors: $errors,
            rows: $validatedRows,
            createCount: $createCount,
            updateCount: $updateCount,
            changesByRow: $changesByRow,
            updateWithChangesCount: $updateWithChangesCount,
            updateUnchangedCount: $updateUnchangedCount,
            updateWithClearsCount: $updateWithClearsCount,
        );
    }

    /**
     * @param list<UsersImportRow> $rows
     * @return array{
     *     changes_by_row: array<int, list<UsersImportFieldChange>>,
     *     update_with_changes_count: int,
     *     update_with_clears_count: int
     * }
     */
    private function buildPreviewDiff(array $rows, int $partnerId, int $studentRoleId): array
    {
        $updateEmails = [];
        $parentEmails = [];

        foreach ($rows as $row) {
            if ($row->mode !== 'update' || $row->studentEmail === null) {
                continue;
            }

            $updateEmails[] = $row->studentEmail;

            if ($row->parentEmail !== null) {
                $parentEmails[] = $row->parentEmail;
            }
        }

        $updateEmails = array_values(array_unique($updateEmails));
        $parentEmails = array_values(array_unique($parentEmails));

        $studentsByEmailLower = collect();
        if ($updateEmails !== []) {
            $studentsByEmailLower = User::query()
                ->where('partner_id', $partnerId)
                ->where('role_id', $studentRoleId)
                ->where(function ($query) use ($updateEmails) {
                    foreach ($updateEmails as $index => $email) {
                        if ($index === 0) {
                            $query->whereRaw('LOWER(email) = ?', [$email]);
                        } else {
                            $query->orWhereRaw('LOWER(email) = ?', [$email]);
                        }
                    }
                })
                ->with(['teams', 'parentProfile'])
                ->get()
                ->keyBy(static fn (User $user) => mb_strtolower((string) $user->email));
        }

        $parentsByEmailLower = collect();
        if ($parentEmails !== []) {
            $parentsByEmailLower = ParentProfile::query()
                ->where('partner_id', $partnerId)
                ->where(function ($query) use ($parentEmails) {
                    foreach ($parentEmails as $index => $email) {
                        if ($index === 0) {
                            $query->whereRaw('LOWER(email) = ?', [$email]);
                        } else {
                            $query->orWhereRaw('LOWER(email) = ?', [$email]);
                        }
                    }
                })
                ->get()
                ->keyBy(static fn (ParentProfile $parent) => mb_strtolower((string) $parent->email));
        }

        return $this->previewDiffBuilder->buildForRows(
            $rows,
            $studentsByEmailLower,
            $parentsByEmailLower,
        );
    }

    /**
     * @param list<UsersImportRow> $rows
     * @param list<UsersImportRowError> $errors
     */
    private function validateCrossRowParentConsistency(array $rows, array &$errors): void
    {
        $seenByEmail = [];

        foreach ($rows as $row) {
            if ($row->parentEmail === null) {
                continue;
            }

            $fingerprint = UsersImportNormalizer::parentFingerprint($row->parentFingerprintFields());

            if (! isset($seenByEmail[$row->parentEmail])) {
                $seenByEmail[$row->parentEmail] = [
                    'fingerprint' => $fingerprint,
                    'row' => $row->rowNumber,
                ];
                continue;
            }

            if ($seenByEmail[$row->parentEmail]['fingerprint'] !== $fingerprint) {
                $errors[] = new UsersImportRowError(
                    $row->rowNumber,
                    'Email родителя',
                    'Данные родителя не совпадают с строкой ' . $seenByEmail[$row->parentEmail]['row'] . ' при одинаковом email родителя.'
                );
            }
        }
    }

    /**
     * @param list<UsersImportRow> $rows
     * @param list<UsersImportRowError> $errors
     */
    private function validateCrossRowStudentEmailConsistency(array $rows, array &$errors): void
    {
        $seen = [];

        foreach ($rows as $row) {
            if ($row->studentEmail === null) {
                continue;
            }

            if (isset($seen[$row->studentEmail])) {
                $errors[] = new UsersImportRowError(
                    $row->rowNumber,
                    'Email ученика',
                    'Email ученика повторяется в файле (строка ' . $seen[$row->studentEmail] . ').'
                );
                continue;
            }

            $seen[$row->studentEmail] = $row->rowNumber;
        }
    }

    /**
     * @return list<UsersImportRowError>
     */
    private function validateRow(
        UsersImportRow $row,
        int $partnerId,
        int $studentRoleId,
        Collection $legalEntities,
        Collection $teams,
    ): array {
        $errors = [];

        if ($row->studentLastname === '') {
            $errors[] = new UsersImportRowError($row->rowNumber, 'Фамилия ученика', 'Пожалуйста, укажите фамилию.');
        } elseif (mb_strlen($row->studentLastname) > 25) {
            $errors[] = new UsersImportRowError($row->rowNumber, 'Фамилия ученика', 'Фамилия не должна превышать 25 символов.');
        }

        if ($row->studentName === '') {
            $errors[] = new UsersImportRowError($row->rowNumber, 'Имя ученика', 'Пожалуйста, укажите имя.');
        } elseif (mb_strlen($row->studentName) > 25) {
            $errors[] = new UsersImportRowError($row->rowNumber, 'Имя ученика', 'Имя не должно превышать 25 символов.');
        }

        if ($row->legalEntityTitle === '') {
            if ($row->teamTitle !== '') {
                $errors[] = new UsersImportRowError($row->rowNumber, 'Юр. лицо', 'Укажите юр. лицо для создания или привязки группы.');
            }
        } else {
            $legalEntityMatches = $legalEntities->filter(
                fn (PartnerLegalEntity $entity) => mb_strtolower($entity->displayTitle()) === mb_strtolower($row->legalEntityTitle)
            );

            if ($legalEntityMatches->count() === 0) {
                $errors[] = new UsersImportRowError(
                    $row->rowNumber,
                    'Юр. лицо',
                    'Юр. лицо «' . $row->legalEntityTitle . '» не найдено среди активных юр. лиц организации.'
                );
            } elseif ($legalEntityMatches->count() > 1) {
                $errors[] = new UsersImportRowError(
                    $row->rowNumber,
                    'Юр. лицо',
                    'Найдено несколько юр. лиц с наименованием «' . $row->legalEntityTitle . '». Уточните справочник.'
                );
            }
        }

        if ($row->studentEmail !== null && ! filter_var($row->studentEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = new UsersImportRowError($row->rowNumber, 'Email ученика', 'Введите корректный адрес электронной почты.');
        }

        if ($row->studentPhone !== null && ! preg_match('/^\+7\d{10}$/', $row->studentPhone)) {
            $errors[] = new UsersImportRowError(
                $row->rowNumber,
                'Телефон ученика',
                'Поле «Телефон ученика» должно быть российским номером в формате +7XXXXXXXXXX.'
            );
        }

        if ($row->birthdayInvalid) {
            $errors[] = new UsersImportRowError(
                $row->rowNumber,
                'Дата рождения',
                'Некорректная дата рождения. Используйте формат ДД.ММ.ГГГГ.'
            );
        }

        if ($row->parentEmail !== null && ! filter_var($row->parentEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = new UsersImportRowError($row->rowNumber, 'Email родителя', 'Введите корректный email родителя.');
        }

        if ($row->teamTitle !== '') {
            $activeTeam = $teams->first(
                fn (Team $team) => mb_strtolower((string) $team->title) === mb_strtolower($row->teamTitle)
            );

            if ($activeTeam === null) {
                $deletedTeam = Team::withTrashed()
                    ->where('partner_id', $partnerId)
                    ->whereRaw('LOWER(title) = ?', [mb_strtolower($row->teamTitle)])
                    ->whereNotNull('deleted_at')
                    ->exists();

                if ($deletedTeam) {
                    $errors[] = new UsersImportRowError(
                        $row->rowNumber,
                        'Группа',
                        'Группа «' . $row->teamTitle . '» была удалена. Восстановите её вручную или укажите другое название.'
                    );
                }
            }
        }

        if ($row->studentEmail !== null) {
            $existing = User::query()
                ->where('email', $row->studentEmail)
                ->first();

            if ($existing !== null) {
                if ((int) $existing->partner_id !== $partnerId) {
                    $errors[] = new UsersImportRowError(
                        $row->rowNumber,
                        'Email ученика',
                        'Этот email уже зарегистрирован в другой организации.'
                    );
                } elseif ((int) $existing->role_id !== $studentRoleId) {
                    $errors[] = new UsersImportRowError(
                        $row->rowNumber,
                        'Email ученика',
                        'Этот email уже занят пользователем, который не является учеником.'
                    );
                }
            }
        }

        if ($row->hasParentData() && $row->parentEmail !== null) {
            $existingParent = \App\Models\ParentProfile::query()
                ->where('partner_id', $partnerId)
                ->whereRaw('LOWER(email) = ?', [$row->parentEmail])
                ->first();

            if ($existingParent !== null && ! $this->parentMatchesExisting($existingParent, $row)) {
                $errors[] = new UsersImportRowError(
                    $row->rowNumber,
                    'Email родителя',
                    'Родитель с таким email уже существует, но данные в файле не совпадают со справочником.'
                );
            }
        }

        return $errors;
    }

    private function parentMatchesExisting(\App\Models\ParentProfile $parent, UsersImportRow $row): bool
    {
        $compare = static fn (?string $a, ?string $b): bool => ($a ?? '') === ($b ?? '');

        return $compare($parent->lastname, $row->parentLastname)
            && $compare($parent->firstname, $row->parentFirstname)
            && $compare($parent->middlename, $row->parentMiddlename)
            && $compare($parent->phone, $row->parentPhone)
            && $compare($parent->email !== null ? mb_strtolower((string) $parent->email) : null, $row->parentEmail);
    }

    private function resolveStudentMode(string $email, int $partnerId, int $studentRoleId): string
    {
        $existing = User::query()->where('email', $email)->first();

        if ($existing === null) {
            return 'create';
        }

        if ((int) $existing->partner_id !== $partnerId || (int) $existing->role_id !== $studentRoleId) {
            return 'error';
        }

        return 'update';
    }

    /**
     * @return Collection<int, PartnerLegalEntity>
     */
    private function resolveLegalEntities(int $partnerId): Collection
    {
        return PartnerLegalEntity::query()
            ->where('partner_id', $partnerId)
            ->active()
            ->get();
    }

    /**
     * @return Collection<int, Team>
     */
    private function resolveTeams(int $partnerId): Collection
    {
        return Team::query()
            ->where('partner_id', $partnerId)
            ->get();
    }
}
