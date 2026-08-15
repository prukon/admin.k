<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\TrainerType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainerType>
 */
class TrainerTypeFactory extends Factory
{
    protected $model = TrainerType::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'name' => 'Тип ' . $this->faker->unique()->numberBetween(100000, 999999999),
            'sort_order' => 10,
            'is_enabled' => true,
            'is_system' => false,
            'rate_per_training_cents' => 0,
            'base_premium_cents' => 0,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => [
            'name' => TrainerType::SYSTEM_DEFAULT_NAME,
            'sort_order' => 0,
            'is_enabled' => true,
            'is_system' => true,
        ]);
    }
}
