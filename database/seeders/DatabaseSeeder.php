<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Team;
use App\Models\TeamWeekday;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $weekdays = Weekday::factory(7)->create();
        $teams = Team::factory(20)->create();
        User::factory(200)->create();


        foreach ($teams as $team) {
            $WeekdayId = $weekdays->random(3)->pluck('id');
            $team->weekdays()->attach($WeekdayId);
        }


//       dd($teams);
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
