<?php

namespace App\Services\Users;

use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Support\Collection;

class FamilyStudentContextService
{
    public const SESSION_KEY = 'active_student_user_id';

    /** @var array<int, Collection<int, User>> */
    private array $accessibleStudentsCache = [];

    /**
     * Ученики, доступные текущему пользователю в семейном кабинете.
     *
     * Вход только под учёткой ученика (role=user). Список — все активные ученики
     * того же партнёра с тем же users.parent_id; текущий пользователь всегда включён.
     *
     * @return Collection<int, User>
     */
    public function accessibleStudents(User $actor): Collection
    {
        $cacheKey = (int) $actor->id;

        if (isset($this->accessibleStudentsCache[$cacheKey])) {
            return $this->accessibleStudentsCache[$cacheKey];
        }

        $actor->loadMissing('role');

        if ($actor->role?->name !== 'user') {
            return $this->accessibleStudentsCache[$cacheKey] = collect();
        }

        if (!$actor->parent_id) {
            return $this->accessibleStudentsCache[$cacheKey] = collect([$actor]);
        }

        $students = User::query()
            ->with('role')
            ->where('partner_id', $actor->partner_id)
            ->where('parent_id', $actor->parent_id)
            ->where('is_enabled', true)
            ->whereHas('role', static fn ($q) => $q->where('name', 'user'))
            ->orderBy('lastname')
            ->orderBy('name')
            ->get();

        if (!$students->contains('id', $actor->id)) {
            $students->prepend($actor);
        }

        return $this->accessibleStudentsCache[$cacheKey] = $students->unique('id')->values();
    }

    public function shouldShowSwitcher(User $actor): bool
    {
        return $this->accessibleStudents($actor)->count() > 1;
    }

    /**
     * Имя и почта для блока user-panel в сайдбаре.
     * При семейном кабинете (несколько детей) — из справочника parents.
     *
     * @return array{name: string, email: string}
     */
    public function sidebarPanelIdentity(User $actor): array
    {
        if ($this->shouldShowSwitcher($actor) && $actor->parent_id) {
            $actor->loadMissing('parentProfile');

            $profile = $actor->parentProfile;
            if ($profile instanceof ParentProfile) {
                return [
                    'name'  => $profile->full_name !== '' ? $profile->full_name : (string) ($actor->name ?? ''),
                    'email' => (string) ($profile->email ?? ''),
                ];
            }
        }

        return [
            'name'  => (string) ($actor->name ?? ''),
            'email' => (string) ($actor->email ?? ''),
        ];
    }

    public function activeStudent(?User $actor = null): User
    {
        $actor ??= auth()->user();
        abort_unless($actor instanceof User, 401);

        $accessible = $this->accessibleStudents($actor);
        if ($accessible->isEmpty()) {
            return $actor;
        }

        $requestedId = (int) session(self::SESSION_KEY, 0);
        if ($requestedId > 0) {
            $match = $accessible->firstWhere('id', $requestedId);
            if ($match instanceof User) {
                return $match;
            }
        }

        if ($accessible->contains('id', $actor->id)) {
            return $actor;
        }

        /** @var User $first */
        $first = $accessible->first();

        return $first;
    }

    public function canAccessStudent(User $actor, int $studentUserId): bool
    {
        return $this->accessibleStudents($actor)->contains('id', $studentUserId);
    }

    public function setActiveStudent(User $actor, int $studentUserId): void
    {
        if (!$this->canAccessStudent($actor, $studentUserId)) {
            abort(403, 'Нет доступа к выбранному ученику.');
        }

        session([self::SESSION_KEY => $studentUserId]);
    }
}
