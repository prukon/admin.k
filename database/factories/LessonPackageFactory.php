<?php

namespace Database\Factories;

use App\Models\LessonPackage;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonPackage>
 */
class LessonPackageFactory extends Factory
{
    protected $model = LessonPackage::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'name' => 'Абонемент ' . $this->faker->words(2, true),
            'schedule_type' => $this->faker->randomElement(['fixed', 'flexible', 'no_schedule']),
            'duration_days' => $this->faker->numberBetween(30, 120),
            'lessons_count' => $this->faker->numberBetween(4, 16),
            'price_cents' => $this->faker->numberBetween(300000, 1500000),
            'freeze_enabled' => false,
            'freeze_days' => 0,
            'is_active' => true,
        ];
    }

    public function forPartner(int $partnerId): static
    {
        return $this->state(fn () => ['partner_id' => $partnerId]);
    }

    public function flexible(int $lessons = 8, int $days = 90): static
    {
        return $this->state(fn () => [
            'schedule_type' => 'flexible',
            'duration_days' => $days,
            'lessons_count' => $lessons,
            'freeze_enabled' => $this->faker->boolean(30),
            'freeze_days' => 7,
        ]);
    }

    public function fixed(int $lessons = 8, int $days = 90): static
    {
        return $this->state(fn () => [
            'schedule_type' => 'fixed',
            'duration_days' => $days,
            'lessons_count' => $lessons,
            'freeze_enabled' => $this->faker->boolean(20),
            'freeze_days' => 14,
        ]);
    }

    public function singleLesson(): static
    {
        return $this->state(fn () => [
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'freeze_enabled' => false,
            'freeze_days' => 0,
        ]);
    }
}
