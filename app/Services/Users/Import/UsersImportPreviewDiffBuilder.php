<?php

namespace App\Services\Users\Import;

use App\Models\ParentProfile;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Строит field-level diff для preview импорта (режим update).
 * Семантика совпадает с UsersImportCommitService: полная подстановка из строки.
 */
final class UsersImportPreviewDiffBuilder
{
    private const EMPTY_DISPLAY = '—';

    public function __construct(
        private readonly TeamUserSyncService $teamUserSync,
    ) {
    }

    /**
     * @param list<UsersImportRow> $rows
     * @param Collection<string, User> $studentsByEmailLower
     * @param Collection<string, ParentProfile> $parentsByEmailLower
     * @return array{
     *     changes_by_row: array<int, list<UsersImportFieldChange>>,
     *     update_with_changes_count: int,
     *     update_with_clears_count: int
     * }
     */
    public function buildForRows(
        array $rows,
        Collection $studentsByEmailLower,
        Collection $parentsByEmailLower,
    ): array {
        $changesByRow = [];
        $updateWithClears = 0;
        $updateWithChanges = 0;

        foreach ($rows as $row) {
            if ($row->mode !== 'update' || $row->studentEmail === null) {
                continue;
            }

            $user = $studentsByEmailLower->get(mb_strtolower($row->studentEmail));
            if (! $user instanceof User) {
                continue;
            }

            $changes = $this->buildForStudent($user, $row, $parentsByEmailLower);
            $changesByRow[$row->rowNumber] = $changes;

            if ($changes !== []) {
                $updateWithChanges++;
            }

            if ($this->hasClears($changes)) {
                $updateWithClears++;
            }
        }

        return [
            'changes_by_row' => $changesByRow,
            'update_with_changes_count' => $updateWithChanges,
            'update_with_clears_count' => $updateWithClears,
        ];
    }

    /**
     * @param Collection<string, ParentProfile> $parentsByEmailLower
     * @return list<UsersImportFieldChange>
     */
    public function buildForStudent(
        User $user,
        UsersImportRow $row,
        Collection $parentsByEmailLower,
    ): array {
        $changes = [];

        $this->pushScalarChange(
            $changes,
            'student_lastname',
            'Фамилия',
            $this->normalizeNullableString($user->lastname),
            $this->normalizeNullableString($row->studentLastname),
        );

        $this->pushScalarChange(
            $changes,
            'student_name',
            'Имя',
            $this->normalizeNullableString($user->name),
            $this->normalizeNullableString($row->studentName),
        );

        $this->pushScalarChange(
            $changes,
            'student_phone',
            'Телефон',
            $this->normalizeNullableString($user->phone),
            $this->normalizeNullableString($row->studentPhone),
        );

        $currentBirthday = $user->birthday instanceof Carbon
            ? $user->birthday->format('Y-m-d')
            : $this->normalizeNullableString(
                is_string($user->birthday) ? $user->birthday : null
            );
        $this->pushScalarChange(
            $changes,
            'birthday',
            'Дата рождения',
            $currentBirthday,
            $this->normalizeNullableString($row->birthday),
            displayFrom: $this->formatBirthdayDisplay($currentBirthday),
            displayTo: $this->formatBirthdayDisplay($row->birthday),
        );

        $currentEnabled = (bool) (int) $user->is_enabled;
        if ($currentEnabled !== $row->isEnabled) {
            $changes[] = new UsersImportFieldChange(
                field: 'is_enabled',
                label: 'Активен',
                from: $currentEnabled ? 'Да' : 'Нет',
                to: $row->isEnabled ? 'Да' : 'Нет',
                kind: UsersImportFieldChange::KIND_CHANGED,
            );
        }

        $teamChange = $this->buildTeamChange($user, $row);
        if ($teamChange !== null) {
            $changes[] = $teamChange;
        }

        $parentChange = $this->buildParentChange($user, $row, $parentsByEmailLower);
        if ($parentChange !== null) {
            $changes[] = $parentChange;
        }

        return $changes;
    }

    /**
     * @param list<UsersImportFieldChange> $changes
     */
    private function pushScalarChange(
        array &$changes,
        string $field,
        string $label,
        ?string $fromRaw,
        ?string $toRaw,
        ?string $displayFrom = null,
        ?string $displayTo = null,
    ): void {
        $from = $fromRaw !== null && $fromRaw !== '' ? $fromRaw : null;
        $to = $toRaw !== null && $toRaw !== '' ? $toRaw : null;

        if ($from === $to) {
            return;
        }

        $kind = ($to === null && $from !== null)
            ? UsersImportFieldChange::KIND_CLEARED
            : UsersImportFieldChange::KIND_CHANGED;

        $changes[] = new UsersImportFieldChange(
            field: $field,
            label: $label,
            from: $displayFrom ?? ($from ?? self::EMPTY_DISPLAY),
            to: $displayTo ?? ($to ?? self::EMPTY_DISPLAY),
            kind: $kind,
        );
    }

