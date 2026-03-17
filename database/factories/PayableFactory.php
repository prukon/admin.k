<?php

namespace Database\Factories;

use App\Models\Payable;
use App\Models\Partner;
use App\Models\User;
use App\Models\PaymentIntent;
use App\Models\Payment;
use App\Models\UserPrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PayableFactory extends Factory
{
    protected $model = Payable::class;

    public function definition(): array
    {
        $partnerId = \App\Models\Partner::inRandomOrder()->value('id');

        if (!$partnerId) {
            $partnerId = \App\Models\Partner::factory()->create()->id;
        }

        $userId = \App\Models\User::where('partner_id', $partnerId)
            ->inRandomOrder()
            ->value('id');

        if (!$userId) {
            $userId = \App\Models\User::factory()->create([
                'partner_id' => $partnerId,
            ])->id;
        }

        $month = Carbon::now()
            ->subMonths(rand(0, 6))
            ->startOfMonth()
            ->format('Y-m-01');

        // ✅ только целые суммы
        $amount = $this->faker->numberBetween(500, 10000);

        return [
            'partner_id' => $partnerId,
            'user_id'    => $userId,
            'type'       => 'monthly_fee',
            'amount'     => $amount,   // decimal(15,2) в БД, но значение будет вида 1500.00
            'currency'   => 'RUB',
            'status'     => 'pending',
            'month'      => $month,
            'meta'       => null,
            'paid_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    public function clubFee(): static
    {
        return $this->state(fn () => [
            'type'  => 'club_fee',
            'month' => null,
        ]);
    }

    public function uniform(): static
    {
        return $this->state(fn () => [
            'type'  => 'uniform',
            'month' => null,
        ]);
    }

    public function camp(): static
    {
        return $this->state(fn () => [
            'type'  => 'camp',
            'month' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status'  => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * ГЛАВНЫЙ СЦЕНАРИЙ: месячный платёж с полной цепочкой.
     */
    public function paidMonthlyWithAllRelations(): static
    {
        return $this
            ->state(function (array $attributes) {
                $paidAt = now();

                $month = $attributes['month']
                    ?? $paidAt->copy()->startOfMonth()->format('Y-m-01');

                return [
                    'type'    => 'monthly_fee',
                    'status'  => 'paid',
                    'month'   => $month,
                    'paid_at' => $attributes['paid_at'] ?? $paidAt,
                ];
            })
            ->afterCreating(function (Payable $payable) {
                // 1) payment_intents
                $intent = \App\Models\PaymentIntent::factory()
                    ->forPayable($payable)
                    ->paid()
                    ->create([
                        'partner_id' => $payable->partner_id,
                        'user_id'    => $payable->user_id,
                        'out_sum'    => $payable->amount,
                        'paid_at'    => $payable->paid_at,
                    ]);

                // 2) users_prices
                \App\Models\UserPrice::factory()
                    ->forUserAndMonth(
                        $payable->user_id,
                        $payable->month,                 // YYYY-MM-01
                        (float) $payable->amount,
                        true
                    )
                    ->create();

                // 3) payments (финансовый факт)
                \App\Models\Payment::factory()
                    ->fromPayableAndIntent($payable, $intent)
                    ->create();
            });
    }
}