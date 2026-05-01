<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'name' => 'Кабинет ' . $this->faker->numberBetween(1, 50),
            'address' => $this->faker->streetAddress(),
            'description' => null,
            'is_enabled' => true,
        ];
    }
}

