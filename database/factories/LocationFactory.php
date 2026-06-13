<?php

namespace Database\Factories;

use App\Models\District;
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
            'district_id' => null,
            // Уникальность (partner_id, district_id, name) в БД: узкий диапазон 1–50 давал частые коллизии при двух create() подряд.
            'name' => 'Кабинет ' . $this->faker->numberBetween(100000, 999999999),
            'address' => $this->faker->streetAddress(),
            'description' => null,
            'is_enabled' => true,
        ];
    }

    public function forPartner(int $partnerId): static
    {
        return $this->state(fn () => ['partner_id' => $partnerId]);
    }

    public function forDistrict(District $district): static
    {
        return $this->state(fn () => [
            'partner_id' => $district->partner_id,
            'district_id' => $district->id,
        ]);
    }
}

