<?php

namespace Database\Factories;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLessonPackage>
 */
class UserLessonPackageFactory extends Factory
{
    protected $model = UserLessonPackage::class;

    public function definition(): array
    {
        $package = LessonPackage::factory()->create();
        $lessons = (int) $package->lessons_count;

        return [
            'user_id' => User::factory(),
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount' => round($package->price_cents / 100, 2),
            'is_paid' => false,
            'created_by' => null,
        ];
    }

    public function forUserAndPackage(User $user, LessonPackage $package): static
    {
        $lessons = (int) $package->lessons_count;

        return $this->state(fn () => [
            'user_id' => $user->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount' => round($package->price_cents / 100, 2),
        ]);
    }
}
