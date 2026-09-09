<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\PartnerLegalEntity;
use Illuminate\Support\Facades\Config;

/**
 * P1: AJAX send/status — JSON 200/422, errors по полям, без пустого 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractPodpislonSigningUrlAjaxContractFeatureTest extends ContractPodpislonSigningUrlTestCase
{
    public function test_ajax_send_returns_json_success_and_saves_sms_link(): void
    {
        $this->actingAsContractsViewer();
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();
        $this->mockSignatureProviderSend();

        $contract = $this->makeContract();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'SMS отправлена')
            ->assertJsonStructure(['success', 'message']);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertSame(self::SAMPLE_URL, $contract->fresh()->provider_signing_url);
    }

    public function test_ajax_send_without_lastname_returns_422_errors_and_does_not_save_url(): void
    {
        $this->actingAsContractsViewer();
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();

        $contract = $this->makeContract();
        $payload = $this->validSendPayload();
        unset($payload['signer_lastname']);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('contracts.send', $contract), $payload);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJsonValidationErrors(['signer_lastname']);

        $this->assertNotSame('', (string) $response->json('errors.signer_lastname.0'));
        $this->assertNull($contract->fresh()->provider_signing_url);
        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
    }

    public function test_ajax_send_with_invalid_phone_returns_422_errors_signer_phone(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeContract();

        $payload = $this->validSendPayload();
        $payload['signer_phone'] = '123';

        $response = $this->postJson(route('contracts.send', $contract), $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['signer_phone']);
        $this->assertNull($contract->fresh()->provider_signing_url);
    }

    public function test_ajax_send_without_legal_entity_key_returns_422_errors_podpislon_api_key(): void
    {
        $this->actingAsContractsViewer();
        Config::set('services.podpislon.key', 'ENV-FALLBACK-MUST-NOT-BE-USED');

        PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП без ключа',
            'is_enabled' => true,
        ]);

        $contract = $this->makeContract();

        $response = $this->postJson(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['podpislon_api_key']);

        $this->assertNull($contract->fresh()->provider_signing_url);
        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
    }

    public function test_ajax_status_without_provider_doc_id_returns_422_and_does_not_save_url(): void
    {
        $actor = $this->actingAsContractsViewer();
        $this->grantContractsSync($actor);
        $this->seedPodpislonLegalEntity();

        $contract = $this->makeContract();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.status', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
        $this->assertNotSame('', trim((string) $response->json('message')));
        $this->assertNull($contract->fresh()->provider_signing_url);
    }

    public function test_ajax_resend_without_sign_request_returns_422_and_does_not_invent_url(): void
    {
        $this->actingAsContractsViewer();
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();

        $contract = $this->makeSentWithSigningUrl(['provider_signing_url' => null]);

        $response = $this->postJson(route('contracts.resend', $contract), []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'no_sign_request');
        $this->assertNull($contract->fresh()->provider_signing_url);
    }
}
