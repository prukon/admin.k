<?php

namespace Database\Seeders;

use App\Models\ParentProfile;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DevUsersSeeder extends Seeder
{
    use GuardsDevSeedData;

    /** Доля учеников с 2–3 группами (при наличии групп у партнёра). */
    private const MULTI_TEAM_SHARE = 0.25;

    private const MULTI_TEAM_MIN = 2;

    private const MULTI_TEAM_MAX = 3;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $partnerIds = Partner::query()->pluck('id')->all();

        if ($partnerIds === []) {
            return;
        }

        $userRoleId = Role::query()->where('name', 'user')->value('id');
        $sync = app(TeamUserSyncService::class);

        $this->assignPartnerTeam(
            User::query()
                ->whereNotNull('partner_id')
                ->when($userRoleId, fn ($q) => $q->where('role_id', $userRoleId))
                ->get(),
        );

        User::factory()
            ->count(200)
            ->create([
                'is_enabled' => 1,
            ])
            ->each(function (User $user) use ($partnerIds, $sync) {
                $partnerId = (int) $partnerIds[array_rand($partnerIds)];
                $user->partner_id = $partnerId;

                $teamId = Team::query()
                    ->where('partner_id', $partnerId)
                    ->inRandomOrder()
                    ->value('id');

                $user->team_id = $teamId ? (int) $teamId : null;
                $user->save();

                // created() мог синхронизировать группу другого партнёра из factory definition.
                // detachAllTeamsForStudent чистит только текущий partner_id — сбрасываем весь pivot ученика.
                DB::table('team_user')->where('user_id', $user->id)->delete();

                if ($user->team_id) {
                    $sync->attachTeamForStudent($user, (int) $user->team_id);
                }
            });

        $this->seedParentsForStudentsWithoutParent($userRoleId);
        $this->attachExtraTeamsForShareOfStudents($userRoleId);
    }

    /**
     * ~25% учеников получают 2–3 группы того же партнёра (attach, без снятия текущих).
     */
    private function attachExtraTeamsForShareOfStudents(?int $userRoleId): void
    {
        $students = User::query()
            ->when($userRoleId, fn ($q) => $q->where('role_id', $userRoleId))
            ->whereNotNull('partner_id')
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $sync = app(TeamUserSyncService::class);
        $targetCount = (int) max(1, round($students->count() * self::MULTI_TEAM_SHARE));
        $candidates = $students->shuffle()->take($targetCount);

        $teamsByPartner = Team::query()
            ->whereIn('partner_id', $students->pluck('partner_id')->unique()->all())
            ->get(['id', 'partner_id'])
            ->groupBy('partner_id')
            ->map(fn (Collection $rows) => $rows->pluck('id')->map(fn ($id) => (int) $id)->values()->all());

        foreach ($candidates as $user) {
            $partnerId = (int) $user->partner_id;
            $partnerTeamIds = $teamsByPartner->get($partnerId, []);

            if (count($partnerTeamIds) < self::MULTI_TEAM_MIN) {
                continue;
            }

            $current = $sync->teamIdsForStudent($user);

            if ($current === [] && $user->team_id) {
                $sync->attachTeamForStudent($user, (int) $user->team_id);
                $current = $sync->teamIdsForStudent($user);
            }

            $desiredTotal = random_int(
                self::MULTI_TEAM_MIN,
                min(self::MULTI_TEAM_MAX, count($partnerTeamIds))
            );

            $needed = $desiredTotal - count($current);
            if ($needed <= 0) {
                continue;
            }

            $available = array_values(array_diff($partnerTeamIds, $current));
            shuffle($available);

            foreach (array_slice($available, 0, $needed) as $extraTeamId) {
                $sync->attachTeamForStudent($user, $extraTeamId);
            }
        }
    }

    /**
     * Родитель у ~50% учеников без parent_id; среди них часть — братья (2–3 ребёнка на одного parents).
     */
    private function seedParentsForStudentsWithoutParent(?int $userRoleId): void
    {
        $students = User::query()
            ->when($userRoleId, fn ($q) => $q->where('role_id', $userRoleId))
            ->whereNotNull('partner_id')
            ->whereNull('parent_id')
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        foreach ($students->groupBy('partner_id') as $partnerStudents) {
            $this->seedParentsForPartnerStudents($partnerStudents->values());
        }
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function seedParentsForPartnerStudents(Collection $students): void
    {
        $shuffled = $students->shuffle()->values();
        $withParentCount = (int) ceil($shuffled->count() / 2);

        if ($withParentCount === 0) {
            return;
        }

        $withParent = $shuffled->take($withParentCount);

        $familyTarget = (int) round($withParent->count() * 0.5);
        $familyPool = $withParent->take($familyTarget);
        $soloPool = $withParent->slice($familyPool->count())->values();

        $index = 0;
        while ($index < $familyPool->count()) {
            $remaining = $familyPool->count() - $index;

            if ($remaining < 2) {
                $soloPool = $soloPool->merge($familyPool->slice($index));
                break;
            }

            $familySize = random_int(2, min(3, $remaining));

            $siblings = $familyPool->slice($index, $familySize);
            $this->attachSharedParentToUsers($siblings);
            $index += $familySize;
        }

        foreach ($soloPool as $user) {
            $this->attachRandomParentProfile($user);
            $user->save();
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function attachSharedParentToUsers(Collection $users): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $partnerId = (int) $users->first()->partner_id;
        $names = $this->randomParentNameFields();

        $parent = ParentProfile::query()->create([
            'partner_id' => $partnerId,
            'lastname'   => $names['lastname'],
            'firstname'  => $names['firstname'],
            'middlename' => $names['middlename'],
            'email'      => fake()->boolean(70) ? fake()->safeEmail() : null,
        ]);

        foreach ($users as $user) {
            $user->parent_id = $parent->id;
            $user->save();
        }
    }

    private function attachRandomParentProfile(User $user): void
    {
        if (!$user->partner_id || $user->parent_id) {
            return;
        }

        $names = $this->randomParentNameFields();
        $parent = ParentProfile::query()->create([
            'partner_id' => (int) $user->partner_id,
            'lastname'   => $names['lastname'],
            'firstname'  => $names['firstname'],
            'middlename' => $names['middlename'],
            'email'      => fake()->boolean(70) ? fake()->safeEmail() : null,
        ]);

        $user->parent_id = $parent->id;
    }

    /**
     * @return array{lastname: string, firstname: string, middlename: ?string}
     */
    private function randomParentNameFields(): array
    {
        $faker = fake();

        $middlename = $faker->boolean(75)
            ? ($faker->boolean()
                ? $faker->middleNameMale()
                : $faker->middleNameFemale())
            : null;

        return [
            'lastname'   => $faker->lastName(),
            'firstname'  => $faker->firstName(),
            'middlename' => $middlename,
        ];
    }

    private function assignPartnerTeam(iterable $users): void
    {
        foreach ($users as $user) {
            $partnerId = (int) $user->partner_id;

            if (! $user->team_id) {
                $teamId = Team::query()
                    ->where('partner_id', $partnerId)
                    ->inRandomOrder()
                    ->value('id');

                if ($teamId) {
                    $user->team_id = (int) $teamId;
                }
            }

            $user->save();

            app(TeamUserSyncService::class)->syncLegacyTeamColumnToPivot($user);
        }
    }
}
