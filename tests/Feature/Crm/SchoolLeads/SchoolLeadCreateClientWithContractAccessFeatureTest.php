<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ: гость / без users.view / без contracts.view нельзя отправить договор вместе с клиентом.
 */
final class SchoolLeadCreateClientWithContractAccessFeatureTest extends SchoolLeadCreateClientWithContractTestCase
{
    public function test_guest_cannot_create_client_with_contract(): void
    {
        Auth::logout();
        $lead = $this->makeLead();
        $template = $this->makeContractTemplate();

        $store = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        );

        $this->assertContains($store->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $store->getStatusCode());
        $this->assertNotSame(500, $store->getStatusCode());
        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_without_contracts_view_send_contract_is_rejected_and_client_is_not_created(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'users.view');

        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['send_contract']);

        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_leads_page_hides_choice_modal_without_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'users.view');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="createClientBtn"', false)
            ->assertDontSee('id="createLeadClientChoiceModal"', false);
    }

    public function test_leads_page_shows_choice_modal_with_contracts_view(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->makeContractTemplate(['title' => 'Видимый шаблон']);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="createLeadClientChoiceModal"', false)
            ->assertSee('id="leadCreateClientModeWithContract"', false)
            ->assertSee('id="leadCreateClientModeWithoutContract"', false)
            ->assertSee('Создать клиента и отправить договор', false)
            ->assertSee('Создать клиента без договора', false)
            ->assertSee('Это действие платное', false)
            ->assertSee('Видимый шаблон', false);
    }

    public function test_guest_cannot_open_leads_page(): void
    {
        Auth::logout();

        $page = $this->get(route('admin.school-leads'));
        $this->assertContains($page->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $page->getStatusCode());
        $this->assertNotSame(500, $page->getStatusCode());
    }

    public function test_manager_without_users_view_gets_403_even_with_send_contract(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'contracts.view');

        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk();
        $this->assertStringNotContainsString('id="createClientBtn"', $page->getContent());
        $this->assertStringNotContainsString('id="createLeadClientChoiceModal"', $page->getContent());

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        )->assertForbidden();

        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_without_contracts_view_can_still_create_client_without_contract(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'users.view');

        $lead = $this->makeLead();

        $store = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract' => 0,
            ]),
            $this->ajaxHeaders()
        );
        $store->assertOk();
        $this->assertNull($store->json('contract_id'));
        $this->assertNotNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_guest_cannot_validate_only_create_client(): void
    {
        Auth::logout();
        $lead = $this->makeLead();

        $store = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'  => 0,
                'validate_only' => 1,
            ]),
            $this->ajaxHeaders()
        );

        $this->assertContains($store->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $store->getStatusCode());
        $this->assertNotSame(500, $store->getStatusCode());
        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_manager_without_users_view_gets_403_on_validate_only(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'contracts.view');

        $lead = $this->makeLead();

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'  => 0,
                'validate_only' => 1,
            ]),
            $this->ajaxHeaders()
        )->assertForbidden();

        $this->assertNull($lead->fresh()->user_id);
    }
}
