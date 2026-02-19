<?php

namespace Tests\Feature\Jobs;

use App\Jobs\TinkoffRunScheduledPayoutsJob;
use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TinkoffRunScheduledPayoutsJobQueueTest extends JobsTestCase
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

    public function test_job_runs_due_payouts_and_leaves_future_untouched(): void
    {
        $partner = Partner::factory()->create([
            'tinkoff_partner_id' => 'shopcode-queue-test-1',
        ]);

        PaymentSystem::factory()
            ->tbank()
            ->testMode()
            ->create([
                'partner_id' => $partner->id,
            ]);

        $due = TinkoffPayout::create([
            'partner_id' => $partner->id,
            'deal_id' => 'deal-queue-test-1',
            'amount' => 10000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->subMinute(),
        ]);

        $future = TinkoffPayout::create([
            'partner_id' => $partner->id,
            'deal_id' => 'deal-queue-test-2',
            'amount' => 10000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addHour(),
        ]);

        $alreadyStarted = TinkoffPayout::create([
            'partner_id' => $partner->id,
            'deal_id' => 'deal-queue-test-3',
            'amount' => 10000,
            'is_final' => 1,
            'status' => 'CREDIT_CHECKING',
            'tinkoff_payout_payment_id' => '555',
            'when_to_run' => now()->subMinute(),
        ]);

        Http::fake(function ($request) use ($due) {
            $url = $request->url();

            if (str_contains($url, '/e2c/v2/Init')) {
                return Http::response([
                    'Success' => true,
                    'PaymentId' => (string) (700000 + (int) $due->id),
                ], 200);
            }
            if (str_contains($url, '/e2c/v2/Payment')) {
                return Http::response([
                    'Success' => true,
                    'Status' => 'CREDIT_CHECKING',
                ], 200);
            }
            if (str_contains($url, '/e2c/v2/GetState')) {
                return Http::response([
                    'Success' => true,
                    'Status' => 'COMPLETED',
                ], 200);
            }

            return Http::response(['Success' => true], 200);
        });

        dispatch(new TinkoffRunScheduledPayoutsJob());
        $this->workQueueUntilEmpty();

        $due->refresh();
        $future->refresh();
        $alreadyStarted->refresh();

        $this->assertSame('COMPLETED', (string) $due->status);
        $this->assertNotNull($due->tinkoff_payout_payment_id);
        $this->assertNotNull($due->completed_at);

        $this->assertNull($future->tinkoff_payout_payment_id);
        $this->assertSame('INITIATED', (string) $future->status);

        $this->assertSame('555', (string) $alreadyStarted->tinkoff_payout_payment_id);
    }
}

