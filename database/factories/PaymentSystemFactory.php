<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PaymentSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentSystemFactory extends Factory
{
    protected $model = PaymentSystem::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),

            'name' => $this->faker->randomElement([
                'robokassa',
                'tbank',
            ]),

            'settings' => [
                'example_key' => $this->faker->uuid(),
            ],

            'test_mode' => false,
            'is_enabled' => true,
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | States
     |--------------------------------------------------------------------------
     */

    public function robokassa(): self
    {
        return $this->state(function () {
            return [
                'name' => 'robokassa',
                'settings' => [
                    'merchant_login' => 'test_login',
                    'password1' => 'pass1',
                    'password2' => 'pass2',
                    'password3' => 'pass3',
                ],
            ];
        });
    }

    public function tbank(): self
    {
        return $this->state(function () {
            return [
                'name' => 'tbank',
                'settings' => [
                    'terminal_key' => 'term_key',
                    'token_password' => 'token_pass',
                    'e2c_terminal_key' => 'e2c_term',
                    'e2c_token_password' => 'e2c_token',
                ],
            ];
        });
    }

    public function testMode(): self
    {
        return $this->state(fn () => [
            'test_mode' => true,
        ]);
    }

    public function disabled(): self
    {
        return $this->state(fn () => [
            'is_enabled' => false,
        ]);
    }
}