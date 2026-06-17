<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class SchoolLeadCreateUserFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    private function defaultRoleId(): int
    {
        return (int) Role::query()->where('is_visible', 1)->orderBy('order_by')->value('id');
    }

    public function test_store_links_school_lead_when_school_lead_id_provided(): void
    {
        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Мария Иванова',
            'phone'      => '+7 900 111-22-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'Мария Иванова',
            'lastname'       => 'Тестова',
            'role_id'        => $this->defaultRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $userId = (int) $response->json('user.id');
        $this->assertGreaterThan(0, $userId);

        $lead->refresh();
        $this->assertSame($userId, (int) $lead->user_id);
        $this->assertSame('Мария Иванова', User::findOrFail($userId)->name);
    }

    public function test_store_ignores_location_id_and_links_lead(): void
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'С локацией',
            'phone'       => '+7 900 555-55-55',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $location->id,
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'С локацией',
            'lastname'       => 'Клиент',
            'role_id'        => $this->defaultRoleId(),
            'location_id'    => $location->id,
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);
        $this->assertSame($location->id, (int) $lead->fresh()->location_id);
    }

    public function test_store_rejects_already_linked_school_lead(): void
    {
        $existingUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'      => $this->defaultRoleId(),
        ]);

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Занятый лид',
            'phone'      => '+7 900 777-77-77',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'    => $existingUser->id,
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Новый',
            'lastname'       => 'Клиент',
            'role_id'        => $this->defaultRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['school_lead_id']);
    }

    public function test_datatable_includes_create_user_prefill_fields(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Футбол',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Иванова Мария Петровна',
            'phone'                  => '+7 900 111-22-33',
            'parent_lastname'        => 'Иванова',
            'parent_firstname'       => 'Мария',
            'parent_middlename'      => 'Петровна',
            'parent_phone'           => '+7 900 444-44-44',
            'parent_email'           => 'parent@example.com',
            'child_lastname'         => 'Иванов',
            'child_firstname'        => 'Пётр',
            'child_middlename'       => 'Сергеевич',
            'child_birthday'         => '2018-05-10',
            'team_id'                => $team->id,
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

        $this->assertSame('Иванова', $row['parent_lastname']);
        $this->assertSame('Мария', $row['parent_firstname']);
        $this->assertSame('Петровна', $row['parent_middlename']);
        $this->assertSame('Иванов', $row['child_lastname']);
        $this->assertSame('Пётр', $row['child_firstname']);
        $this->assertSame('Сергеевич', $row['child_middlename']);
        $this->assertSame('2018-05-10', $row['child_birthday_iso']);
        $this->assertSame($team->id, (int) $row['team_id']);
        $this->assertSame('parent@example.com', $row['parent_email']);
        $this->assertSame('+7 900 444-44-44', $row['parent_phone']);
        $this->assertTrue($row['is_individual_traits']);
        $this->assertFalse($row['is_on_medical_register']);
        $this->assertTrue($row['is_with_disability']);
    }

    public function test_datatable_includes_user_id(): void
    {
        $linkedUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Связанный',
            'phone'      => '+7 900 888-88-88',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'    => $linkedUser->id,
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Свободный',
            'phone'      => '+7 900 999-99-99',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();

        $rows = collect($response->json('data'));
        $linked = $rows->firstWhere('name', 'Связанный');
        $free = $rows->firstWhere('name', 'Свободный');

        $this->assertNotNull($linked);
        $this->assertSame($linkedUser->id, (int) $linked['user_id']);
        $this->assertNotNull($free);
        $this->assertNull($free['user_id']);
    }

    public function test_store_rejects_superadmin_role_from_school_lead_flow(): void
    {
        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Лид',
            'phone'      => '+7 900 111-22-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Лид',
            'lastname'       => 'Клиент',
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

    public function test_store_copies_health_flags_from_school_lead(): void
    {
        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Особенный ученик',
            'phone'                  => '+7 900 111-22-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'child_lastname'         => 'Иванов',
            'child_firstname'        => 'Пётр',
            'is_individual_traits'   => true,
            'is_on_medical_register' => true,
            'is_with_disability'     => false,
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'Пётр',
            'lastname'       => 'Иванов',
            'role_id'        => $this->defaultRoleId(),
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

    public function test_store_uses_submitted_health_flags_when_provided(): void
    {
        $this->grantUsersOtherUpdatePermission();

        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'С заявкой',
            'phone'                  => '+7 900 222-33-44',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'is_individual_traits'   => true,
            'is_on_medical_register' => true,
            'is_with_disability'     => true,
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'                   => 'С заявкой',
            'lastname'               => 'Клиент',
            'role_id'                => $this->defaultRoleId(),
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

    public function test_school_leads_page_includes_create_user_modal_when_users_view_granted(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="editLeadModal"', false)
            ->assertSee('id="createClientBtn"', false)
            ->assertSee('populateLeadForm', false)
            ->assertSee('forceNewParent', false)
            ->assertDontSee('id="createUserModal"', false)
            ->assertDontSee('create-user-from-lead', false)
            ->assertDontSee('>Суперадмин</option>', false);
    }

    public function test_school_leads_page_hides_create_user_modal_without_users_view(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $denied->role_id,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($denied)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="editLeadModal"', false)
            ->assertDontSee('id="createClientBtn"', false)
            ->assertDontSee('id="createUserModal"', false);
    }

    private function grantUsersOtherUpdatePermission(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('users.other.update'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
