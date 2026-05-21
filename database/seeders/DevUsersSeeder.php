<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DevUsersSeeder extends Seeder
{
    use GuardsDevSeedData;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $partnerIds = Partner::query()->pluck('id')->all();

        if ($partnerIds === []) {
            return;
        }

        /** @var Collection<int, Collection<int, Location>> $locationsByPartner */
        $locationsByPartner = Location::query()
            ->whereIn('partner_id', $partnerIds)
            ->where('is_enabled', true)
            ->get()
            ->groupBy('partner_id');

        $userRoleId = Role::query()->where('name', 'user')->value('id');

        $this->fillParentNamesForStudentsWithoutParent($userRoleId);

        $this->assignPartnerTeamAndLocation(
            User::query()
                ->whereNull('location_id')
                ->whereNotNull('partner_id')
                ->when($userRoleId, fn ($q) => $q->where('role_id', $userRoleId))
                ->get(),
            $locationsByPartner,
        );

        User::factory()
            ->count(200)
            ->create([
                'is_enabled' => 1,
            ])
            ->each(function (User $user) use ($partnerIds, $locationsByPartner) {
                $partnerId = (int) $partnerIds[array_rand($partnerIds)];
                $user->partner_id = $partnerId;

                $partnerLocations = $locationsByPartner->get($partnerId);
                if ($partnerLocations !== null && $partnerLocations->isNotEmpty()) {
                    $user->location_id = (int) $partnerLocations->random()->id;
                }

                $teamId = Team::query()
                    ->where('partner_id', $partnerId)
                    ->inRandomOrder()
                    ->value('id');

                if ($teamId) {
                    $user->team_id = (int) $teamId;
                }

                $user->fill($this->randomParentNameFields());
                $user->save();
            });
    }

    private function fillParentNamesForStudentsWithoutParent(?int $userRoleId): void
    {
        User::query()
            ->when($userRoleId, fn ($q) => $q->where('role_id', $userRoleId))
            ->whereNull('parent_lastname')
            ->orderBy('id')
            ->each(function (User $user) {
                $user->fill($this->randomParentNameFields());
                $user->save();
            });
    }

    /**
     * @return array{parent_lastname: string, parent_firstname: string, parent_middlename: ?string}
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
            'parent_lastname' => $faker->lastName(),
            'parent_firstname' => $faker->firstName(),
            'parent_middlename' => $middlename,
        ];
    }

    /**
     * @param  Collection<int, Collection<int, Location>>  $locationsByPartner
     */
    private function assignPartnerTeamAndLocation(
        iterable $users,
        Collection $locationsByPartner,
    ): void {
        foreach ($users as $user) {
            $partnerId = (int) $user->partner_id;

            $partnerLocations = $locationsByPartner->get($partnerId);
            if ($partnerLocations !== null && $partnerLocations->isNotEmpty()) {
                $user->location_id = (int) $partnerLocations->random()->id;
            }

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
        }
    }
}
