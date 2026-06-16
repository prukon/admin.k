<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к разделу «Заявки с сайта» (вкладки «Заявки» и «Виджет для сайта»)
 * и всем связанным endpoint'ам: при наличии прав — 200, иначе 403.
 */
final class SchoolLeadsSectionFullAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        config([
            'services.telegram.bot_token'    => 'test-token',
            'services.telegram.bot_username' => 'kidscrmLeadFormBot',
        ]);

        $this->lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Секция доступ',
            'phone'      => '+7 900 100-00-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
    }

    public function test_guest_cannot_access_any_section_endpoint(): void
    {
        Auth::logout();

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_school_leads_view_gets_403_on_leads_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolWidget.view');
        $this->actingAs($actor);

        foreach ($this->leadsRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без schoolLeads.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_school_widget_view_gets_403_on_widget_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('schoolWidget.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->actingAs($actor);

        foreach ($this->widgetRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без schoolWidget.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_school_lead_landing_view_gets_403_on_landing_endpoint(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->actingAs($actor);

        foreach ($this->landingRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без schoolLeadLanding.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_only_school_leads_view_leads_page_and_api_return_200_widget_forbidden(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->actingAs($actor);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'leads');

        foreach ($this->leadsRoutesPayload() as $item) {
            $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            )->assertOk();
        }

        $this->get(route('admin.school-leads.widget'))->assertForbidden();
        $this->get(route('admin.school-widget'))->assertForbidden();
        $this->postJson(route('admin.school-widget.telegram-link'))->assertForbidden();
        $this->deleteJson(route('admin.school-widget.telegram-disconnect'))->assertForbidden();
        $this->get(route('admin.school-leads.landing'))->assertForbidden();
    }

    public function test_user_with_only_school_widget_view_widget_page_and_api_return_200_leads_forbidden(): void
    {
        $actor = $this->createUserWithoutPermission('schoolWidget.view', $this->partner);
        $this->grantPermission($actor, 'schoolWidget.view');
        $this->actingAs($actor);

        $this->get(route('admin.school-leads.widget'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'widget');

        $this->get(route('admin.school-widget'))->assertOk();

        $this->postJson(route('admin.school-widget.telegram-link'))
            ->assertOk()
            ->assertJsonStructure(['url', 'message', 'expires_at']);

        $this->get(route('admin.school-leads'))->assertForbidden();

        foreach ($this->leadsRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без schoolLeads.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $this->get(route('admin.school-leads.landing'))->assertForbidden();
    }

    public function test_user_with_only_school_lead_landing_view_landing_page_returns_200_leads_and_widget_forbidden(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'landing');

        $this->get(route('admin.school-leads'))->assertForbidden();
        $this->get(route('admin.school-leads.widget'))->assertForbidden();
        $this->get(route('admin.school-widget'))->assertForbidden();
    }

    public function test_user_with_both_permissions_all_section_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'schoolWidget.view');
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewHas('activeTab', 'leads');

        $this->get(route('admin.school-leads.widget'))
            ->assertOk()
            ->assertViewHas('activeTab', 'widget');

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Оба права: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_all_section_pages_and_endpoints_return_200(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeadLanding.view');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'leads');

        $this->get(route('admin.school-leads.widget'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'widget');

        $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'landing');

        $this->get(route('admin.school-widget'))
            ->assertOk()
            ->assertViewHas('activeTab', 'widget');

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Админ: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_widget_telegram_disconnect_returns_200(): void
    {
        $this->asAdmin();

        $this->partner->school_leads_telegram_chat_id = '999888777';
        $this->partner->save();

        $this->deleteJson(route('admin.school-widget.telegram-disconnect'))
            ->assertOk();
    }

    public function test_admin_school_leads_page_workflow_with_users_and_contracts_returns_200(): void
    {
        $this->asAdmin();

        $leadForUser = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Workflow лид',
            'phone'      => '+7 900 901-01-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => (int) \App\Models\Role::query()->where('is_visible', 1)->orderBy('order_by')->value('id'),
            'is_enabled' => 1,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="slColContract"', false)
            ->assertSee('create-user-from-lead', false);

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Workflow',
            'lastname'       => 'Клиент',
            'role_id'        => $student->role_id,
            'is_enabled'     => 1,
            'school_lead_id' => $leadForUser->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->get(route('contracts.index', ['user_id' => $student->id]))->assertOk();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allSectionRoutesPayload(): array
    {
        return array_merge(
            $this->leadsRoutesPayload(),
            $this->landingRoutesPayload(),
            $this->widgetRoutesPayload(includeDisconnect: false),
        );
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function landingRoutesPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads.landing'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function leadsRoutesPayload(): array
    {
        $locationId = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ])->id;

        $deleteLead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Удалить секция',
            'phone'      => '+7 900 100-00-99',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        return array_merge([
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'        => 1,
                    'start'       => 0,
                    'length'      => 10,
                    'status_ids' => [$this->schoolLeadSystemStatusId(), $this->schoolLeadProcessingStatusId()],
                    'location_id' => (string) $locationId,
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.columns-settings.get'),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.school-leads.columns-settings.save'),
                'data'   => [
                    'columns' => [
                        'name'    => true,
                        'phone'   => true,
                        'status'  => true,
                        'actions' => true,
                    ],
                ],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.school-leads.update', ['schoolLead' => $this->lead->id]),
                'data'   => [
                    'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
                    'comment' => 'Section smoke',
                ],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.school-leads.destroy', ['schoolLead' => $deleteLead->id]),
            ],
        ], $this->schoolLeadStatusManagementRoutesPayload());
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function widgetRoutesPayload(bool $includeDisconnect = true): array
    {
        $routes = [
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads.widget'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('admin.school-widget'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.school-widget.telegram-link'),
            ],
        ];

        if ($includeDisconnect) {
            $routes[] = [
                'method' => 'DELETE',
                'url'    => route('admin.school-widget.telegram-disconnect'),
            ];
        }

        return $routes;
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
