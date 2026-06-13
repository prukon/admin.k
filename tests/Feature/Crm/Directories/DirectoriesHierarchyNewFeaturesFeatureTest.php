<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Directories;

use App\Models\District;
use App\Models\Location;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\LocationAdminUsersSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Функциональное покрытие связей «админ ↔ объект ↔ район ↔ группа»
 * и UI/API, добавленных в иерархии справочников.
 */
final class DirectoriesHierarchyNewFeaturesFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPermissions([
            'districts.view',
            'locations.view',
            'locations.manage',
            'groups.view',
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grantPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createPartnerAdmin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('admin'),
            'is_enabled' => 1,
            'name' => 'Иван',
            'lastname' => 'Админов',
        ], $overrides));
    }

    private function attachAdmins(Location $location, array $adminIds): void
    {
        app(LocationAdminUsersSyncService::class)->syncAdminsForLocation($location, $adminIds);
    }

    public function test_location_admin_binding_full_crud_and_validation(): void
    {
        $admin = $this->createPartnerAdmin(['name' => 'Пётр', 'lastname' => 'Главный']);
        $studentRoleId = $this->roleId('user');
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $studentRoleId,
            'is_enabled' => 1,
        ]);

        $store = $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с админом',
            'admin_user_ids' => [$admin->id],
            'is_enabled' => 1,
        ])->assertOk();

        $locationId = (int) $store->json('location.id');

        $this->getJson(route('admin.locations.show', $locationId))
            ->assertOk()
            ->assertJsonPath('admin_user_ids', [$admin->id]);

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'admin_user_id' => $admin->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $locationId)
            ->assertJsonPath('data.0.admin_user_label', 'Главный Пётр');

        $this->putJson(route('admin.locations.update', $locationId), [
            'name' => 'Объект с админом',
            'admin_user_ids' => [],
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseMissing('location_admin_user', [
            'location_id' => $locationId,
            'user_id' => $admin->id,
        ]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с учеником',
            'admin_user_ids' => [$student->id],
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_user_ids.0']);

        $foreignAdmin = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->roleId('admin'),
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с чужим админом',
            'admin_user_ids' => [$foreignAdmin->id],
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_user_ids.0']);
    }

    public function test_one_admin_can_be_bound_to_multiple_locations(): void
    {
        $admin = $this->createPartnerAdmin();

        $firstId = (int) $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект A',
            'admin_user_ids' => [$admin->id],
            'is_enabled' => 1,
        ])->json('location.id');

        $secondId = (int) $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект B',
            'admin_user_ids' => [$admin->id],
            'is_enabled' => 1,
        ])->json('location.id');

        $this->assertDatabaseHas('location_admin_user', ['location_id' => $firstId, 'user_id' => $admin->id]);
        $this->assertDatabaseHas('location_admin_user', ['location_id' => $secondId, 'user_id' => $admin->id]);
    }

    public function test_teams_filters_by_location_admin_and_district(): void
    {
        $admin = $this->createPartnerAdmin();
        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Северный']);
        $otherDistrict = District::factory()->forPartner($this->partner->id)->create(['name' => 'Южный']);

        $locationWithAdmin = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $district->id,
        ]);
        $this->attachAdmins($locationWithAdmin, [$admin->id]);
        $locationOtherDistrict = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $otherDistrict->id,
        ]);
        $locationWithoutDistrict = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => null,
        ]);

        $teamMatch = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Целевая группа',
            'location_id' => $locationWithAdmin->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locationOtherDistrict->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locationWithoutDistrict->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        $byAdmin = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&admin_user_id=' . $admin->id)
            ->assertOk();
        $this->assertSame([$teamMatch->id], collect($byAdmin->json('data'))->pluck('id')->all());

        $byDistrict = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&district_id=' . $district->id)
            ->assertOk();
        $this->assertSame([$teamMatch->id], collect($byDistrict->json('data'))->pluck('id')->all());

        $this->getJson('/admin/teams/data?draw=1&start=0&length=50&admin_user_id=none')->assertOk();
        $this->getJson('/admin/teams/data?draw=1&start=0&length=50&district_id=none')->assertOk();
    }

    public function test_district_location_multiselect_sync_and_locations_label_column(): void
    {
        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Центр']);
        $otherDistrict = District::factory()->forPartner($this->partner->id)->create(['name' => 'Окраина']);

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект A',
            'district_id' => $district->id,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект B',
            'district_id' => null,
        ]);
        $locC = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект C',
            'district_id' => $otherDistrict->id,
        ]);
        $locD = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект D',
            'district_id' => $district->id,
        ]);
        $locE = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект E',
            'district_id' => $district->id,
        ]);

        $this->getJson(route('admin.districts.show', $district->id))
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('id', $district->id)
                ->has('location_ids')
                ->etc());

        $this->assertEqualsCanonicalizing(
            [$locA->id, $locD->id, $locE->id],
            $this->getJson(route('admin.districts.show', $district->id))->json('location_ids')
        );

        $this->putJson(route('admin.districts.update', $district->id), [
            'name' => 'Центр',
            'sort_order' => 0,
            'is_enabled' => 1,
            'location_ids' => [$locB->id, $locC->id],
        ])->assertOk();

        $this->assertDatabaseHas('locations', ['id' => $locA->id, 'district_id' => null]);
        $this->assertDatabaseHas('locations', ['id' => $locB->id, 'district_id' => $district->id]);
        $this->assertDatabaseHas('locations', ['id' => $locC->id, 'district_id' => $district->id]);
        $this->assertDatabaseHas('locations', ['id' => $locD->id, 'district_id' => null]);
        $this->assertDatabaseHas('locations', ['id' => $locE->id, 'district_id' => null]);

        $data = $this->getJson(route('admin.districts.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->assertOk();

        $row = collect($data->json('data'))->firstWhere('id', $district->id);
        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['locations_count']);
        $this->assertSame('Объект B, Объект C', $row['locations_label']);
        $this->assertSame(['Объект B', 'Объект C'], $row['locations_names']);

        $locF = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект F',
            'district_id' => $district->id,
        ]);

        $this->putJson(route('admin.districts.update', $district->id), [
            'name' => 'Центр',
            'sort_order' => 0,
            'is_enabled' => 1,
            'location_ids' => [
                $locB->id,
                $locC->id,
                $locD->id,
                $locE->id,
                $locF->id,
            ],
        ])->assertOk();

        $truncated = $this->getJson(route('admin.districts.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->assertOk();

        $truncatedRow = collect($truncated->json('data'))->firstWhere('id', $district->id);
        $this->assertSame(5, (int) $truncatedRow['locations_count']);
        $this->assertCount(5, $truncatedRow['locations_names']);
        $this->assertStringContainsString('еще', (string) $truncatedRow['locations_label']);
        $this->assertSame('Объект B', $truncatedRow['locations_names'][0]);
    }

    public function test_district_show_without_locations_view_omits_location_ids(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantPermissionsForUser($actor, ['districts.view']);
        $this->actingAs($actor);

        $district = District::factory()->forPartner($this->partner->id)->create();
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $district->id,
        ]);

        $payload = $this->getJson(route('admin.districts.show', $district->id))
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('location_ids', $payload);
    }

    public function test_district_update_without_locations_view_does_not_change_location_bindings(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantPermissionsForUser($actor, ['districts.view']);
        $this->actingAs($actor);

        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Без sync']);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $district->id,
        ]);

        $this->putJson(route('admin.districts.update', $district->id), [
            'name' => 'Без sync updated',
            'sort_order' => 0,
            'is_enabled' => 1,
            'location_ids' => [],
        ])->assertOk();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'district_id' => $district->id,
        ]);
    }

    public function test_locations_index_renders_admin_and_district_ui(): void
    {
        $admin = $this->createPartnerAdmin(['name' => 'UI', 'lastname' => 'Админ']);
        District::factory()->forPartner($this->partner->id)->create(['name' => 'UI район']);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'UI объект',
        ]);
        $this->attachAdmins($location, [$admin->id]);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="filter-admin"', false)
            ->assertSee('data-column-key="admin_user_label"', false)
            ->assertSee('name="admin_user_ids[]"', false)
            ->assertSee('id="filter-district"', false);
    }

    public function test_districts_index_renders_location_multiselect_and_list_column(): void
    {
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект для колонки',
        ]);

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('id="districtCreateLocationIds"', false)
            ->assertSee('id="districtEditLocationIds"', false)
            ->assertSee('js-generic-multiselect-select', false)
            ->assertSee('data-column-key="locations_label"', false)
            ->assertSee('Объект для колонки', false);
    }

    public function test_teams_index_renders_admin_and_district_filters(): void
    {
        $admin = $this->createPartnerAdmin(['name' => 'Filter', 'lastname' => 'Admin']);
        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Filter district']);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $district->id,
        ]);
        $this->attachAdmins($location, [$admin->id]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('id="filter-admin"', false)
            ->assertSee('id="filter-district"', false);
    }

    public function test_all_new_feature_endpoints_return_200_for_admin(): void
    {
        $admin = $this->createPartnerAdmin();
        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Smoke district']);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $district->id,
        ]);
        $this->attachAdmins($location, [$admin->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $this->get(route('admin.locations.index'))->assertOk();
        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'admin_user_id' => $admin->id,
            'district_id' => $district->id,
        ]))->assertOk()->assertJsonStructure([
            'data' => [['admin_user_label', 'district_name']],
        ]);

        $this->getJson(route('admin.locations.show', $location->id))
            ->assertOk()
            ->assertJsonPath('admin_user_ids', [$admin->id]);

        $this->putJson(route('admin.locations.update', $location->id), [
            'name' => $location->name,
            'admin_user_ids' => [$admin->id],
            'district_id' => $district->id,
            'is_enabled' => 1,
        ])->assertOk();

        $this->get(route('admin.districts.index'))->assertOk();
        $this->getJson(route('admin.districts.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk()->assertJsonStructure([
            'data' => [['locations_label', 'locations_names', 'locations_count']],
        ]);

        $this->getJson(route('admin.districts.show', $district->id))
            ->assertOk()
            ->assertJsonStructure(['location_ids']);

        $this->putJson(route('admin.districts.update', $district->id), [
            'name' => $district->name,
            'sort_order' => 0,
            'is_enabled' => 1,
            'location_ids' => [$location->id],
        ])->assertOk();

        $this->get(route('admin.team.index'))->assertOk();
        foreach ([
            '/admin/teams/data?draw=1&start=0&length=10&admin_user_id=' . $admin->id,
            '/admin/teams/data?draw=1&start=0&length=10&district_id=' . $district->id,
            '/admin/teams/data?draw=1&start=0&length=10&admin_user_id=none&district_id=none&location_id=' . $location->id,
        ] as $url) {
            $this->getJson($url)->assertOk();
        }

        $this->getJson(route('admin.team.edit', $team->id))->assertOk();
    }

    public function test_guest_cannot_access_new_feature_endpoints(): void
    {
        Auth::logout();

        $district = District::factory()->forPartner($this->partner->id)->create();
        $location = Location::factory()->create(['partner_id' => $this->partner->id]);

        $calls = [
            fn () => $this->get(route('admin.locations.index')),
            fn () => $this->getJson(route('admin.locations.data', ['draw' => 1, 'admin_user_id' => 'none'])),
            fn () => $this->get(route('admin.districts.index')),
            fn () => $this->getJson(route('admin.districts.data', ['draw' => 1])),
            fn () => $this->putJson(route('admin.districts.update', $district->id), [
                'name' => 'x', 'is_enabled' => 1, 'location_ids' => [$location->id],
            ]),
            fn () => $this->get('/admin/teams'),
            fn () => $this->getJson('/admin/teams/data?draw=1&admin_user_id=none&district_id=none'),
        ];

        foreach ($calls as $call) {
            $this->assertContains($call()->getStatusCode(), [302, 401, 403]);
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grantPermissionsForUser(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
