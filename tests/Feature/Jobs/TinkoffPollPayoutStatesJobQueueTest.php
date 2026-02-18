<?php

namespace Tests\Feature\Jobs;

use App\Jobs\TinkoffPollPayoutStatesJob;
use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TinkoffPollPayoutStatesJobQueueTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_job_polls_intermediate_payouts_via_queue(): void
    {
        $partner = Partner::factory()->create([
            'tinkoff_partner_id' => 'shopcode-queue-test-2',
        ]);

        PaymentSystem::factory()
            ->tbank()
            ->testMode()
            ->create([
                'partner_id' => $partner->id,
            ]);

        $p = TinkoffPayout::create([
            'payment_id' => 1,
            'partner_id' => $partner->id,
            'deal_id' => 'deal-queue-test-poll',
            'amount' => 1000,
            'is_final' => 1,
            'status' => 'CREDIT_CHECKING',
            'tinkoff_payout_payment_id' => '5001',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/e2c/v2/GetState')) {
                return Http::response([
                    'Success' => true,
                    'Status' => 'COMPLETED',
                ], 200);
            }
            return Http::response(['Success' => true], 200);
        });

        dispatch(new TinkoffPollPayoutStatesJob());
        $this->workQueueUntilEmpty();

        $p->refresh();
        $this->assertSame('COMPLETED', (string) $p->status);
        $this->assertNotNull($p->completed_at);
    }
}

