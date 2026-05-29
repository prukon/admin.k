<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SportType>
 */
class SportTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'name' => 'Спорт ' . $this->faker->numberBetween(100000, 999999999),
            'description' => null,
            'sort' => 0,
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
