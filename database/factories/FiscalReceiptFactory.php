<?php

namespace Database\Factories;

use App\Models\FiscalReceipt;
use App\Models\Partner;
use App\Models\Payable;
use App\Models\PaymentIntent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalReceipt>
 */
class FiscalReceiptFactory extends Factory
{
    protected $model = FiscalReceipt::class;

    public function definition(): array
    {
        $amount = $this->faker->numberBetween(500, 10_000);

        return [
            'partner_id' => Partner::factory(),
            'legal_entity_id' => null,
            'payment_intent_id' => null,
            'payment_id' => null,
            'payable_id' => null,
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PENDING,
            'amount' => (string) $amount,
            'invoice_id' => 'dev-invoice-' . $this->faker->unique()->numerify('######'),
            'account_id' => null,
            'external_id' => null,
            'idempotency_key' => 'dev-fiscal-' . $this->faker->unique()->uuid(),
            'request_payload' => null,
            'response_payload' => null,
            'webhook_payload' => null,
            'receipt_url' => null,
            'qr_code_url' => null,
            'document_number' => null,
            'session_number' => null,
            'number' => null,
            'fiscal_number' => null,
            'fiscal_sign' => null,
            'device_number' => null,
            'reg_number' => null,
            'ofd' => null,
            'receipt_datetime' => null,
            'error_code' => null,
            'error_message' => null,
            'warning_message' => null,
            'queued_at' => null,
            'processed_at' => null,
            'failed_at' => null,
        ];
    }

    public function forPartner(int $partnerId): static
    {
        return $this->state(fn () => ['partner_id' => $partnerId]);
    }

    public function forPaymentIntent(PaymentIntent $intent, ?Payable $payable = null): static
    {
        return $this->state(fn () => [
            'partner_id' => (int) $intent->partner_id,
            'payment_intent_id' => (int) $intent->id,
            'payable_id' => $payable?->id ?? $intent->payable_id,
            'amount' => (string) ($intent->out_sum ?? $payable?->amount ?? '0'),
            'invoice_id' => 'pi_' . $intent->id,
            'account_id' => $intent->user_id ? (string) $intent->user_id : null,
            'idempotency_key' => 'income:pi:' . $intent->id,
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'external_id' => 'dev-ck-' . $this->faker->unique()->numerify('##########'),
            'receipt_url' => 'https://receipts.example.test/dev/' . $this->faker->uuid(),
            'receipt_datetime' => now(),
            'processed_at' => now(),
            'queued_at' => now()->subMinute(),
        ]);
    }

    public function errored(): static
    {
        return $this->state(fn () => [
            'status' => FiscalReceipt::STATUS_ERROR,
            'error_code' => 500,
            'error_message' => 'Dev seed: simulated CloudKassir error',
            'failed_at' => now(),
        ]);
    }
}
