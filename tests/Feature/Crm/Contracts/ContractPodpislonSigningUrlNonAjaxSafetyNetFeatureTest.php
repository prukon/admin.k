<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;

/**
 * P1: native POST/GET без X-Requested-With всё равно пишет URL (не пустой 200 / не 500).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractPodpislonSigningUrlNonAjaxSafetyNetFeatureTest extends ContractPodpislonSigningUrlTestCase
{
    public function test_native_post_send_saves_sms_link_and_is_not_empty(): void
    {
        $this->actingAsContractsViewer();
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();
        $this->mockSignatureProviderSend();

        $contract = $this->makeContract();

        $response = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertContains($response->getStatusCode(), [200, 302]);

        $contract->refresh();
        $this->assertSame(self::SAMPLE_URL, $contract->provider_signing_url);
        $this->assertSame(Contract::STATUS_SENT, $contract->status);

        if ($response->getStatusCode() === 200) {
            $response->assertJsonPath('success', true);
        } else {
            $response->assertRedirect();
        }
    }

    public function test_native_get_status_saves_sms_link_on_already_sent_contract(): void
    {
        $actor = $this->actingAsContractsViewer();
        $this->grantContractsSync($actor);
        $entity = $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();

        $contract = $this->makeContract([
            'provider_doc_id' => '971890',
            'status' => Contract::STATUS_SENT,
            'legal_entity_id' => $entity->id,
        ]);

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->from(route('contracts.show', $contract))
            ->get(route('contracts.status', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk();

        $this->assertSame(self::SAMPLE_URL, $contract->fresh()->provider_signing_url);
    }

    public function test_native_html_get_card_shows_sms_link(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeSentWithSigningUrl();

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->get(route('contracts.show', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk()
            ->assertSee(self::SAMPLE_URL, false);
    }

    public function test_native_post_send_with_invalid_fields_does_not_save_url_or_return_empty_200(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeContract();

        $response = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.send', $contract), [
                'signer_firstname' => 'Иван',
                'signer_phone' => '+7 (900) 111-22-33',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['signer_lastname']);

        $this->assertNull($contract->fresh()->provider_signing_url);
    }

    public function test_guest_native_post_send_does_not_save_url(): void
    {
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();

        $contract = $this->makeContract();

        Auth::logout();
        $response = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);

        $this->assertNull($contract->fresh()->provider_signing_url);
        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
    }
}
