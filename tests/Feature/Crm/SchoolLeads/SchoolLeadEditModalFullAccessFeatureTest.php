<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\District;
use App\Models\Location;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: страница «Заявки» и весь функционал объединённой модалки лида
 * (редактирование, создание клиента, встроенное создание договора) → 200 при наличии прав.
 */
final class SchoolLeadEditModalFullAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Modal access lead',
            'phone'                 => '+7 900 101-01-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'child_lastname'        => 'Доступов',
            'child_firstname'       => 'Тест',
        ]);
    }

    private function defaultRoleId(): int
    {
        return (int) Role::query()->where('is_visible', 1)->orderBy('order_by')->value('id');
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

    private function grantSchoolLeadsView(User $actor): void
    {
        $this->grantPermission($actor, 'schoolLeads.view');
    }

    private function actingAsSchoolLeadsViewer(): User
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSchoolLeadsView($actor);

        return $actor;
    }

    public function test_guest_is_denied_on_edit_modal_related_endpoints(): void
    {
        Auth::logout();

        foreach ($this->routesPayload() as $item) {
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

    public function test_user_without_school_leads_view_gets_403_on_edit_modal_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->routesPayload() as $item) {
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

    public function test_viewer_with_school_leads_view_gets_200_on_core_modal_endpoints(): void
    {
        $this->actingAsSchoolLeadsViewer();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertSee('id="editLeadModal"', false)
            ->assertSee('id="leadModalStatusPicker"', false)
            ->assertSee('id="saveLeadBtn"', false)
            ->assertSee('setLeadModalStatusPicker', false)
            ->assertDontSee('id="createContractModal"', false);

        foreach ($this->routesPayload(includeContractEndpoints: false, includeUserStore: false) as $item) {
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
                "Viewer: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_with_users_view_gets_200_on_create_client_workflow(): void
    {
        $actor = $this->actingAsSchoolLeadsViewer();
        $this->grantPermission($actor, 'users.view');

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Create client access',
            'phone'                 => '+7 900 202-02-02',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'child_lastname'        => 'Клиентов',
            'child_firstname'       => 'Новый',
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="createClientBtn"', false);

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Новый',
            'lastname'       => 'Клиентов',
            'role_id'        => $this->defaultRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();
    }

    public function test_viewer_with_contracts_view_gets_200_on_embedded_contract_modal_endpoints(): void
    {
        $actor = $this->actingAsSchoolLeadsViewer();
        $this->grantPermission($actor, 'contracts.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
            'is_enabled' => 1,
            'name'       => 'Договорный',
            'lastname'   => 'Ученик',
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Лид с договором access',
            'phone'                 => '+7 900 303-03-03',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $student->id,
            'child_lastname'        => 'Ученик',
            'child_firstname'       => 'Договорный',
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="createContractModal"', false)
            ->assertSee('id="leadCreateContractBtn"', false)
            ->assertSee('openCreateContractFromLead', false);

        foreach ($this->contractModalRoutesPayload($student) as $item) {
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
                "Contracts viewer: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_all_edit_modal_endpoints_return_200(): void
    {
        $this->asAdmin();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="editLeadModal"', false)
            ->assertSee('id="createContractModal"', false)
            ->assertSee('id="createClientBtn"', false)
            ->assertSee('js-open-create-contract-from-lead', false)
            ->assertSee('if (!row.user_id)', false);

        foreach ($this->routesPayload(includeContractEndpoints: true, includeUserStore: true) as $item) {
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

    public function test_admin_extended_modal_update_payload_returns_200(): void
    {
        $this->asAdmin();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $district = District::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $location = Location::factory()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $district->id,
            'is_enabled'  => true,
        ]);

        $status = $this->createPartnerSchoolLeadStatus(['name' => 'Access modal status']);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $this->lead->id]), [
            'school_lead_status_id'  => $status->id,
            'comment'                => 'Access modal comment',
            'parent_lastname'        => 'Родитель',
            'parent_firstname'       => 'Тест',
            'parent_phone'           => '+7 900 111-22-33',
            'parent_email'           => 'parent-access@example.com',
            'child_lastname'         => 'Ученик',
            'child_firstname'        => 'Тест',
            'child_middlename'       => 'Доступович',
            'child_birthday'         => '2015-04-12',
            'team_id'                => $team->id,
            'district_id'            => $district->id,
            'location_id'            => $location->id,
            'is_individual_traits'   => '1',
            'is_on_medical_register' => '0',
            'is_with_disability'     => '1',
        ])->assertOk();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function routesPayload(
        bool $includeContractEndpoints = true,
        bool $includeUserStore = true,
    ): array {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $district = District::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $location = Location::factory()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $district->id,
            'is_enabled'  => true,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
            'is_enabled' => 1,
            'name'       => 'Contract',
            'lastname'   => 'Student',
        ]);

        $leadForClient = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Store access',
            'phone'                 => '+7 900 404-04-04',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'child_lastname'        => 'Store',
            'child_firstname'       => 'Access',
        ]);

        $routes = [
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 10,
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
                'method' => 'GET',
                'url'    => route('admin.school-leads.statuses.index'),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.school-leads.columns-settings.save'),
                'data'   => [
                    'columns' => [
                        'name'     => true,
                        'status'   => true,
                        'contract' => true,
                        'actions'  => true,
                    ],
                ],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.school-leads.update', ['schoolLead' => $this->lead->id]),
                'data'   => [
                    'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
                    'comment'               => 'Modal access smoke',
                    'child_lastname'        => 'Доступов',
                    'child_firstname'       => 'Тест',
                    'team_id'               => $team->id,
                    'district_id'           => $district->id,
                    'location_id'           => $location->id,
                    'is_individual_traits'  => '0',
                    'is_on_medical_register'=> '1',
                    'is_with_disability'    => '0',
                ],
            ],
        ];

        if ($includeUserStore) {
            $routes[] = [
                'method'  => 'POST',
                'url'     => route('admin.user.store'),
                'data'    => [
                    'name'           => 'Access',
                    'lastname'       => 'Store',
                    'role_id'        => $this->defaultRoleId(),
                    'is_enabled'     => 1,
                    'school_lead_id' => $leadForClient->id,
                ],
                'headers' => [
                    'HTTP_ACCEPT'      => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            ];
        }

        if ($includeContractEndpoints) {
            $routes = array_merge($routes, $this->contractModalRoutesPayload($student));
        }

        return $routes;
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function contractModalRoutesPayload(User $student): array
    {
        return [
            [
                'method' => 'GET',
                'url'    => route('contracts.users.search', ['q' => 'Contract']),
            ],
            [
                'method' => 'GET',
                'url'    => route('contracts.user.group', ['user_id' => $student->id]),
            ],
            [
                'method' => 'POST',
                'url'    => url('/client-contracts/check-balance'),
                'data'   => [],
            ],
        ];
    }
}
