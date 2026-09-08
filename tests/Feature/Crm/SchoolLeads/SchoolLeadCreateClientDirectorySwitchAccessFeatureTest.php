<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use Illuminate\Support\Facades\Auth;

/**
 * Гость / без прав / со правами для модалки лида: создание клиента и PUT заявки.
 */
final class SchoolLeadCreateClientDirectorySwitchAccessFeatureTest extends SchoolLeadCreateClientDirectorySwitchTestCase
{
    public function test_guest_cannot_open_leads_or_create_client_from_lead(): void
    {
        Auth::logout();

        $lead = $this->makeLead();
        $directory = $this->makeDirectoryParent();

        $page = $this->get(route('admin.school-leads'));
        $this->assertContains($page->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $page->getStatusCode());
        $this->assertNotSame(200, $page->getStatusCode());

        $store = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'    => $directory->id,
                'parent_email' => $directory->email,
            ]),
            $this->ajaxHeaders()
        );
        $this->assertContains($store->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $store->getStatusCode());
        $this->assertNotSame(500, $store->getStatusCode());
        $this->assertNull($lead->fresh()->user_id);

        $put = $this->putJson(
            route('admin.school-leads.update', $lead),
            $this->leadPutPayload($lead),
            $this->ajaxHeaders()
        );
        $this->assertContains($put->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $put->getStatusCode());
    }

    public function test_manager_without_users_view_gets_403_on_create_client(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');

        $lead = $this->makeLead();
        $directory = $this->makeDirectoryParent();

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk();
        $this->assertStringNotContainsString('id="createClientBtn"', $page->getContent());

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'    => $directory->id,
                'parent_email' => $directory->email,
            ]),
            $this->ajaxHeaders()
        )->assertForbidden();

        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_manager_without_school_leads_view_gets_403_on_leads_page_and_put(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'users.view');

        $lead = $this->makeLead();

        $this->get(route('admin.school-leads'))->assertForbidden();
        $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertForbidden();

        $this->putJson(
            route('admin.school-leads.update', $lead),
            $this->leadPutPayload($lead),
            $this->ajaxHeaders()
        )->assertForbidden();
    }

    public function test_authorized_user_opens_leads_with_hidden_toast_and_can_create_client_from_directory(): void
    {
        $this->actingAsLeadsAndUsersViewer();
        $this->makeDirectoryParent();

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringContainsString('id="editLeadModal"', $html);
        $this->assertStringContainsString('id="createClientBtn"', $html);
        $this->assertStringContainsString('Из справочника', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="kidsMainToast"[^>]*\bshow\b/',
            $html,
            'При первом открытии всплывайка не должна быть уже показана'
        );

        $lead = $this->makeLead();
        $directory = $this->makeDirectoryParent();

        $store = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'        => $directory->id,
                'parent_email'     => $directory->email,
                'parent_lastname'  => $directory->lastname,
                'parent_firstname' => $directory->firstname,
            ]),
            $this->ajaxHeaders()
        );
        $this->assertNotSame(500, $store->getStatusCode());
        $store->assertOk();
        $this->assertNotSame('', trim((string) $store->getContent()));
        $this->assertGreaterThan(0, (int) $store->json('user.id'));

        $search = $this->getJson(
            route('admin.users.parents.search', ['q' => $directory->lastname]),
            $this->ajaxHeaders()
        );
        $search->assertOk();
        $this->assertNotSame('', trim((string) $search->getContent()));
    }

    public function test_manager_without_users_view_cannot_search_directory_parents(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');

        $this->getJson(
            route('admin.users.parents.search', ['q' => 'Справочников']),
            $this->ajaxHeaders()
        )->assertForbidden();
    }
}
