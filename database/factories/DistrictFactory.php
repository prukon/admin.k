<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\District>
 */
class DistrictFactory extends Factory
{
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'name' => 'Район ' . $this->faker->numberBetween(100000, 999999999),
            'is_enabled' => true,
            'sort_order' => 0,
        ];
    }

    public function forPartner(int $partnerId): static
    {
        return $this->state(fn () => ['partner_id' => $partnerId]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
