<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\Role;
use App\Models\SchoolLead;
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
            'status'     => 'new',
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

    public function test_store_prefills_location_from_lead_via_validation(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'С локацией',
            'phone'       => '+7 900 555-55-55',
            'status'      => 'new',
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
        $this->assertSame($location->id, (int) $user->location_id);
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);
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
            'status'     => 'new',
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
            'status'     => 'new',
            'user_id'    => $linkedUser->id,
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Свободный',
            'phone'      => '+7 900 999-99-99',
            'status'     => 'new',
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

    public function test_school_leads_page_includes_create_user_modal_when_users_view_granted(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="createUserModal"', false)
            ->assertSee('create-user-from-lead', false);
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
            ->assertDontSee('id="createUserModal"', false);
    }
}
