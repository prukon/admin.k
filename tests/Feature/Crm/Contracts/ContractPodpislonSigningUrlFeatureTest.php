<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\Http;

/**
 * Сохранение contacts[].link в provider_signing_url при отправке, ресенде и sync.
 * UX-баг: sync при уже sent раньше выходил до capture — ссылка на карточке не появлялась.
 */
final class ContractPodpislonSigningUrlFeatureTest extends ContractPodpislonSigningUrlTestCase
{
    public function test_send_saves_sms_link_and_card_html_shows_it_after_reload(): void
    {
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();
        $this->mockSignatureProviderSend();

        $contract = $this->makeContract();

        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
        $this->assertSame(self::SAMPLE_URL, $contract->provider_signing_url);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('id="contract-provider-signing-url"', false)
            ->assertSee(self::SAMPLE_URL, false);
    }

    /**
     * RED→GREEN: старые sent-договоры (без URL в БД) получают ссылку при «Синхронизировать»,
     * даже если статус у провайдера уже sent (synced: false).
     */
    public function test_sync_on_already_sent_contract_saves_sms_link_without_changing_status(): void
    {
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);
        $entity = $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();

        $contract = $this->makeContract([
            'provider_doc_id' => '971890',
            'status' => Contract::STATUS_SENT,
            'legal_entity_id' => $entity->id,
        ]);

        $this->getJson(route('contracts.status', $contract))
            ->assertOk()
            ->assertJsonPath('status', Contract::STATUS_SENT)
            ->assertJsonPath('synced', false);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
        $this->assertSame(self::SAMPLE_URL, $contract->provider_signing_url);
    }

    public function test_sync_does_not_save_foreign_host_as_sms_link(): void
    {
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);
        $entity = $this->seedPodpislonLegalEntity();

        Http::fake([
            '*' => Http::response([[
                'status' => 15,
                'status_text' => 'sent',
                'contacts' => [
                    ['link' => 'https://evil.example/sign/pack/1/abc'],
                ],
            ]], 200),
        ]);

        $contract = $this->makeContract([
            'provider_doc_id' => '971890',
            'status' => Contract::STATUS_SENT,
            'legal_entity_id' => $entity->id,
        ]);

        $this->getJson(route('contracts.status', $contract))->assertOk();

        $contract->refresh();
        $this->assertNull($contract->provider_signing_url);
    }

    public function test_sync_without_contacts_link_does_not_invent_sms_url(): void
    {
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);
        $entity = $this->seedPodpislonLegalEntity();

        Http::fake([
            '*' => Http::response([[
                'status' => 15,
                'status_text' => 'sent',
            ]], 200),
        ]);

        $contract = $this->makeContract([
            'provider_doc_id' => '971890',
            'status' => Contract::STATUS_SENT,
            'legal_entity_id' => $entity->id,
        ]);

        $this->getJson(route('contracts.status', $contract))->assertOk();

        $this->assertNull($contract->fresh()->provider_signing_url);
    }

    public function test_send_on_already_sent_package_saves_sms_link_via_same_send_endpoint(): void
    {
        $entity = $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();

        $contract = $this->makeContract([
            'provider_doc_id' => '971890',
            'status' => Contract::STATUS_SENT,
            'legal_entity_id' => $entity->id,
        ]);

        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(self::SAMPLE_URL, $contract->fresh()->provider_signing_url);
    }

    public function test_resend_endpoint_saves_sms_link(): void
    {
        $entity = $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();

        $contract = $this->makeContract([
            'provider_doc_id' => '971890',
            'status' => Contract::STATUS_SENT,
            'legal_entity_id' => $entity->id,
        ]);
        $this->attachSignRequest($contract);

        $this->postJson(route('contracts.resend', $contract), [])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(self::SAMPLE_URL, $contract->fresh()->provider_signing_url);
    }
}
