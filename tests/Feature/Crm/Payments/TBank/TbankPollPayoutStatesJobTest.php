<?php

namespace Tests\Feature\Crm\Payments\TBank;

use App\Jobs\TinkoffPollPayoutStatesJob;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

class TbankPollPayoutStatesJobTest extends CrmTestCase
{
    public function test_job_polls_intermediate_payouts_and_updates_to_completed(): void
    {
        // e2c keys
        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => [
                // чтобы $ps->is_connected === true и service брал ключи из БД
                'terminal_key' => 'TERM_PAY',
                'token_password' => 'PWD_PAY',
                'e2c_terminal_key' => 'TERM_E2C',
                'e2c_token_password' => 'PWD_E2C',
            ],
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

        // Таблица логов может отсутствовать в некоторых инсталляциях/окружениях.
        if (Schema::hasTable('tinkoff_payout_status_logs')) {
            $this->assertDatabaseHas('tinkoff_payout_status_logs', [
                'payout_id' => $p->id,
                'to_status' => 'COMPLETED',
            ]);
        }
    }
}

