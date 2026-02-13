<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\TinkoffCommissionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class TinkoffCommissionRuleFactory extends Factory
{
    protected $model = TinkoffCommissionRule::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'method' => $this->faker->randomElement(['card', 'sbp', 'tpay']),
            'acquiring_percent' => 2.50,
            'acquiring_min_fixed' => 10.00,
            'payout_percent' => 1.20,
            'payout_min_fixed' => 5.00,
            'platform_percent' => 1.00,
            'platform_min_fixed' => 0.00,
            'min_fixed' => 0.00,
            'is_enabled' => 1,
        ];
    }

    public function globalRule(): self
    {
        return $this->state(fn () => [
            'partner_id' => null,
            'method' => null,
        ]);
    }

    public function disabled(): self
    {
        return $this->state(fn () => [
            'is_enabled' => 0,
        ]);
    }
}