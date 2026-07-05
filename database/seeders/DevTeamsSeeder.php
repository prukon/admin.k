<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Team;
use App\Models\Weekday;
use Database\Seeders\Concerns\AssignsTeamDirectoryLinks;
use Database\Seeders\Concerns\AssignsTeamsToLegalEntities;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;

class DevTeamsSeeder extends Seeder
{
    use AssignsTeamDirectoryLinks;
    use AssignsTeamsToLegalEntities;
    use GuardsDevSeedData;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $partnerIds = Partner::pluck('id')->toArray();

        if (empty($partnerIds)) {
            return;
        }

        $remaining = 20;
        $partnerCount = count($partnerIds);
        $teamsPerPartner = (int) ceil($remaining / $partnerCount);

        foreach ($partnerIds as $partnerId) {
            if ($remaining <= 0) {
                break;
            }

            $count = min($teamsPerPartner, $remaining);
            Team::factory()->count($count)->create(['partner_id' => $partnerId]);
            $remaining -= $count;
        }

        $this->assignLegalEntitiesToAllTeams();
        $this->assignSportTypesToAllTeams();

        // Было: $this->attachWeekdaysToTeams();
        $this->attachWeekdaysToTeams();
    }

    // Привязка случайных дней недели к командам.
    protected function attachWeekdaysToTeams(): void
    {
        $teams = Team::all();
        $weekdays = Weekday::all();

        if ($teams->isEmpty() || $weekdays->isEmpty()) {
            return;
        }

        foreach ($teams as $team) {
            $count = min(3, $weekdays->count());

            $weekdayIds = $weekdays
                ->random($count)
                ->pluck('id')
                ->toArray();

            $team->weekdays()->syncWithoutDetaching($weekdayIds);
        }
    }
}