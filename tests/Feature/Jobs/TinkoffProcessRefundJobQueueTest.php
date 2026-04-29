<?php

namespace Tests\Feature\Jobs;

use App\Jobs\TinkoffProcessRefundJob;
use App\Models\Partner;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Refund;
use App\Models\UserPeriodPrice;
use App\Models\UserPrice;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TinkoffProcessRefundJobQueueTest extends JobsTestCase
{
    private function workQueueUntilEmpty(int $maxIterations = 10): void
    {
        for ($i = 0; $i < $maxIterations; $i++) {
            if ((int) DB::table('jobs')->count() <= 0) {
                return;
            }

            Artisan::call('queue:work', [
                'connection' => 'database',
                '--once' => true,
                '--sleep' => 0,
                '--tries' => 1,
                '--quiet' => true,
            ]);
        }

        $this->fail('Queue did not drain within max iterations.');
    }

    public function test_refund_job_succeeds_and_marks_payable_refunded(): void
    {
        $partner = Partner::factory()->create();

        PaymentSystem::factory()
            ->tbank()
            ->testMode()
            ->create([
                'partner_id' => $partner->id,
            ]);

        $userId = 1001;

        $payable = Payable::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'type' => 'monthly_fee',
            'amount' => 1500,
            'currency' => 'RUB',
            'status' => 'paid',
            'month' => now()->startOfMonth()->format('Y-m-01'),
            'meta' => ['month' => now()->startOfMonth()->format('Y-m-01')],
            'paid_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'summ' => $payable->amount,
        ]);

        $intent = PaymentIntent::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'tbank_payment_id' => 777001,
            'status' => 'paid',
            'out_sum' => $payable->amount,
        ]);

        UserPrice::factory()->paid()->create([
            'user_id' => $userId,
            'new_month' => $payable->month->format('Y-m-d'),
            'price' => (string) (int) $payable->amount,
        ]);

        $refund = Refund::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'payment_id' => $payment->id,
            'amount' => 1500,
            'currency' => 'RUB',
            'status' => 'pending',
            'provider' => 'tbank',
            'meta' => [
                'payment_intent_id' => $intent->id,
                'tbank_payment_id' => (int) $intent->tbank_payment_id,
            ],
        ]);

        Http::fake([
            'https://rest-api-test.tinkoff.ru/v2/Cancel' => Http::response([
                'Success' => true,
            ], 200),
        ]);

        (new TinkoffProcessRefundJob($refund->id))->handle();

        $refund->refresh();
        $payable->refresh();

        $this->assertSame('succeeded', (string) $refund->status);
        $this->assertNotNull($refund->processed_at);
        $this->assertSame((string) (int) $intent->tbank_payment_id, (string) $refund->provider_refund_id);

        $this->assertSame('refunded', (string) $payable->status);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $userId,
            'new_month' => $payable->month->format('Y-m-d'),
            'is_paid' => 0,
        ]);
    }

    public function test_refund_job_fails_when_payment_intent_id_missing(): void
    {
        $partner = Partner::factory()->create();

        PaymentSystem::factory()
            ->tbank()
            ->testMode()
            ->create([
                'partner_id' => $partner->id,
            ]);

        $userId = 1002;

        $payable = Payable::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'type' => 'club_fee',
            'amount' => 500,
            'currency' => 'RUB',
            'status' => 'paid',
            'month' => null,
            'meta' => null,
            'paid_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'summ' => $payable->amount,
        ]);

        $refund = Refund::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'payment_id' => $payment->id,
            'amount' => 500,
            'currency' => 'RUB',
            'status' => 'pending',
            'provider' => 'tbank',
            'meta' => [],
        ]);

        Http::fake();

        (new TinkoffProcessRefundJob($refund->id))->handle();

        $refund->refresh();
        $this->assertSame('failed', (string) $refund->status);
        $this->assertSame('payment_intent_id_missing', (string) ($refund->meta['failed_reason'] ?? null));
    }

    public function test_refund_job_succeeds_and_marks_user_period_price_unpaid_for_custom_payment_fee(): void
    {
        $partner = Partner::factory()->create();

        PaymentSystem::factory()
            ->tbank()
            ->testMode()
            ->create([
                'partner_id' => $partner->id,
            ]);

        $userId = 1003;

        $upp = UserPeriodPrice::query()->create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'date_start' => '2026-11-01',
            'date_end' => '2026-11-30',
            'amount' => '500.00',
            'is_paid' => 1,
        ]);

        $payable = Payable::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'type' => 'custom_payment_fee',
            'amount' => 500,
            'currency' => 'RUB',
            'status' => 'paid',
            'month' => null,
            'meta' => [
                'user_period_price_id' => $upp->id,
            ],
            'paid_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'summ' => $payable->amount,
        ]);

        $intent = PaymentIntent::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'tbank_payment_id' => 777003,
            'provider_inv_id' => 777003,
            'status' => 'paid',
            'out_sum' => $payable->amount,
        ]);

        $refund = Refund::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'payment_id' => $payment->id,
            'amount' => 500,
            'currency' => 'RUB',
            'status' => 'pending',
            'provider' => 'tbank',
            'meta' => [
                'payment_intent_id' => $intent->id,
                'tbank_payment_id' => (int) $intent->tbank_payment_id,
            ],
        ]);

        Http::fake([
            'https://rest-api-test.tinkoff.ru/v2/Cancel' => Http::response([
                'Success' => true,
            ], 200),
        ]);

        (new TinkoffProcessRefundJob($refund->id))->handle();

        $refund->refresh();
        $payable->refresh();
        $upp->refresh();

        $this->assertSame('succeeded', (string) $refund->status);
        $this->assertSame('refunded', (string) $payable->status);
        $this->assertSame(0, (int) $upp->is_paid);

        $this->assertDatabaseHas('user_period_prices', [
            'id' => $upp->id,
            'is_paid' => 0,
        ]);
    }
}

