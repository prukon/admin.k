<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\FiscalReceipt;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Crm\CrmTestCase;

class CloudKassirWebhookTest extends CrmTestCase
{
    public function test_cloudkassir_webhook_marks_receipt_as_processed(): void
    {
        Config::set('services.cloudkassir.api_secret', 'cloud-secret');

        $receipt = FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_QUEUED,
            'amount' => '3500.00',
            'invoice_id' => 'pi_1',
            'account_id' => (string) $this->user->id,
            'external_id' => 'ck-123',
            'idempotency_key' => 'income:partner:1:payable:1:intent:1',
        ]);

        $payload = [
            'Id' => 'ck-123',
            'DocumentNumber' => 777,
            'SessionNumber' => 10,
            'Number' => 5,
            'FiscalSign' => '123456789',
            'DeviceNumber' => '01801810008669',
            'RegNumber' => '0000000370021655',
            'FiscalNumber' => '9999078902005454',
            'Ofd' => 'Первый ОФД',
            'Url' => 'https://receipts.ru/ck-123',
            'QrCodeUrl' => 'https://qr.example/ck-123',
            'DateTime' => '2026-03-13 10:11:12',
        ];
        
        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hmac = base64_encode(hash_hmac('sha256', $rawBody, 'cloud-secret', true));
        
        $response = $this->call(
            'POST',
            '/webhook/cloudkassir/receipt',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CONTENT_HMAC' => $hmac,
            ],
            $rawBody
        );











        $response->assertOk()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('fiscal_receipts', [
            'id' => $receipt->id,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'external_id' => 'ck-123',
            'document_number' => '777',
            'session_number' => '10',
            'number' => '5',
            'fiscal_sign' => '123456789',
            'device_number' => '01801810008669',
            'reg_number' => '0000000370021655',
            'fiscal_number' => '9999078902005454',
            'ofd' => 'Первый ОФД',
            'receipt_url' => 'https://receipts.ru/ck-123',
            'qr_code_url' => 'https://qr.example/ck-123',
        ]);
    }
}