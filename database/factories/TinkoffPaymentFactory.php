<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Models\TinkoffPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TinkoffPayment>
 */
class TinkoffPaymentFactory extends Factory
{
    protected $model = TinkoffPayment::class;

    public function definition(): array
    {
        $amountCents = $this->faker->numberBetween(50_000, 500_000);

        return [
            'order_id' => (string) Str::uuid(),
            'partner_id' => Partner::factory(),
            'legal_entity_id' => null,
            'amount' => $amountCents,
            'method' => $this->faker->randomElement(['card', 'sbp', 'tpay']),
            'status' => 'NEW',
            'tinkoff_payment_id' => (string) $this->faker->unique()->numberBetween(300_000_000, 2_000_000_000),
            'deal_id' => 'dev-deal-' . Str::lower(Str::random(12)),
            'payment_url' => null,
            'payload' => null,
            'confirmed_at' => null,
            'canceled_at' => null,
        ];
    }

    public function forPartner(int $partnerId): static
    {
        return $this->state(fn () => ['partner_id' => $partnerId]);
    }

    public function forLegalEntity(PartnerLegalEntity $entity): static
    {
        return $this->state(fn () => [
            'partner_id' => (int) $entity->partner_id,
            'legal_entity_id' => (int) $entity->id,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => 'CONFIRMED',
            'confirmed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'REJECTED',
            'canceled_at' => now(),
        ]);
    }

    public function card(): static
    {
        return $this->state(fn () => ['method' => 'card']);
    }

    public function sbp(): static
    {
        return $this->state(fn () => ['method' => 'sbp']);
    }
}
