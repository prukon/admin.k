<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\Role;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainerProfile>
 */
class TrainerProfileFactory extends Factory
{
    protected $model = TrainerProfile::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'user_id' => User::factory(),
            'description' => null,
            'is_enabled' => true,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (TrainerProfile $profile) {
            $trainerRoleId = Role::query()->where('name', 'trainer')->value('id');

            if ($trainerRoleId && $profile->user_id) {
                User::query()->whereKey($profile->user_id)->update([
                    'role_id' => $trainerRoleId,
                    'partner_id' => $profile->partner_id,
                ]);
            }
        });
    }
}
