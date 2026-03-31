<?php

namespace Tests\Feature\Crm\Payments;

use App\Jobs\SendCloudKassirReceiptJob;
use App\Models\FiscalReceipt;
use App\Models\Payable;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

class SendCloudKassirReceiptJobTest extends CrmTestCase
{
    public function test_job_sends_receipt_and_marks_it_as_queued(): void
    {
        Config::set('services.cloudkassir.base_url', 'https://api.cloudpayments.ru');
        Config::set('services.cloudkassir.public_id', 'test-public-id');
        Config::set('services.cloudkassir.api_secret', 'test-secret');
        Config::set('services.cloudkassir.inn', '7708806062');
        Config::set('services.cloudkassir.timeout', 30);

        Config::set('services.cloudkassir.taxation_system', 1);
        Config::set('services.cloudkassir.default_method', 4);
        Config::set('services.cloudkassir.default_object', 4);
        Config::set('services.cloudkassir.russia_time_zone', 2);

        Config::set('services.cloudkassir.agent.enabled', true);
        Config::set('services.cloudkassir.agent.agent_sign', 6);
        Config::set('services.cloudkassir.agent.use_purveyor_data', true);
        Config::set('services.cloudkassir.agent.use_agent_data', true);
        Config::set('services.cloudkassir.agent.payment_agent_phone', '+79110263811');

        $this->partner->update([
            'organization_name' => 'ООО Школа футбола',
            'tax_id' => '7700000000',
            'phone' => '+79990000002',
            'website' => 'https://school.example',
            'taxation_system' => 1,
            'vat' => 10,
        ]);

        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => '3500.00',
            'currency' => 'RUB',
            'status' => 'paid',
            'month' => '2026-03-01',
            'meta' => ['month' => '2026-03-01'],
            'paid_at' => now(),
        ]);

        $intent = PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum' => '3500.00',
            'payment_date' => '2026-03-01',
            'paid_at' => now(),
            'meta' => json_encode(['user_name' => $this->user->name], JSON_UNESCAPED_UNICODE),
        ]);

        $receipt = FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_intent_id' => $intent->id,
            'payable_id' => $payable->id,
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PENDING,
            'amount' => '3500.00',
            'invoice_id' => 'pi_' . $intent->id,
            'account_id' => (string) $this->user->id,
            'idempotency_key' => 'income:test:' . $intent->id,
        ]);

        Http::fake([
            'https://api.cloudpayments.ru/kkt/receipt' => Http::response([
                'Success' => true,
                'Message' => 'Queued',
                'Warning' => null,
                'WarningCodes' => null,
                'Model' => [
                    'Id' => 'ck-queued-123',
                    'ErrorCode' => 0,
                    'ReceiptLocalUrl' => 'https://receipts.ru/ck-queued-123',
                ],
            ], 200),
        ]);

        dispatch_sync(new SendCloudKassirReceiptJob($receipt->id));

        Http::assertSent(function ($request) {
            $data = $request->data();

            $cr = $data['CustomerReceipt'] ?? [];

            return $request->url() === 'https://api.cloudpayments.ru/kkt/receipt'
                && $data['Inn'] === '7708806062'
                && $data['Type'] === 'Income'
                && ($cr['TaxationSystem'] ?? null) === 1
                && ! array_key_exists('AgentSign', $cr)
                && ($cr['Items'][0]['AgentSign'] ?? null) === '6'
                && ($cr['Items'][0]['Label'] ?? null) === 'Абонемент за март'
                && ($cr['Items'][0]['Vat'] ?? null) === 10
                && ($cr['Items'][0]['AgentData']['PaymentAgentPhone'] ?? null) === '+79110263811'
                && ($cr['Items'][0]['PurveyorData']['Inn'] ?? null) === '7700000000';
        });

        $this->assertDatabaseHas('fiscal_receipts', [
            'id' => $receipt->id,
            'status' => FiscalReceipt::STATUS_QUEUED,
            'external_id' => 'ck-queued-123',
            'receipt_url' => 'https://receipts.ru/ck-queued-123',
            'error_code' => 0,
        ]);

        $receipt->refresh();
        $this->assertNotNull($receipt->queued_at);
        $this->assertNull($receipt->failed_at);
        $this->assertNull($receipt->error_message);
    }
}
