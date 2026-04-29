<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RobokassaProcessRefundJob;
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

class RobokassaProcessRefundJobQueueTest extends JobsTestCase
{
    private function workQueueUntilEmpty(int $maxIterations = 20): void
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

    public function test_refund_job_succeeds_when_state_finished(): void
    {
        $partner = Partner::factory()->create();

        PaymentSystem::factory()
            ->robokassa()
            ->create([
                'partner_id' => $partner->id,
            ]);

        $userId = 2001;
        $month = now()->startOfMonth()->format('Y-m-01');

        $payable = Payable::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'type' => 'monthly_fee',
            'amount' => 1200,
            'currency' => 'RUB',
            'status' => 'paid',
            'month' => $month,
            'meta' => ['month' => $month],
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
            'provider' => 'robokassa',
            'status' => 'paid',
            'out_sum' => $payable->amount,
            'payment_date' => $month,
        ]);

        UserPrice::factory()->paid()->create([
            'user_id' => $userId,
            'new_month' => $month,
            'price' => (string) (int) $payable->amount,
        ]);

        $refund = Refund::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'payment_id' => $payment->id,
            'amount' => 1200,
            'currency' => 'RUB',
            'status' => 'pending',
            'provider' => 'robokassa',
            'meta' => [
                'inv_id' => 123456,
                'payment_intent_id' => $intent->id,
            ],
        ]);

        Http::fake([
            'https://auth.robokassa.ru/Merchant/WebService/Service.asmx/OpStateExt*' => Http::response(
                '<?xml version="1.0" encoding="utf-8"?><Response><Result><Code>0</Code><OpKey>OPKEY_TEST</OpKey></Result></Response>',
                200,
                ['Content-Type' => 'text/xml']
            ),
            'https://services.robokassa.ru/RefundService/Refund/Create' => Http::response([
                'success' => true,
                'message' => 'ok',
                'requestId' => 'REQ-1',
            ], 200),
            'https://services.robokassa.ru/RefundService/Refund/GetState*' => Http::response([
                'label' => 'finished',
                'requestId' => 'REQ-1',
                'amount' => 1200,
            ], 200),
        ]);

        dispatch(new RobokassaProcessRefundJob($refund->id));
        $this->workQueueUntilEmpty();

        $refund->refresh();
        $payable->refresh();

        $this->assertSame('succeeded', (string) $refund->status);
        $this->assertNotNull($refund->processed_at);
        $this->assertSame('REQ-1', (string) $refund->provider_refund_id);

        $this->assertSame('refunded', (string) $payable->status);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $userId,
            'new_month' => $month,
            'is_paid' => 0,
        ]);
    }

    public function test_refund_job_succeeds_and_marks_user_period_price_unpaid_for_abonement_fee_period(): void
    {
        $partner = Partner::factory()->create();

        PaymentSystem::factory()
            ->robokassa()
            ->create([
                'partner_id' => $partner->id,
            ]);

        $userId = 2002;

        $upp = UserPeriodPrice::query()->create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'date_start' => '2026-11-01',
            'date_end' => '2026-11-30',
            'amount' => '700.00',
            'is_paid' => 1,
        ]);

        $payable = Payable::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'type' => 'abonement_fee_period',
            'amount' => 700,
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
            'provider' => 'robokassa',
            'status' => 'paid',
            'out_sum' => $payable->amount,
            'payment_date' => 'Абонемент',
        ]);

        $refund = Refund::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'payment_id' => $payment->id,
            'amount' => 700,
            'currency' => 'RUB',
            'status' => 'pending',
            'provider' => 'robokassa',
            'meta' => [
                'inv_id' => 223344,
                'payment_intent_id' => $intent->id,
            ],
        ]);

        Http::fake([
            'https://auth.robokassa.ru/Merchant/WebService/Service.asmx/OpStateExt*' => Http::response(
                '<?xml version="1.0" encoding="utf-8"?><Response><Result><Code>0</Code><OpKey>OPKEY_TEST</OpKey></Result></Response>',
                200,
                ['Content-Type' => 'text/xml']
            ),
            'https://services.robokassa.ru/RefundService/Refund/Create' => Http::response([
                'success' => true,
                'message' => 'ok',
                'requestId' => 'REQ-A',
            ], 200),
            'https://services.robokassa.ru/RefundService/Refund/GetState*' => Http::response([
                'label' => 'finished',
                'requestId' => 'REQ-A',
                'amount' => 700,
            ], 200),
        ]);

        dispatch(new RobokassaProcessRefundJob($refund->id));
        $this->workQueueUntilEmpty();

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

