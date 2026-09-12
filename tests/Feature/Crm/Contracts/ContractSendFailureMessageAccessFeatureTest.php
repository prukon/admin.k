<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к тексту ошибки договора: гость, без права, тренер, чужая школа, без организации.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractSendFailureMessageAccessFeatureTest extends ContractSendFailureMessageTestCase
{
    public function test_guest_cannot_see_failed_contract_show_or_list_data(): void
    {
        $contract = $this->makeFailedContractWithProviderMessage();

        Auth::logout();

        $show = $this->get(route('contracts.show', $contract), ['HTTP_ACCEPT' => 'text/html']);
        $this->assertNotSame(200, $show->getStatusCode());
        $this->assertNotSame(500, $show->getStatusCode());
        $show->assertStatus(302);
        $show->assertDontSee(self::PROVIDER_ERROR, false);
        $show->assertDontSee('id="contract-status-error"', false);

        $this->getJson('/client-contracts/data?draw=1&start=0&length=20')->assertStatus(401);
        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())->assertStatus(401);
    }

    public function test_guest_native_post_send_redirects_and_does_not_mark_failed(): void
    {
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonAddDocumentRejected();
        $contract = $this->makeDraftContract();

        Auth::logout();
        $web = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode());
        $this->assertContains($web->getStatusCode(), [302, 401, 403, 419]);
        $web->assertDontSee(self::PROVIDER_ERROR, false);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame(0, ContractEvent::query()->where('contract_id', $contract->id)->where('type', 'sent')->count());
        $this->assertSame(0, ContractEvent::query()->where('contract_id', $contract->id)->where('type', 'failed')->count());
    }

    public function test_without_contracts_view_failed_card_and_data_are_forbidden(): void
    {
        $contract = $this->makeFailedContractWithProviderMessage();
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $show = $this->get(route('contracts.show', $contract));
        $show->assertStatus(403);
        $show->assertDontSee(self::PROVIDER_ERROR, false);
        $show->assertDontSee('id="contract-status-error"', false);

        $this->getJson('/client-contracts/data?draw=1&start=0&length=20')->assertStatus(403);

        $send = $this->postJson(route('contracts.send', $contract), $this->validSendPayload());
        $send->assertStatus(403);
        $send->assertDontSee(self::PROVIDER_ERROR, false);

        $native = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.send', $contract), $this->validSendPayload());
        $native->assertForbidden();
        $native->assertDontSee(self::PROVIDER_ERROR, false);
    }

    public function test_trainer_cannot_open_failed_card_or_send(): void
    {
        $contract = $this->makeFailedContractWithProviderMessage();
        $trainer = $this->createUserWithRole('trainer');

        $this->actingAs($trainer)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $show = $this->get(route('contracts.show', $contract));
        $show->assertForbidden();
        $show->assertDontSee(self::PROVIDER_ERROR, false);

        $this->getJson('/client-contracts/data?draw=1&start=0&length=20')->assertForbidden();
        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())->assertForbidden();

        $contract->refresh();
        $this->assertSame(Contract::STATUS_FAILED, $contract->status);
    }

    public function test_foreign_partner_cannot_see_failed_contract_or_its_error(): void
    {
        $contract = $this->makeFailedContractWithProviderMessage();

        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true]);
        $this->grantPermissionToRoleForPartner($this->foreignUser->role_id, $this->foreignPartner->id, self::PERM_CONTRACTS_VIEW);

        $show = $this->get(route('contracts.show', $contract));
        $this->assertContains($show->getStatusCode(), [403, 404]);
        $show->assertDontSee(self::PROVIDER_ERROR, false);
        $show->assertDontSee('id="contract-status-error"', false);

        $ids = collect($this->getJson('/client-contracts/data?draw=1&start=0&length=20')->json('data'))
            ->pluck('id')
            ->all();
        $this->assertNotContains($contract->id, $ids);
    }

    public function test_foreign_school_cannot_send_and_does_not_change_status(): void
    {
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonAddDocumentRejected();
        $contract = $this->makeDraftContract();

        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true]);
        $this->grantPermissionToRoleForPartner($this->foreignUser->role_id, $this->foreignPartner->id, self::PERM_CONTRACTS_VIEW);

        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())->assertForbidden();

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertNull($contract->lastFailureMessage());
        $this->assertSame(0, ContractEvent::query()->where('contract_id', $contract->id)->count());
    }

    public function test_user_without_organization_cannot_open_failed_card(): void
    {
        $contract = $this->makeFailedContractWithProviderMessage();
        $actor = User::factory()->create(['partner_id' => null]);

        $this->actingAs($actor)->withSession([]);

        $response = $this->from(route('login'))->get(route('contracts.show', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $response->assertDontSee(self::PROVIDER_ERROR, false);
    }

    public function test_viewer_sees_failed_card_and_list_row(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeFailedContractWithProviderMessage();

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee(self::PROVIDER_ERROR, false);

        $row = $this->listRowFor($contract);
        $this->assertIsArray($row);
        $this->assertSame(Contract::STATUS_FAILED, $row['status']);
        $this->assertSame(self::PROVIDER_ERROR, $row['status_error']);
    }
}
