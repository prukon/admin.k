<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use App\Models\Payable;
use App\Models\PaymentIntent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $operationDate   = $this->faker->dateTimeBetween('-1 year', 'now');
        $operationCarbon = Carbon::instance($operationDate);

        return [
            'user_id'        => null,
            'user_name'      => null,
            'team_title'     => $this->faker->words(2, true),
            'operation_date' => $operationCarbon->format('Y-m-d H:i:s'),
            'payment_month'  => $operationCarbon->copy()->startOfMonth()->format('Y-m-01'),
            'deal_id'        => $this->faker->unique()->uuid(),
            'payment_id'     => $this->faker->uuid(),
            'payment_status' => 'paid',
            // ✅ целая сумма
            'summ'           => $this->faker->numberBetween(500, 10000),
            'payment_number' => $this->faker->numerify('########'),
            'partner_id'     => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ];
    }

    /**
     * Привязка к конкретному пользователю.
     */
    public function forUser(User $user): static
    {
        return $this->state(function (array $attributes) use ($user) {
            $fullName = trim(($user->lastname ?? '') . ' ' . ($user->name ?? ''));

            return [
                'user_id'    => $user->id,
                'user_name'  => $fullName !== '' ? $fullName : ($user->email ?? 'Без имени'),
                'partner_id' => $user->partner_id ?? $attributes['partner_id'] ?? null,
            ];
        });
    }

    /**
     * Для monthly_fee: сформировать запись на основе payable + payment_intent.
     */
    public function fromPayableAndIntent(Payable $payable, ?PaymentIntent $intent = null): static
    {
        return $this->state(function (array $attributes) use ($payable, $intent) {
            $operationAt = $intent?->paid_at ?: $payable->paid_at ?: now();
            $operationCarbon = Carbon::parse($operationAt);

            return [
                'user_id'        => $payable->user_id,
                'partner_id'     => $payable->partner_id,
                'operation_date' => $operationCarbon->format('Y-m-d H:i:s'),
                'payment_month'  => $payable->month
                    ? Carbon::parse($payable->month)->format('Y-m-01')
                    : $operationCarbon->copy()->startOfMonth()->format('Y-m-01'),
                'summ'           => $payable->amount,
                'deal_id'        => $intent?->provider_inv_id
                    ? (string) $intent->provider_inv_id
                    : ($attributes['deal_id'] ?? $this->faker->unique()->uuid()),
                'payment_id'     => $intent?->tbank_payment_id
                    ? (string) $intent->tbank_payment_id
                    : ($attributes['payment_id'] ?? $this->faker->uuid()),
                'payment_status' => 'paid',
            ];
        });
    }
}