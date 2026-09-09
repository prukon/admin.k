<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;

/**
 * Карточка со ссылкой Подпислона: гость / без права / тренер / чужая школа / без sync.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractPodpislonSigningUrlAccessFeatureTest extends ContractPodpislonSigningUrlTestCase
{
    public function test_guest_is_redirected_from_card_and_does_not_see_sms_link(): void
    {
        $contract = $this->makeSentWithSigningUrl();

        Auth::logout();

        $resp = $this->get(route('contracts.show', $contract), ['HTTP_ACCEPT' => 'text/html']);
        $this->assertNotSame(200, $resp->getStatusCode());
        $this->assertNotSame(500, $resp->getStatusCode());
        $resp->assertRedirect();
        $resp->assertDontSee(self::SAMPLE_URL, false);
    }

    public function test_guest_json_send_and_status_are_401_and_do_not_save_url(): void
    {
        $entity = $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();

        $draft = $this->makeContract(['legal_entity_id' => $entity->id]);
        $sent = $this->makeContract([
            'provider_doc_id' => '971890',
            'status' => Contract::STATUS_SENT,
            'legal_entity_id' => $entity->id,
        ]);

        Auth::logout();

        $this->postJson(route('contracts.send', $draft), $this->validSendPayload())
            ->assertUnauthorized();
        $this->getJson(route('contracts.status', $sent))
            ->assertUnauthorized();

        $this->assertNull($draft->fresh()->provider_signing_url);
        $this->assertNull($sent->fresh()->provider_signing_url);
    }

    public function test_manager_without_contracts_view_gets_403_and_does_not_see_url(): void
    {
        $contract = $this->makeSentWithSigningUrl();
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $resp = $this->get(route('contracts.show', $contract));
        $resp->assertForbidden();
        $resp->assertDontSee(self::SAMPLE_URL, false);

        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())
            ->assertForbidden();
        $this->getJson(route('contracts.status', $contract))
            ->assertForbidden();
    }

    public function test_trainer_cannot_open_card_or_sync_sms_link(): void
    {
        $contract = $this->makeSentWithSigningUrl();
        $trainer = $this->createUserWithRole('trainer');

        $this->actingAs($trainer)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.show', $contract))->assertForbidden();
        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())
            ->assertForbidden();
        $this->getJson(route('contracts.status', $contract))->assertForbidden();
    }

    public function test_foreign_school_cannot_open_card_or_see_signing_url(): void
    {
        $contract = $this->makeSentWithSigningUrl();

        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true]);
        $this->grantPermissionToRoleForPartner(
            $this->foreignUser->role_id,
            $this->foreignPartner->id,
            self::PERM_CONTRACTS_VIEW
        );

        $resp = $this->get(route('contracts.show', $contract));
        $this->assertContains($resp->getStatusCode(), [403, 404]);
        $resp->assertDontSee(self::SAMPLE_URL, false);
    }

    public function test_viewer_without_sync_sees_sms_link_but_cannot_write_it_via_status(): void
    {
        $actor = $this->actingAsContractsViewer();
        $contract = $this->makeSentWithSigningUrl(['provider_signing_url' => null]);
        $this->fakePodpislonHttpWithSigningUrl();

        $html = $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('id="contract-provider-signing-url"', $html);

        $this->getJson(route('contracts.status', $contract))->assertForbidden();
        $this->assertNull($contract->fresh()->provider_signing_url);

        $withUrl = $this->makeSentWithSigningUrl();
        $show = $this->get(route('contracts.show', $withUrl))->assertOk();
        $show->assertSee(self::SAMPLE_URL, false);
        $show->assertSee('id="contract-provider-signing-url"', false);
        $show->assertDontSee('id="syncStatusBtn"', false);

        $this->assertSame($actor->id, auth()->id());
    }

    public function test_viewer_with_view_sees_sms_link_on_card(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeSentWithSigningUrl();

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee(self::SAMPLE_URL, false)
            ->assertSee('id="contract-provider-signing-url"', false);
    }
}
