<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Создание клиента из заявки: перенос флагов особенностей, запрет superadmin,
 * контроль доступа (страница и endpoint'ы → 200 при наличии прав).
 */
final class SchoolLeadCreateClientFullFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_school_leads_page_roles_view_excludes_superadmin_for_admin(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewHas('roles', function ($roles) use ($superRole) {
                return !$roles->pluck('id')->contains($superRole->id);
            })
            ->assertDontSee('>Суперадмин</option>', false);
    }

    public function test_school_leads_page_roles_view_excludes_superadmin_for_superadmin_actor(): void
    {
        $this->asSuperadmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewHas('roles', function ($roles) use ($superRole) {
                return !$roles->pluck('id')->contains($superRole->id);
            })
            ->assertDontSee('>Суперадмин</option>', false);
    }

    public function test_school_leads_page_shows_health_fields_when_users_other_update_granted(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'users.other.update');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="create-is_individual_traits"', false)
            ->assertSee('id="create-is_on_medical_register"', false)
            ->assertSee('id="create-is_with_disability"', false)
            ->assertSee('Индивидуальные особенности воспитанника', false)
            ->assertSee('Состоит на учёте у медицинских специалистов', false)
            ->assertSee('Наличие инвалидности', false);
    }

    public function test_school_leads_page_hides_health_fields_without_users_other_update(): void
    {
        $actor = $this->createUserWithoutPermission('users.other.update', $this->partner);
        $this->actingAs($actor);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'users.view');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertDontSee('id="create-is_individual_traits"', false);
    }

    public function test_school_leads_page_includes_create_client_javascript_helpers(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'users.other.update');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('prefillCreateUserFromLead', false)
            ->assertSee('setCreateUserHealthFieldsFromLead', false)
            ->assertSee('syncCreateUserHealthFields', false)
            ->assertSee('create-user-from-lead', false);
    }

    public function test_datatable_includes_health_flags_for_create_client_prefill(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');

        SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Особенный лид',
            'phone'                  => '+7 900 333-44-55',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'is_individual_traits'   => true,
            'is_on_medical_register' => false,
            'is_with_disability'     => true,
        ]);

        $row = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->json('data.0');

        $this->assertSame('Особенный лид', $row['name']);
        $this->assertTrue($row['is_individual_traits']);
        $this->assertFalse($row['is_on_medical_register']);
        $this->assertTrue($row['is_with_disability']);
    }

    public function test_create_client_from_lead_copies_health_flags_without_users_other_update_permission(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $lead = $this->createLeadWithHealthFlags(
            individualTraits: true,
            medicalRegister: true,
            disability: false,
        );

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'Пётр',
            'lastname'       => 'Иванов',
            'role_id'        => $this->studentRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $user = User::findOrFail((int) $response->json('user.id'));

        $this->assertTrue($user->is_individual_traits);
        $this->assertTrue($user->is_on_medical_register);
        $this->assertFalse($user->is_with_disability);
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);
    }

    public function test_create_client_from_lead_form_health_flags_override_lead_values(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'users.other.update');

        $lead = $this->createLeadWithHealthFlags(
            individualTraits: true,
            medicalRegister: true,
            disability: true,
        );

        $response = $this->postJson(route('admin.user.store'), [
            'name'                   => 'Анна',
            'lastname'               => 'Смирнова',
            'role_id'                => $this->studentRoleId(),
            'is_enabled'             => 1,
            'school_lead_id'         => $lead->id,
            'is_individual_traits'   => '0',
            'is_on_medical_register' => '',
            'is_with_disability'     => '1',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $user = User::findOrFail((int) $response->json('user.id'));

        $this->assertFalse($user->is_individual_traits);
        $this->assertNull($user->is_on_medical_register);
        $this->assertTrue($user->is_with_disability);
    }

    public function test_create_client_from_lead_rejects_superadmin_role_for_admin(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $lead = $this->createLeadWithHealthFlags();
        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Хакер',
            'lastname'       => 'Тест',
            'role_id'        => $superRole->id,
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);

        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_create_client_from_lead_rejects_superadmin_role_for_superadmin_actor(): void
    {
        $this->asSuperadmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $lead = $this->createLeadWithHealthFlags();
        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Ещё один',
            'lastname'       => 'Супер',
            'role_id'        => $superRole->id,
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);

        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_admin_create_client_from_lead_full_workflow_returns_200(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'users.other.update');

        $lead = $this->createLeadWithHealthFlags(
            individualTraits: true,
            medicalRegister: false,
            disability: true,
        );

        foreach ($this->createClientWorkflowRoutes($lead) as $item) {
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

        $userId = (int) $lead->fresh()->user_id;
        $this->assertGreaterThan(0, $userId);

        $user = User::findOrFail($userId);
        $this->assertTrue($user->is_individual_traits);
        $this->assertFalse($user->is_on_medical_register);
        $this->assertTrue($user->is_with_disability);
    }

    public function test_viewer_with_school_leads_and_users_view_create_client_workflow_returns_200(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'users.view');

        $lead = $this->createLeadWithHealthFlags(
            individualTraits: false,
            medicalRegister: true,
            disability: false,
        );

        $this->get(route('admin.school-leads'))->assertOk();

        $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'Клиент',
            'lastname'       => 'Из лида',
            'role_id'        => $this->studentRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertFalse($user->is_individual_traits);
        $this->assertTrue($user->is_on_medical_register);
        $this->assertFalse($user->is_with_disability);
    }

    public function test_superadmin_actor_create_client_workflow_returns_200_without_assigning_superadmin_role(): void
    {
        $this->asSuperadmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $lead = $this->createLeadWithHealthFlags(
            individualTraits: true,
            medicalRegister: true,
            disability: false,
        );

        $this->get(route('admin.school-leads'))->assertOk();

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'Нормальный',
            'lastname'       => 'Клиент',
            'role_id'        => $this->studentRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertSame($this->studentRoleId(), (int) $user->role_id);
        $this->assertTrue($user->is_individual_traits);
        $this->assertTrue($user->is_on_medical_register);
        $this->assertFalse($user->is_with_disability);
    }

    public function test_viewer_without_users_view_cannot_create_client_from_lead(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->grantPermission($actor, 'schoolLeads.view');

        $lead = $this->createLeadWithHealthFlags();

        $this->get(route('admin.school-leads'))->assertOk();

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Запрет',
            'lastname'       => 'Store',
            'role_id'        => $this->studentRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();

        $this->assertNull($lead->fresh()->user_id);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function createClientWorkflowRoutes(SchoolLead $lead): array
    {
        return [
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
                'method'  => 'POST',
                'url'     => route('admin.user.store'),
                'data'    => [
                    'name'                   => 'Workflow',
                    'lastname'               => 'Клиент',
                    'role_id'                => $this->studentRoleId(),
                    'is_enabled'             => 1,
                    'school_lead_id'         => $lead->id,
                    'is_individual_traits'   => '1',
                    'is_on_medical_register' => '0',
                    'is_with_disability'     => '1',
                ],
                'headers' => [
                    'HTTP_ACCEPT'      => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 10,
                ]),
            ],
        ];
    }

    private function createLeadWithHealthFlags(
        bool $individualTraits = false,
        bool $medicalRegister = false,
        bool $disability = false,
    ): SchoolLead {
        return SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Лид ' . uniqid(),
            'phone'                  => '+7 900 ' . random_int(100, 999) . '-' . random_int(10, 99) . '-' . random_int(10, 99),
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'child_lastname'         => 'Тестов',
            'child_firstname'        => 'Ученик',
            'is_individual_traits'   => $individualTraits,
            'is_on_medical_register' => $medicalRegister,
            'is_with_disability'     => $disability,
        ]);
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
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
