<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui;

use App\Models\District;
use App\Models\Location;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Единый Select2 multiselect (generic-multiselect): UI-активы, AJAX/non-AJAX контракты, доступ.
 */
final class GenericMultiselectFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName, ?User $user = null): void
    {
        $user ??= $this->user;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantUsersView(User $actor): void
    {
        $this->grantPermission('users.view', $actor);
    }

    public function test_generic_multiselect_partial_contains_unified_select2_implementation(): void
    {
        $path = resource_path('views/partials/select2/generic-multiselect.blade.php');
        $this->assertFileExists($path);

        $source = (string) file_get_contents($path);

        $this->assertStringContainsString('window.KidsCrmGenericMultiselectSelect2', $source);
        $this->assertStringContainsString('closeOnSelect: false', $source);
        $this->assertStringContainsString('.select2-results__option[aria-selected]', $source);
        $this->assertStringContainsString('window.KidsCrmUserStudentTeamsSelect2 = window.KidsCrmGenericMultiselectSelect2', $source);
        $this->assertStringContainsString('.generic-multiselect-field .select2-container--bootstrap-5 .select2-selection.select2-selection--multiple', $source);

        $this->assertStringNotContainsString("on('select2:closing'", $source);
        $this->assertStringNotContainsString(".select2-results__option.select2-results__option--selectable').filter", $source);
    }

    public function test_student_teams_partial_uses_generic_multiselect_class(): void
    {
        $path = resource_path('views/admin/users/_student_teams_multiselect.blade.php');
        $source = (string) file_get_contents($path);

        $this->assertStringContainsString('js-generic-multiselect-select', $source);
        $this->assertStringContainsString('generic-multiselect-field', $source);
        $this->assertStringNotContainsString('js-user-student-teams-select', $source);
        $this->assertStringNotContainsString('teamsSelect2Profile', $source);
    }

    public function test_districts_index_renders_unified_generic_multiselect_assets(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект multiselect smoke',
        ]);

        $html = $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('id="districtCreateLocationIds"', false)
            ->assertSee('id="districtEditLocationIds"', false)
            ->assertSee('js-generic-multiselect-select', false)
            ->assertSee('generic-multiselect-field', false)
            ->assertSee('KidsCrmGenericMultiselectSelect2', false)
            ->assertSee('KidsCrmGenericMultiselectSelect2.init', false)
            ->assertSee('dropdownParent', false)
            ->getContent();

        $this->assertStringNotContainsString('js-user-student-teams-select', $html);
        $this->assertStringNotContainsString('KidsCrmUserStudentTeamsSelect2.init', $html);
    }

    public function test_users_index_renders_unified_generic_multiselect_for_student_teams(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа users multiselect',
        ]);

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('id="createStudentTeamIds"', false)
            ->assertSee('id="editStudentTeamIds"', false)
            ->assertSee('js-generic-multiselect-select', false)
            ->assertSee('KidsCrmGenericMultiselectSelect2.init', false)
            ->assertSee('data-bs-backdrop="static"', false)
            ->getContent();

        $this->assertStringNotContainsString('js-user-student-teams-select', $html);
        $this->assertStringNotContainsString('user-student-teams-multiselect', $html);
    }

    public function test_store_district_ajax_returns_json_with_district_and_message(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'AJAX bind object',
        ]);

        $response = $this->postJson(route('admin.districts.store'), [
            'name' => 'AJAX район',
            'sort_order' => 5,
            'is_enabled' => 1,
            'location_ids' => [$location->id],
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'district' => ['id', 'name']])
            ->assertJsonPath('message', 'Район создан');

        $districtId = (int) $response->json('district.id');
        $this->assertGreaterThan(0, $districtId);
        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'district_id' => $districtId,
        ]);
    }

    public function test_store_district_ajax_validation_returns_422_json(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');

        $this->postJson(route('admin.districts.store'), [
            'name' => '',
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_district_non_ajax_redirects_and_creates_district_with_location_ids(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'NonAJAX bind object',
        ]);

        $payload = [
            'name' => 'NonAJAX район ' . uniqid('', true),
            'sort_order' => 2,
            'is_enabled' => 1,
            'location_ids' => [$location->id],
        ];

        $this->post(route('admin.districts.store'), $payload)
            ->assertRedirect(route('admin.districts.index'));

        $district = District::query()->where('name', $payload['name'])->first();
        $this->assertNotNull($district);
        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'district_id' => $district->id,
        ]);
    }

    public function test_update_district_ajax_returns_json_message_and_syncs_location_ids(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Before AJAX update']);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => null,
        ]);

        $this->putJson(route('admin.districts.update', $district->id), [
            'name' => 'After AJAX update',
            'sort_order' => 4,
            'is_enabled' => 1,
            'location_ids' => [$location->id],
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Район обновлён');

        $this->assertDatabaseHas('districts', [
            'id' => $district->id,
            'name' => 'After AJAX update',
        ]);
        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'district_id' => $district->id,
        ]);
    }

    public function test_update_district_non_ajax_redirects_and_updates_district_with_location_ids(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Before non-AJAX update']);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => null,
        ]);

        $this->put(route('admin.districts.update', $district->id), [
            'name' => 'After non-AJAX update',
            'sort_order' => 6,
            'is_enabled' => 1,
            'location_ids' => [$location->id],
        ])->assertRedirect(route('admin.districts.index'));

        $this->assertDatabaseHas('districts', [
            'id' => $district->id,
            'name' => 'After non-AJAX update',
        ]);
        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'district_id' => $district->id,
        ]);
    }

    public function test_users_store_ajax_with_team_ids_returns_user_and_message(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $roleId = (int) Role::query()->where('name', 'user')->value('id');
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $email = 'multiselect-ajax-' . uniqid('', true) . '@example.test';

        $response = $this->postJson(route('admin.user.store'), [
            'name' => 'Multiselect',
            'lastname' => 'Student',
            'email' => $email,
            'role_id' => $roleId,
            'team_ids' => [$team->id],
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id', 'email']])
            ->assertJsonPath('message', 'Пользователь создан успешно');

        $userId = (int) $response->json('user.id');
        $this->assertGreaterThan(0, $userId);
        $this->assertDatabaseHas('team_user', [
            'user_id' => $userId,
            'team_id' => $team->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_update_location_non_ajax_redirects_and_syncs_team_ids(): void
    {
        $this->asAdmin();
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->put(route('admin.locations.update', $location->id), [
            'name' => $location->name,
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'location_id' => $location->id,
        ]);
    }

    public function test_districts_page_access_with_and_without_permission_and_guest(): void
    {
        $district = District::factory()->forPartner($this->partner->id)->create();

        $allowed = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->grantPermission('districts.view', $allowed);
        $this->actingAs($allowed);

        $this->get(route('admin.districts.index'))->assertOk();
        $this->getJson(route('admin.districts.data', ['draw' => 1]))->assertOk();
        $this->getJson(route('admin.districts.show', $district->id))->assertOk();

        $denied = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('admin.districts.index'))->assertForbidden();
        $this->getJson(route('admin.districts.data', ['draw' => 1]))->assertForbidden();
        $this->postJson(route('admin.districts.store'), ['name' => 'X', 'is_enabled' => 1])->assertForbidden();

        Auth::logout();

        $guestIndex = $this->get(route('admin.districts.index'));
        $this->assertContains($guestIndex->getStatusCode(), [302, 401]);

        $guestData = $this->getJson(route('admin.districts.data', ['draw' => 1]));
        $this->assertContains($guestData->getStatusCode(), [401, 403]);
    }

    public function test_users_page_access_with_and_without_permission_and_guest(): void
    {
        $allowed = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->grantUsersView($allowed);
        $this->actingAs($allowed);

        $this->get(route('admin.user1'))->assertOk();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();

        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('admin.user1'))->assertForbidden();
        $this->getJson('/admin/users/data?draw=1')->assertForbidden();

        Auth::logout();

        $this->get(route('admin.user1'))->assertRedirect();
        $this->getJson('/admin/users/data?draw=1')->assertUnauthorized();
    }
}