    private function buildTeamChange(User $user, UsersImportRow $row): ?UsersImportFieldChange
    {
        $currentTitles = $this->teamUserSync->teamTitlesCollection($user)
            ->map(static fn (string $title) => trim($title))
            ->filter()
            ->values();

        $currentKeys = $currentTitles
            ->map(static fn (string $title) => mb_strtolower($title))
            ->sort()
            ->values()
            ->all();

        $newTitle = trim($row->teamTitle);
        $newKeys = $newTitle === ''
            ? []
            : [mb_strtolower($newTitle)];

        if ($currentKeys === $newKeys) {
            return null;
        }

        $fromLabel = $currentTitles->implode(', ');
        if ($fromLabel === '') {
            $fromLabel = self::EMPTY_DISPLAY;
        }

        $toLabel = $newTitle !== '' ? $newTitle : self::EMPTY_DISPLAY;
        $kind = ($newTitle === '' && $currentTitles->isNotEmpty())
            ? UsersImportFieldChange::KIND_CLEARED
            : UsersImportFieldChange::KIND_CHANGED;

        return new UsersImportFieldChange(
            field: 'teams',
            label: 'Группы',
            from: $fromLabel,
            to: $toLabel,
            kind: $kind,
        );
    }

    /**
     * @param Collection<string, ParentProfile> $parentsByEmailLower
     */
    private function buildParentChange(
        User $user,
        UsersImportRow $row,
        Collection $parentsByEmailLower,
    ): ?UsersImportFieldChange {
        $currentParentId = $user->parent_id ? (int) $user->parent_id : null;
        $currentLabel = $this->formatExistingParentLabel($user);

        if (! $row->hasParentData()) {
            if ($currentParentId === null) {
                return null;
            }

            return new UsersImportFieldChange(
                field: 'parent',
                label: 'Родитель',
                from: $currentLabel !== '' ? $currentLabel : self::EMPTY_DISPLAY,
                to: self::EMPTY_DISPLAY,
                kind: UsersImportFieldChange::KIND_CLEARED,
            );
        }

        $targetParent = null;
        if ($row->parentEmail !== null) {
            $targetParent = $parentsByEmailLower->get(mb_strtolower($row->parentEmail));
        }

        $targetParentId = $targetParent instanceof ParentProfile ? (int) $targetParent->id : null;

        if ($targetParentId !== null && $currentParentId === $targetParentId) {
            return null;
        }

        $targetLabel = $targetParent instanceof ParentProfile
            ? $this->composeParentLabel(
                $targetParent->lastname,
                $targetParent->firstname,
                $targetParent->middlename,
                $targetParent->email,
                $targetParent->phone,
            )
            : $this->composeParentLabel(
                $row->parentLastname,
                $row->parentFirstname,
                $row->parentMiddlename,
                $row->parentEmail,
                $row->parentPhone,
            );

        return new UsersImportFieldChange(
            field: 'parent',
            label: 'Родитель',
            from: $currentParentId === null
                ? self::EMPTY_DISPLAY
                : ($currentLabel !== '' ? $currentLabel : self::EMPTY_DISPLAY),
            to: $targetLabel,
            kind: UsersImportFieldChange::KIND_CHANGED,
        );
    }

    private function formatExistingParentLabel(User $user): string
    {
        $user->loadMissing('parentProfile');
        $profile = $user->parentProfile;
        if (! $profile instanceof ParentProfile) {
            return trim((string) $user->parent_full_name);
        }

        return $this->composeParentLabel(
            $profile->lastname,
            $profile->firstname,
            $profile->middlename,
            $profile->email,
            $profile->phone,
        );
    }

    private function composeParentLabel(
        ?string $lastname,
        ?string $firstname,
        ?string $middlename,
        ?string $email,
        ?string $phone,
    ): string {
        $name = trim(collect([$lastname, $firstname, $middlename])->filter()->implode(' '));
        $parts = array_values(array_filter([
            $name !== '' ? $name : null,
            $email !== null && $email !== '' ? $email : null,
            $phone !== null && $phone !== '' ? $phone : null,
        ]));

        return $parts !== [] ? implode(' · ', $parts) : self::EMPTY_DISPLAY;
    }

    private function formatBirthdayDisplay(?string $ymd): string
    {
        if ($ymd === null || $ymd === '') {
            return self::EMPTY_DISPLAY;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $ymd)->format('d.m.Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @param list<UsersImportFieldChange> $changes
     */
    private function hasClears(array $changes): bool
    {
        foreach ($changes as $change) {
            if ($change->kind === UsersImportFieldChange::KIND_CLEARED) {
                return true;
            }
        }

        return false;
    }
}
