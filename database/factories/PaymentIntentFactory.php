<?php

namespace Database\Factories;

use App\Models\PaymentIntent;
use App\Models\Payable;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    public function definition(): array
    {
        // ✅ целая сумма
        $sum = $this->faker->numberBetween(500, 10000);

        return [
            'partner_id'       => null,
            'user_id'          => null,
            'payable_id'       => null,
            'provider'         => 'robokassa',
            'provider_inv_id'  => $this->faker->unique()->numberBetween(100000, 999999),
            'tbank_payment_id' => null,
            'tbank_order_id'   => null,
            'status'           => 'pending',
            'out_sum'          => $sum,
            'payment_date'     => null,
            'meta'             => null,
            'paid_at'          => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }

    /**
     * Привязка к конкретному payable:
     * подтягиваем partner_id, user_id, сумму.
     */
    public function forPayable(Payable $payable): static
    {
        return $this->state(function (array $attributes) use ($payable) {
            return [
                'payable_id' => $payable->id,
                'partner_id' => $payable->partner_id,
                'user_id'    => $payable->user_id,
                'out_sum'    => $payable->amount,
            ];
        });
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $paidAt = now();

            return [
                'status'   => 'paid',
                'paid_at'  => $attributes['paid_at'] ?? $paidAt,
                'payment_date' => $attributes['payment_date'] ?? $paidAt->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
        ]);
    }
}