<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Partner;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeamScheduleSlot>
 */
class TeamScheduleSlotFactory extends Factory
{
    public function definition(): array
    {
        $startHour = $this->faker->numberBetween(9, 19);
        $timeStart = str_pad((string) $startHour, 2, '0', STR_PAD_LEFT) . ':00';
        $timeEnd = str_pad((string) ($startHour + 1), 2, '0', STR_PAD_LEFT) . ':00';

        return [
            'partner_id' => Partner::factory(),
            'team_id' => Team::factory(),
            'location_id' => null,
            'weekday' => $this->faker->numberBetween(1, 7),
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'date_start' => now()->toDateString(),
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ];
    }

    public function withLocation(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'location_id' => Location::factory(),
            ];
        });
    }
}

