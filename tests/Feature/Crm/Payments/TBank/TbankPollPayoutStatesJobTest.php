<?php

namespace Tests\Feature\Crm\Payments\TBank;

use App\Jobs\TinkoffPollPayoutStatesJob;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

class TbankPollPayoutStatesJobTest extends CrmTestCase
{
    public function test_job_polls_intermediate_payouts_and_updates_to_completed(): void
    {
        // e2c keys
        $this->seedGlobalTbank([
                    'terminal_key' => 'TERM_PAY',
                    'token_password' => 'PWD_PAY',
                    'e2c_terminal_key' => 'TERM_E2C',
                    'e2c_token_password' => 'PWD_E2C',
                ]);

        $p = TinkoffPayout::create([
            'payment_id' => 1,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-1',
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

        // Выполняем job синхронно
        (new TinkoffPollPayoutStatesJob())->handle(app(\App\Services\Tinkoff\TinkoffPayoutsService::class));

        $p->refresh();
        $this->assertSame('COMPLETED', (string) $p->status);
        $this->assertNotNull($p->completed_at);

        $this->assertDatabaseHas('tinkoff_payout_status_logs', [
            'payout_id' => $p->id,
            'to_status' => 'COMPLETED',
        ]);
    }

    public function test_job_does_not_getstate_when_bank_payment_id_is_empty(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_PAY',
            'token_password' => 'PWD_PAY',
            'e2c_terminal_key' => 'TERM_E2C',
            'e2c_token_password' => 'PWD_E2C',
        ]);

        $pending = TinkoffPayout::create([
            'payment_id' => 1,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-pending',
            'amount' => 1000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->subMinute(),
        ]);

        $blank = TinkoffPayout::create([
            'payment_id' => 2,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-blank',
            'amount' => 1000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => '',
        ]);

        $inFlight = TinkoffPayout::create([
            'payment_id' => 3,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-inflight',
            'amount' => 1000,
            'is_final' => 1,
            'status' => 'CREDIT_CHECKING',
            'tinkoff_payout_payment_id' => '5002',
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

        (new TinkoffPollPayoutStatesJob())->handle(app(\App\Services\Tinkoff\TinkoffPayoutsService::class));

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/e2c/v2/GetState')) {
                return false;
            }

            return (string) ($request->data()['PaymentId'] ?? '') === '5002';
        });
        Http::assertSentCount(1);

        $pending->refresh();
        $blank->refresh();
        $inFlight->refresh();
        $this->assertSame('INITIATED', (string) $pending->status);
        $this->assertSame('INITIATED', (string) $blank->status);
        $this->assertNull($pending->payload_state);
        $this->assertNull($blank->payload_state);
        $this->assertSame('COMPLETED', (string) $inFlight->status);
    }

    public function test_poll_state_skips_http_when_bank_payment_id_is_empty(): void
    {
        $payout = TinkoffPayout::create([
            'payment_id' => 1,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-no-id',
            'amount' => 1000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
        ]);

        Http::fake();

        $returned = app(\App\Services\Tinkoff\TinkoffPayoutsService::class)->pollState($payout);

        Http::assertNothingSent();
        $this->assertSame($payout->id, $returned->id);
        $this->assertSame('INITIATED', (string) $returned->status);
        $this->assertNull($returned->payload_state);
    }
}

