<?php

namespace App\Services\Users\Import;

use App\Enums\AuditEvent;
use App\Models\ParentProfile;
use App\Models\PartnerLegalEntity;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\TeamService;
use App\Services\TeamUserSyncService;
use App\Services\UserService;
use App\Services\Users\StudentParentSyncService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class UsersImportCommitService
{
    public function __construct(
        private readonly UserService $userService,
        private readonly TeamService $teamService,
        private readonly TeamUserSyncService $teamUserSync,
        private readonly StudentParentSyncService $studentParentSync,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param list<UsersImportRow> $rows
     * @return array{created: int, updated: int}
     */
    public function commit(array $rows, int $partnerId, int $authorId): array
    {
        $studentRoleId = (int) (Role::query()->where('name', 'user')->value('id') ?? 0);
        if ($studentRoleId <= 0) {
            throw new \RuntimeException('Роль ученика не найдена.');
        }

        $legalEntitiesByTitle = PartnerLegalEntity::query()
            ->where('partner_id', $partnerId)
            ->active()
            ->get()
            ->groupBy(fn (PartnerLegalEntity $entity) => mb_strtolower($entity->displayTitle()));

        $teamCache = [];
        $parentCache = [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use (
            $rows,
            $partnerId,
            $authorId,
            $studentRoleId,
            $legalEntitiesByTitle,
            &$teamCache,
            &$parentCache,
            &$created,
            &$updated,
        ) {
            foreach ($rows as $row) {
                $teamId = $this->resolveTeamId(
                    $row,
                    $partnerId,
                    $legalEntitiesByTitle,
                    $teamCache,
                    $authorId,
                );
                $teamIds = $teamId !== null ? [$teamId] : [];

                $parentPayload = $this->buildParentPayload($row, $partnerId, $parentCache);

                if ($row->mode === 'update' && $row->studentEmail !== null) {
                    $user = User::query()
                        ->where('partner_id', $partnerId)
                        ->where('role_id', $studentRoleId)
                        ->where('email', $row->studentEmail)
                        ->firstOrFail();

                    $oldTeamLabel = $this->teamUserSync->teamTitlesLabel($user) ?: '-';
                    $oldParentLabel = $user->parent_full_name ?: '-';

                    $user->update([
                        'name' => $row->studentName,
                        'lastname' => $row->studentLastname,
                        'email' => $row->studentEmail,
                        'phone' => $row->studentPhone,
                        'birthday' => $row->birthday,
                        'is_enabled' => $row->isEnabled,
                    ]);

                    if ($row->hasParentData()) {
                        $this->studentParentSync->syncForStudent($user, $partnerId, $parentPayload);
                    } else {
                        $user->parent_id = null;
                        $user->save();
                    }

                    $this->rememberParentCache($row, $user, $parentCache);

                    $this->teamUserSync->syncTeamsForStudent($user, $teamIds);

                    $user->refresh();
                    $user->load('parentProfile', 'teams');

                    $this->auditLogger->record(
                        AuditEvent::UserUpdated,
                        AuditContext::make($this->buildUpdateLogDescription($user, $oldTeamLabel, $oldParentLabel))
                            ->withUser($user)
                            ->withTarget($user, $user->full_name ?: "user#{$user->id}")
                            ->withAuthorId($authorId)
                            ->withPartnerId($partnerId)
                    );

                    $updated++;
                    continue;
                }

                $data = [
                    'name' => $row->studentName,
                    'lastname' => $row->studentLastname,
                    'email' => $row->studentEmail,
                    'phone' => $row->studentPhone,
                    'birthday' => $row->birthday,
                    'is_enabled' => $row->isEnabled,
                    'role_id' => $studentRoleId,
                    'partner_id' => $partnerId,
                    'team_ids' => $teamIds,
                ];

                $data = array_merge($data, $parentPayload);

                $user = $this->userService->store($data);
                $user->load('teams', 'parentProfile');

                $this->rememberParentCache($row, $user, $parentCache);

                $teamTitleForLog = $this->teamUserSync->teamTitlesLabel($user) ?: '-';
                $role = Role::find($studentRoleId);
                $roleLabel = $role->label ?? $role->name ?? '-';

                $this->auditLogger->record(
                    AuditEvent::UserCreated,
                    AuditContext::make(sprintf(
                        "Импорт Excel\nИмя: %s\nД.р: %s\nГруппа: %s\nEmail: %s\nАктивен: %s\nРоль: %s",
                        $user->full_name ?: "user#{$user->id}",
                        $row->birthday ? Carbon::parse($row->birthday)->format('d.m.Y') : '-',
                        $teamTitleForLog,
                        $user->email ?? '-',
                        $row->isEnabled ? 'Да' : 'Нет',
                        $roleLabel,
                    ))
                        ->withUser($user)
                        ->withTarget($user, $user->full_name ?: "user#{$user->id}")
                        ->withAuthorId($authorId)
                        ->withPartnerId($partnerId)
                );

                $created++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @param Collection<string, Collection<int, PartnerLegalEntity>> $legalEntitiesByTitle
     * @param array<string, int> $teamCache
     */
    private function resolveTeamId(
        UsersImportRow $row,
        int $partnerId,
        Collection $legalEntitiesByTitle,
        array &$teamCache,
        int $authorId,
    ): ?int {
        if ($row->teamTitle === '') {
            return null;
        }

        $cacheKey = mb_strtolower($row->teamTitle);
        if (isset($teamCache[$cacheKey])) {
            return $teamCache[$cacheKey];
        }

        $existing = Team::query()
            ->where('partner_id', $partnerId)
            ->whereRaw('LOWER(title) = ?', [$cacheKey])
            ->first();

        if ($existing) {
            $teamCache[$cacheKey] = (int) $existing->id;

            return $teamCache[$cacheKey];
        }

        $legalEntityCollection = $legalEntitiesByTitle->get(mb_strtolower($row->legalEntityTitle), collect());
        $legalEntity = $legalEntityCollection->first();

        if (! $legalEntity instanceof PartnerLegalEntity) {
            throw new \RuntimeException('Юр. лицо не найдено для строки ' . $row->rowNumber);
        }

        $team = $this->teamService->storeWithLogging([
            'title' => $row->teamTitle,
            'legal_entity_id' => $legalEntity->id,
            'is_enabled' => true,
        ], $authorId);

        $teamCache[$cacheKey] = (int) $team->id;

        return $teamCache[$cacheKey];
    }

    /**
     * @param array<string, int> $parentCache
     * @return array<string, mixed>
     */
    private function buildParentPayload(UsersImportRow $row, int $partnerId, array &$parentCache): array
    {
        if (! $row->hasParentData()) {
            return [
                'parent_lastname' => null,
                'parent_firstname' => null,
                'parent_middlename' => null,
                'parent_phone' => null,
                'parent_email' => null,
            ];
        }

        if ($row->parentEmail !== null) {
            $cacheKey = $row->parentEmail;
            if (isset($parentCache[$cacheKey])) {
                return ['parent_id' => $parentCache[$cacheKey]];
            }

            $existing = ParentProfile::query()
                ->where('partner_id', $partnerId)
                ->whereRaw('LOWER(email) = ?', [$row->parentEmail])
                ->first();

            if ($existing) {
                $parentCache[$cacheKey] = (int) $existing->id;

                return ['parent_id' => $parentCache[$cacheKey]];
            }

            return [
                'parent_lastname' => $row->parentLastname,
                'parent_firstname' => $row->parentFirstname,
                'parent_middlename' => $row->parentMiddlename,
                'parent_phone' => $row->parentPhone,
                'parent_email' => $row->parentEmail,
            ];
        }

        return [
            'parent_lastname' => $row->parentLastname,
            'parent_firstname' => $row->parentFirstname,
            'parent_middlename' => $row->parentMiddlename,
            'parent_phone' => $row->parentPhone,
            'parent_email' => null,
        ];
    }

    /**
     * @param array<string, int> $parentCache
     */
    private function rememberParentCache(UsersImportRow $row, User $user, array &$parentCache): void
    {
        if ($row->parentEmail === null || ! $user->parent_id) {
            return;
        }

        $parentCache[$row->parentEmail] = (int) $user->parent_id;
    }

    private function buildUpdateLogDescription(User $user, string $oldTeamLabel, string $oldParentLabel): string
    {
        $user->loadMissing('parentProfile', 'teams');

        $newTeamLabel = $this->teamUserSync->teamTitlesLabel($user) ?: '-';
        $newParentLabel = $user->parent_full_name ?: '-';

        return sprintf(
            "Импорт Excel\nИмя: %s → %s\nEmail: %s\nГруппы: %s → %s\nРодитель: %s → %s",
            $user->full_name,
            $user->full_name,
            $user->email ?? '-',
            $oldTeamLabel,
            $newTeamLabel,
            $oldParentLabel,
            $newParentLabel,
        );
    }
}
