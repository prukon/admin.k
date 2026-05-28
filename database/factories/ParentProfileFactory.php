<?php

namespace Database\Factories;

use App\Models\ParentProfile;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentProfile>
 */
class ParentProfileFactory extends Factory
{
    protected $model = ParentProfile::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'lastname' => fake()->lastName(),
            'firstname' => fake()->firstName(),
            'middlename' => null,
            'phone' => null,
            'email' => null,
        ];
    }
}
