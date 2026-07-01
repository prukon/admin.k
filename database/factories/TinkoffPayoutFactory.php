<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TinkoffPayout>
 */
class TinkoffPayoutFactory extends Factory
{
    protected $model = TinkoffPayout::class;

    public function definition(): array
    {
        $gross = $this->faker->numberBetween(50_000, 500_000);
        $bankAccept = (int) round($gross * 0.025);
        $bankPayout = (int) round($gross * 0.001);
        $platform = (int) round($gross * 0.02);
        $net = max(0, $gross - $bankAccept - $bankPayout - $platform);

        return [
            'payment_id' => null,
            'partner_id' => Partner::factory(),
            'legal_entity_id' => null,
            'deal_id' => 'dev-deal-' . $this->faker->unique()->bothify('########'),
            'amount' => $net,
            'is_final' => true,
            'status' => 'COMPLETED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => null,
            'payload_init' => null,
            'payload_payment' => null,
            'payload_state' => null,
            'completed_at' => now(),
            'source' => 'manual',
            'initiated_by_user_id' => null,
            'payer_user_id' => null,
            'gross_amount' => $gross,
            'bank_accept_fee' => $bankAccept,
            'bank_payout_fee' => $bankPayout,
            'platform_fee' => $platform,
            'net_amount' => $net,
        ];
    }

    public function forPayment(TinkoffPayment $payment): static
    {
        return $this->state(function () use ($payment) {
            $gross = (int) $payment->amount;
            $bankAccept = (int) round($gross * 0.025);
            $bankPayout = (int) round($gross * 0.001);
            $platform = (int) round($gross * 0.02);
            $net = max(0, $gross - $bankAccept - $bankPayout - $platform);

            return [
                'payment_id' => (int) $payment->id,
                'partner_id' => (int) $payment->partner_id,
                'legal_entity_id' => $payment->legal_entity_id,
                'deal_id' => (string) ($payment->deal_id ?? ('dev-deal-' . $payment->id)),
                'amount' => $net,
                'gross_amount' => $gross,
                'bank_accept_fee' => $bankAccept,
                'bank_payout_fee' => $bankPayout,
                'platform_fee' => $platform,
                'net_amount' => $net,
            ];
        });
    }

    /**
     * Финальный статус — безопасен для cron (не подхватывается scheduled/poll jobs).
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'COMPLETED',
            'completed_at' => now(),
            'when_to_run' => null,
            'tinkoff_payout_payment_id' => (string) $this->faker->unique()->numberBetween(900_000_000, 999_999_999),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'REJECTED',
            'completed_at' => now(),
            'when_to_run' => null,
            'tinkoff_payout_payment_id' => null,
            'payload_init' => ['Success' => false, 'ErrorCode' => 'DEV-SEED'],
        ]);
    }

    public function autoSource(): static
    {
        return $this->state(fn () => ['source' => 'auto']);
    }
}
