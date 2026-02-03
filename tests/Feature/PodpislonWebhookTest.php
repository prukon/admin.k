<?php

namespace Tests\Feature;

use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodpislonWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function webhookUrl(): string
    {
        return route('webhooks.podpislon');
    }

    /**
     * Хелпер: создать контракт, который сможет поймать вебхук
     */
    private function createContractForWebhook(string $providerDocId): Contract
    {
        return Contract::create([
            'school_id'       => 1,
            'user_id'         => 1,
            'group_id'        => null,
            'status'          => Contract::STATUS_SENT,
            'provider_doc_id' => $providerDocId,
            'source_pdf_path' => '/tmp/source-' . $providerDocId . '.pdf',
            'source_sha256'   => hash('sha256', 'dummy-' . $providerDocId),
        ]);
    }


    public function test_requires_signature_when_secret_configured(): void
    {
        config([
            'services.podpislon.webhook_secret' => 'testsecret',
        ]);

        $payload = [
            'EVENT'      => 'DOCUMENT_OPENED',
            'FILE_ID'    => 123,
            'COMPANY_ID' => 456,
            // SIGNATURE нет
        ];

        $response = $this->postJson($this->webhookUrl(), $payload);

        $response
            ->assertStatus(403)
            ->assertJson([
                'ok'    => false,
                'error' => 'signature_required',
            ]);
    }

    public function test_rejects_invalid_signature_when_secret_configured(): void
    {
        config([
            'services.podpislon.webhook_secret' => 'testsecret',
        ]);

        $payload = [
            'EVENT'      => 'DOCUMENT_OPENED',
            'FILE_ID'    => 123,
            'COMPANY_ID' => 456,
            'SIGNATURE'  => 'deadbeef',
        ];

        $response = $this->postJson($this->webhookUrl(), $payload);

        $response
            ->assertStatus(403)
            ->assertJson([
                'ok'    => false,
                'error' => 'invalid_signature',
            ]);
    }

    public function test_accepts_valid_signature_and_updates_contract(): void
    {
        config([
            'services.podpislon.webhook_secret' => 'testsecret',
        ]);

        // создаём контракт
        $contract = $this->createContractForWebhook('123');

        $secret    = config('services.podpislon.webhook_secret');
        $fileId    = 123;
        $companyId = 456;

        // вариант №2: FILE_ID + COMPANY_ID + SECRET
        $signature = md5($fileId . $companyId . $secret);

        $payload = [
            'EVENT'      => 'DOCUMENT_OPENED',
            'FILE_ID'    => $fileId,
            'COMPANY_ID' => $companyId,
            'SIGNATURE'  => $signature,
        ];

        $response = $this->postJson($this->webhookUrl(), $payload);

        $response
            ->assertStatus(200)
            ->assertJson([
                'ok' => true,
            ]);

        $contract->refresh();
        $this->assertEquals(Contract::STATUS_OPENED, $contract->status);

        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'webhook_document_opened',
        ]);
    }

    public function test_accepts_without_signature_when_secret_not_configured(): void
    {
        config([
            'services.podpislon.webhook_secret' => '',
        ]);

        $contract = $this->createContractForWebhook('999');

        $payload = [
            'EVENT'      => 'DOCUMENT_OPENED',
            'FILE_ID'    => 999,
            'COMPANY_ID' => 456,
            // без подписи
        ];

        $response = $this->postJson($this->webhookUrl(), $payload);

        $response
            ->assertStatus(200)
            ->assertJson([
                'ok' => true,
            ]);

        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'webhook_document_opened',
        ]);
    }
}
