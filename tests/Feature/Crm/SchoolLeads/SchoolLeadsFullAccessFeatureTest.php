<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: страница и все endpoint'ы раздела «Заявки с сайта» отдают 200 при наличии schoolLeads.view.
 * Связанные сценарии (создание клиента из лида, переход к договору) — при users.view / contracts.view.
 */
final class SchoolLeadsFullAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Доступный лид',
            'phone'      => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
    }

    private function grantSchoolLeadsView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantLocationsView(User $actor): void
    {
        $this->grantPermission($actor, 'locations.view');
    }

    private function grantUsersView(User $actor): void
    {
        $this->grantPermission($actor, 'users.view');
    }

    private function grantContractsView(User $actor): void
    {
        $this->grantPermission($actor, 'contracts.view');
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

    private function defaultRoleId(): int
    {
        return (int) Role::query()->where('is_visible', 1)->orderBy('order_by')->value('id');
    }

    private function actingAsSchoolLeadsViewer(bool $withLocationsView = false): User
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSchoolLeadsView($actor);

        if ($withLocationsView) {
            $this->grantLocationsView($actor);
        }

        return $actor;
    }

    public function test_guest_is_denied_on_all_school_leads_endpoints(): void
    {
        Auth::logout();

        $routes = $this->authorizedRoutesPayload();

        foreach ($routes as $item) {
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

    public function test_user_without_permission_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->authorizedRoutesPayload() as $item) {
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

    public function test_viewer_without_locations_permission_all_endpoints_return_200(): void
    {
        $this->actingAsSchoolLeadsViewer(withLocationsView: false);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'leads')
            ->assertSee('Заявки с сайта', false)
            ->assertSee('payments-report-title', false)
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false)
            ->assertSee("linkClass: 'edit-lead'", false)
            ->assertDontSee('id="sl-filter-location"', false);

        $this->getJson(route('logs.data.school-lead', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'status_ids' => [$this->schoolLeadSystemStatusId(), $this->schoolLeadProcessingStatusId()],
        ]))->assertOk();

        $this->getJson(route('admin.school-leads.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [
                'name'    => true,
                'phone'   => true,
                'utm'     => true,
                'status'  => true,
                'actions' => true,
            ],
        ])->assertOk();

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $this->lead->id]), [
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
            'comment' => 'OK',
        ])->assertOk();

        $tempLead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'На удаление',
            'phone'      => '+7 900 999-99-99',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $tempLead->id]))
            ->assertOk();
    }

    public function test_viewer_with_locations_permission_all_endpoints_return_200(): void
    {
        $this->actingAsSchoolLeadsViewer(withLocationsView: true);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Доступная локация',
            'is_enabled' => true,
        ]);

        $this->lead->update(['location_id' => $location->id]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-location"', false)
            ->assertSee('Доступная локация', false)
            ->assertSee('id="leadLocation"', false)
            ->assertSee('historyModal', false);

        $this->getJson(route('logs.data.school-lead', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'status_ids' => [$this->schoolLeadSystemStatusId()],
            'location_id' => (string) $location->id,
            'search'      => ['value' => 'Доступный'],
        ]))->assertOk();

        $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'location_id' => 'none',
            'order'       => [['column' => 3, 'dir' => 'asc']],
            'columns'     => [
                ['data' => 'id'],
                ['data' => 'name'],
                ['data' => 'phone'],
                ['data' => 'location_name'],
                ['data' => 'utm_summary'],
                ['data' => 'page_url'],
                ['data' => 'status_label'],
                ['data' => 'comment'],
            ],
        ]))->assertOk();

        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [
                'name'     => true,
                'phone'    => true,
                'location' => true,
                'utm'      => false,
                'page_url' => true,
                'status'   => true,
                'comment'  => false,
                'actions'  => true,
            ],
        ])->assertOk();

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $this->lead->id]), [
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
            'comment'     => 'С локацией',
            'location_id' => $location->id,
        ])->assertOk();

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $this->lead->id]), [
            'location_id' => null,
        ])->assertOk();
    }

    public function test_admin_all_school_leads_endpoints_return_200(): void
    {
        $this->asAdmin();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        foreach ($this->authorizedRoutesPayload($location->id) as $item) {
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

    public function test_admin_school_leads_page_and_client_contract_workflows_return_200(): void
    {
        $this->asAdmin();

        $leadForUser = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Лид для клиента',
            'phone'                  => '+7 900 801-01-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'is_individual_traits'   => true,
            'is_on_medical_register' => false,
            'is_with_disability'     => true,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
            'is_enabled' => 1,
            'name'       => 'Ученик',
            'lastname'   => 'Договорный',
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Лид с договором',
            'phone'      => '+7 900 802-02-02',
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
            'user_id'    => $student->id,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="slColContract"', false)
            ->assertSee('id="createClientBtn"', false)
            ->assertSee('Создать договор', false);

        foreach ($this->workflowRoutesPayload($leadForUser, $student) as $item) {
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
                "Админ workflow: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_with_school_leads_users_and_contracts_permissions_all_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSchoolLeadsView($actor);
        $this->grantUsersView($actor);
        $this->grantContractsView($actor);

        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Полный доступ лид',
            'phone'                  => '+7 900 803-03-03',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'is_individual_traits'   => true,
            'is_on_medical_register' => true,
            'is_with_disability'     => false,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
            'is_enabled' => 1,
        ]);

        $this->get(route('admin.school-leads'))->assertOk();

        foreach ($this->authorizedRoutesPayload() as $item) {
            $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            )->assertOk();
        }

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Полный',
            'lastname'       => 'Доступ',
            'role_id'        => $this->defaultRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
            'parent_email'   => $this->schoolLeadClientParentEmail(),
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $createdUserId = (int) $lead->fresh()->user_id;
        $this->assertGreaterThan(0, $createdUserId);

        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->is_individual_traits);
        $this->assertTrue($createdUser->is_on_medical_register);
        $this->assertFalse($createdUser->is_with_disability);

        $this->get(route('contracts.index', ['user_id' => $student->id]))->assertOk();
    }

    public function test_admin_create_client_from_lead_with_health_flags_workflow_returns_200(): void
    {
        $this->asAdmin();

        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Health workflow',
            'phone'                  => '+7 900 806-06-06',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'is_individual_traits'   => false,
            'is_on_medical_register' => true,
            'is_with_disability'     => false,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="createClientBtn"', false);

        $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'Health',
            'lastname'       => 'Workflow',
            'role_id'        => $this->defaultRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
            'parent_email'   => $this->schoolLeadClientParentEmail(),
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertFalse($user->is_individual_traits);
        $this->assertTrue($user->is_on_medical_register);
        $this->assertFalse($user->is_with_disability);
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);
    }

    public function test_viewer_with_users_view_but_without_contracts_view_datatable_omits_contract_fields(): void
    {
        $actor = $this->actingAsSchoolLeadsViewer(withLocationsView: false);
        $this->grantUsersView($actor);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Без contracts.view',
            'phone'      => '+7 900 804-04-04',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'    => $user->id,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="slColContract"', false);

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->json('data'))->firstWhere('name', 'Без contracts.view');

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('latest_contract', $row);
        $this->assertArrayNotHasKey('create_contract_url', $row);
    }

    public function test_viewer_without_users_view_cannot_store_user_from_lead(): void
    {
        $actor = $this->actingAsSchoolLeadsViewer(withLocationsView: false);

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Запрет store',
            'phone'      => '+7 900 805-05-05',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->get(route('admin.school-leads'))->assertOk();

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Запрет',
            'lastname'       => 'Store',
            'role_id'        => $this->defaultRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
            'parent_email'   => $this->schoolLeadClientParentEmail(),
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();
    }

    public function test_foreign_partner_lead_endpoints_return_not_found(): void
    {
        $this->asAdmin();

        $foreignLead = SchoolLead::create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'Чужой',
            'phone'      => '+7 900 000-00-00',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $foreignLead->id]), [
            'school_lead_status_id' => $this->schoolLeadSpamStatusId(),
        ])->assertNotFound();

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $foreignLead->id]))
            ->assertNotFound();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function authorizedRoutesPayload(?int $locationId = null): array
    {
        $locationId ??= Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ])->id;

        $deleteLead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Удалить',
            'phone'      => '+7 900 555-55-55',
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
                'method' => 'GET',
                'url'    => route('logs.data.school-lead', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.school-leads.columns-settings.save'),
                'data'   => [
                    'columns' => [
                        'name'     => true,
                        'phone'    => true,
                        'location' => true,
                        'status'   => true,
                        'actions'  => true,
                    ],
                ],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.school-leads.update', ['schoolLead' => $this->lead->id]),
                'data'   => [
                    'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
                    'comment'     => 'Smoke',
                    'location_id' => $locationId,
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
    private function workflowRoutesPayload(
        SchoolLead $leadForUser,
        User $student,
    ): array {
        return [
            [
                'method'  => 'POST',
                'url'     => route('admin.user.store'),
                'data'    => [
                    'name'           => 'Лид для клиента',
                    'lastname'       => 'Создан',
                    'role_id'        => $this->defaultRoleId(),
                    'is_enabled'     => 1,
                    'school_lead_id' => $leadForUser->id,
                    'parent_email'   => $this->schoolLeadClientParentEmail(),
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'GET',
                'url'     => route('contracts.index', ['user_id' => $student->id]),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 20,
                ]),
            ],
        ];
    }
}
