<?php

namespace App\Services\Users;

use App\Enums\AuditEvent;
use App\Models\Team;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\InAppNotifications\CabinetTeamAttachedNotifier;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Добавление группы активному ученику из ЛК (только attach, без detach/replace).
 * Кандидаты — группы объектов, к которым уже привязаны текущие группы ученика.
 */
class CabinetTeamAttachService
{
    public function __construct(
        private readonly FamilyStudentContextService $familyContext,
        private readonly TeamUserSyncService $teamUserSync,
        private readonly AuditLogger $auditLogger,
        private readonly CabinetTeamAttachedNotifier $cabinetTeamAttachedNotifier,
    ) {}

    /**
     * Контекст для кнопки/модалки в сайдбаре или null (кнопку не показывать).
     *
     * @return array{
     *     student_id: int,
     *     student_full_name: string,
     *     current_teams_label: string,
     *     locations_label: string,
     *     available_by_location: list<array{location_id: int, location_name: string, teams: list<array{id: int, title: string}>}>
     * }|null
     */
    public function sidebarContext(User $actor): ?array
    {
        $student = $this->resolveEligibleStudent($actor);
        if ($student === null) {
            return null;
        }

        $currentTeams = $this->loadCurrentTeamsWithLocations($student);
        $locationIds = $this->locationIdsFromTeams($currentTeams);
        if ($locationIds === []) {
            return null;
        }

        $availableByLocation = $this->availableTeamsGroupedByLocation(
            (int) $student->partner_id,
            $locationIds,
            $this->teamUserSync->teamIdsForStudent($student)
        );

        return [
            'student_id' => (int) $student->id,
            'student_full_name' => (string) ($student->full_name ?: ($student->name ?? ('Ученик #'.$student->id))),
            'current_teams_label' => $this->teamUserSync->teamTitlesLabel($student) ?: '—',
            'locations_label' => $this->locationsLabelFromTeams($currentTeams),
            'available_by_location' => $availableByLocation,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function attach(User $actor, int $teamId): User
    {
        $student = $this->resolveEligibleStudent($actor);
        if ($student === null) {
            throw new InvalidArgumentException('Нет ученика с группой на объекте для добавления.');
        }

        if (! $this->isTeamAllowedForStudent($student, $teamId)) {
            throw new InvalidArgumentException('Выбранная группа недоступна для добавления.');
        }

        $oldLabel = $this->teamUserSync->teamTitlesLabel($student) ?: '—';

        DB::transaction(function () use ($student, $teamId, $oldLabel, $actor) {
            $this->teamUserSync->attachTeamForStudent($student, $teamId);
            $student->unsetRelation('teams');

            $newLabel = $this->teamUserSync->teamTitlesLabel($student) ?: '—';

            $this->auditLogger->record(
                AuditEvent::UserAccountUpdated,
                AuditContext::make(sprintf(
                    'Добавление группы из ЛК (автор #%d). Группы: %s → %s',
                    (int) $actor->id,
                    $oldLabel,
                    $newLabel
                ))
                    ->withUser($student)
                    ->withAuthorId((int) $actor->id)
                    ->withPartnerId((int) $student->partner_id)
                    ->withTargetReference('App\Models\User', (int) $student->id, $newLabel)
            );
        });

        $student->unsetRelation('teams');

        $team = Team::query()->with('location:id,name')->find($teamId);
        if ($team instanceof Team) {
            $this->cabinetTeamAttachedNotifier->notify($actor, $student, $team);
        }

        return $student;
    }

    public function isTeamAllowedForStudent(User $student, int $teamId): bool
    {
        if ($teamId <= 0 || ! $this->isStudentRole($student)) {
            return false;
        }

        $partnerId = (int) $student->partner_id;
        if ($partnerId <= 0) {
            return false;
        }

        $currentTeams = $this->loadCurrentTeamsWithLocations($student);
        $locationIds = $this->locationIdsFromTeams($currentTeams);
        if ($locationIds === []) {
            return false;
        }

        $currentTeamIds = $this->teamUserSync->teamIdsForStudent($student);
        if (in_array($teamId, $currentTeamIds, true)) {
            return false;
        }

        return Team::query()
            ->where('partner_id', $partnerId)
            ->where('id', $teamId)
            ->where('is_enabled', true)
            ->whereIn('location_id', $locationIds)
            ->exists();
    }

    /**
     * Активный ученик семейного контекста, если у него есть хотя бы одна группа с объектом.
     * Только для актора с ролью user (ЛК ученика / семейный режим).
     */
    public function resolveEligibleStudent(User $actor): ?User
    {
        $actor->loadMissing('role');
        if ($actor->role?->name !== 'user') {
            return null;
        }

        $student = $this->familyContext->activeStudent($actor);

        if (! $this->familyContext->canAccessStudent($actor, (int) $student->id)) {
            return null;
        }

        if ((int) $student->partner_id !== (int) $actor->partner_id) {
            return null;
        }

        if (! $this->isStudentRole($student)) {
            return null;
        }

        $currentTeams = $this->loadCurrentTeamsWithLocations($student);
        if ($this->locationIdsFromTeams($currentTeams) === []) {
            return null;
        }

        return $student;
    }

    /**
     * @return Collection<int, Team>
     */
    private function loadCurrentTeamsWithLocations(User $student): Collection
    {
        return $student->teams()
            ->with('location:id,name')
            ->get();
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return list<int>
     */
    private function locationIdsFromTeams(Collection $teams): array
    {
        return $teams
            ->pluck('location_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Team>  $teams
     */
    private function locationsLabelFromTeams(Collection $teams): string
    {
        $names = $teams
            ->filter(fn (Team $team) => (int) ($team->location_id ?? 0) > 0)
            ->map(function (Team $team) {
                $name = trim((string) ($team->location?->name ?? ''));

                return $name !== '' ? $name : ('Объект #'.$team->location_id);
            })
            ->unique()
            ->sort()
            ->values();

        return $names->isEmpty() ? '—' : $names->implode(', ');
    }

    /**
     * @param  list<int>  $locationIds
     * @param  list<int>  $excludeTeamIds
     * @return list<array{location_id: int, location_name: string, teams: list<array{id: int, title: string}>}>
     */
    private function availableTeamsGroupedByLocation(int $partnerId, array $locationIds, array $excludeTeamIds): array
    {
        $teams = Team::query()
            ->with('location:id,name')
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereIn('location_id', $locationIds)
            ->when($excludeTeamIds !== [], fn ($q) => $q->whereNotIn('id', $excludeTeamIds))
            ->orderBy('location_id')
            ->orderBy('order_by')
            ->orderBy('title')
            ->get(['id', 'title', 'location_id', 'order_by']);

        $grouped = [];
        foreach ($teams as $team) {
            $locationId = (int) $team->location_id;
            if (! isset($grouped[$locationId])) {
                $name = trim((string) ($team->location?->name ?? ''));
                $grouped[$locationId] = [
                    'location_id' => $locationId,
                    'location_name' => $name !== '' ? $name : ('Объект #'.$locationId),
                    'teams' => [],
                ];
            }
            $grouped[$locationId]['teams'][] = [
                'id' => (int) $team->id,
                'title' => (string) $team->title,
            ];
        }

        // Стабильный порядок по названию объекта
        uasort($grouped, static fn (array $a, array $b) => strcmp($a['location_name'], $b['location_name']));

        return array_values($grouped);
    }

    private function isStudentRole(User $user): bool
    {
        $user->loadMissing('role');

        return $user->role?->name === 'user';
    }
}
