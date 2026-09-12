<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractSignRequest;
use App\Models\PartnerLegalEntity;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * P1: AJAX send при status:false Подпислона — 422, текст ошибки, без ложного sent.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractSendFailureMessageAjaxContractFeatureTest extends ContractSendFailureMessageTestCase
{
    public function test_ajax_send_returns_422_with_provider_message_and_does_not_write_sent(): void
    {
        $this->actingAsContractsViewer();
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonAddDocumentRejected();

        $contract = $this->makeDraftContract();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'send_not_sent')
            ->assertJsonPath('message', self::PROVIDER_ERROR)
            ->assertJsonStructure(['success', 'message', 'code']);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_FAILED, $contract->status);
        $this->assertNull($contract->provider_doc_id);
        $this->assertSame(self::PROVIDER_ERROR, $contract->lastFailureMessage());

        $this->assertSame(0, ContractEvent::query()->where('contract_id', $contract->id)->where('type', 'sent')->count());
        $this->assertSame(1, ContractEvent::query()->where('contract_id', $contract->id)->where('type', 'failed')->count());

        $failed = ContractEvent::query()->where('contract_id', $contract->id)->where('type', 'failed')->first();
        $this->assertNotNull($failed);
        $payload = json_decode((string) $failed->payload_json, true);
        $this->assertIsArray($payload);
        $this->assertSame(self::PROVIDER_ERROR, $payload['message'] ?? null);

        $this->assertDatabaseHas('contract_sign_requests', [
            'contract_id' => $contract->id,
            'status' => 'failed',
        ]);
        $this->assertNull(ContractSignRequest::query()->where('contract_id', $contract->id)->value('provider_request_id'));

        $addDocumentCalls = Http::recorded(function ($request) {
            return str_contains((string) $request->url(), 'add-document');
        });
        $this->assertCount(1, $addDocumentCalls);

        $show = $this->get(route('contracts.show', $contract))->assertOk();
        $html = $show->getContent();
        $this->assertStringContainsString('id="contract-status-error"', $html);
        $this->assertStringContainsString(self::PROVIDER_ERROR, $html);
        $this->assertStringContainsString('contract-event-error', $html);
        $this->assertStringNotContainsString('Отправлено СМС', $html);

        $row = $this->listRowFor($contract);
        $this->assertIsArray($row);
        $this->assertSame(self::PROVIDER_ERROR, $row['status_error']);
    }

    public function test_ajax_send_without_lastname_returns_422_and_does_not_mark_failed(): void
    {
        $this->actingAsContractsViewer();
        $this->seedPodpislonLegalEntity();
        $contract = $this->makeDraftContract();

        $payload = $this->validSendPayload();
        unset($payload['signer_lastname']);

        $response = $this->postJson(route('contracts.send', $contract), $payload);
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJsonValidationErrors(['signer_lastname']);
        $this->assertNotSame('', (string) $response->json('errors.signer_lastname.0'));

        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
        $this->assertNull($contract->fresh()->lastFailureMessage());
    }

    public function test_ajax_send_without_firstname_returns_422_errors_signer_firstname(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeDraftContract();

        $payload = $this->validSendPayload();
        unset($payload['signer_firstname']);

        $this->postJson(route('contracts.send', $contract), $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['signer_firstname']);

        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
        $this->assertNull($contract->fresh()->lastFailureMessage());
    }

    public function test_ajax_send_with_invalid_phone_returns_422_errors_signer_phone(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeDraftContract();

        $payload = $this->validSendPayload();
        $payload['signer_phone'] = '123';

        $this->postJson(route('contracts.send', $contract), $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['signer_phone']);

        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
        $this->assertNull($contract->fresh()->lastFailureMessage());
    }

    public function test_ajax_send_without_legal_entity_key_returns_422_errors_podpislon_api_key(): void
    {
        $this->actingAsContractsViewer();
        Config::set('services.podpislon.key', 'ENV-FALLBACK-MUST-NOT-BE-USED');

        PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП без ключа',
            'is_enabled' => true,
        ]);

        $contract = $this->makeDraftContract();

        $response = $this->postJson(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['podpislon_api_key']);

        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
        $this->assertNull($contract->fresh()->lastFailureMessage());
    }

    public function test_ajax_send_without_provider_message_falls_back_to_generic_text(): void
    {
        $this->actingAsContractsViewer();
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonAddDocumentRejectedWithoutMessage();
        $contract = $this->makeDraftContract();

        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'send_not_sent')
            ->assertJsonPath('message', ContractEvent::PROVIDER_SEND_NOT_CONFIRMED);

        $this->assertSame(Contract::STATUS_FAILED, $contract->fresh()->status);
        $this->assertSame(ContractEvent::PROVIDER_SEND_NOT_CONFIRMED, $contract->fresh()->lastFailureMessage());
        $this->assertSame(0, ContractEvent::query()->where('contract_id', $contract->id)->where('type', 'sent')->count());
    }

    public function test_ajax_send_success_does_not_put_status_error_on_list_or_card(): void
    {
        $this->actingAsContractsViewer();
        $this->seedPodpislonLegalEntity();
        $this->mockSignatureProviderSend();
        Http::fake([
            '*' => Http::response([[
                'status' => 15,
                'status_text' => 'sent',
                'contacts' => [['phone' => '+79001112233']],
            ]], 200),
        ]);

        $contract = $this->makeDraftContract();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk()
            ->assertJsonPath('success', true);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
        $this->assertNull($contract->lastFailureMessage());

        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="contract-status-error"', $html);

        $row = $this->listRowFor($contract);
        $this->assertIsArray($row);
        $this->assertNull($row['status_error']);
    }
}
