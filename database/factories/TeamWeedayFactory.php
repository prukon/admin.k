<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeamWeekday>
 */
class TeamWeedayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => random_int(1, 100),
            'team_id' => random_int(1, 100),
            'weekday_id' => random_int(1,7),
        ];
    }
} 
