<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Team;
use App\Models\Weekday;
use Illuminate\Database\Seeder;

class DevTeamsSeeder extends Seeder
{
    public function run(): void
    {
        $partnerIds = Partner::pluck('id')->toArray();

        if (empty($partnerIds)) {
            return;
        }

        // Было: Team::factory()->count(20)->create()->each(...)
        Team::factory()->count(20)->create()->each(function (Team $team) use ($partnerIds) {
            $team->partner_id = $partnerIds[array_rand($partnerIds)];
            $team->save();
        });

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