<?php

namespace Tests\Feature;

use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Monolog\Handler\NullHandler;
use Tests\TestCase;

class PodpislonWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // В тестовой среде может не быть прав на запись в storage/logs,
        // поэтому глушим канал podpislon, чтобы контроллер не падал на логировании.
        config([
            'logging.channels.podpislon' => [
                'driver'  => 'monolog',
                'handler' => NullHandler::class,
            ],
        ]);
    }

    private function webhookUrl(): string
    {
        return route('webhooks.podpislon');
    }

    /**
     * Хелпер: отправить raw x-www-form-urlencoded тело (важно для подписи)
     */
    private function postRawForm(string $url, string $rawBody)
    {
        return $this->call(
            'POST',
            $url,
            [],   // parameters
            [],   // cookies
            [],   // files
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $rawBody
        );
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
            // подпись теперь не зависит от секрета, но поле SIGNATURE обязательно
            'services.podpislon.webhook_secret' => 'anything',
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
            'services.podpislon.webhook_secret' => 'anything',
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
            'services.podpislon.webhook_secret' => 'anything',
            'services.podpislon.webhook_token'  => 'tok',
        ]);

        // создаём контракт
        $contract = $this->createContractForWebhook('123');

        $fileId    = 123;
        $companyId = 456;

        // подпись Подпислона: md5(raw-body без SIGNATURE)
        $rawNoSig  = 'EVENT=DOCUMENT_OPENED&FILE_ID=' . $fileId . '&COMPANY_ID=' . $companyId;
        $signature = md5($rawNoSig);
        $rawBody   = $rawNoSig . '&SIGNATURE=' . $signature;

        $response = $this->postRawForm($this->webhookUrl() . '?token=tok', $rawBody);

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
            ->assertStatus(403)
            ->assertJson([
                'ok'    => false,
                'error' => 'signature_required',
            ]);

        $this->assertDatabaseMissing('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'webhook_document_opened',
        ]);
    }

    public function test_requires_token_when_token_configured(): void
    {
        config([
            'services.podpislon.webhook_token' => 'tok',
        ]);

        $rawNoSig  = 'EVENT=DOCUMENT_OPENED&FILE_ID=1&COMPANY_ID=2';
        $rawBody   = $rawNoSig . '&SIGNATURE=' . md5($rawNoSig);

        $response = $this->postRawForm($this->webhookUrl(), $rawBody);

        $response
            ->assertStatus(403)
            ->assertJson([
                'ok'    => false,
                'error' => 'token_required',
            ]);
    }
}
