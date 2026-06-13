<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Locations;

use App\Models\Location;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Crm\CrmTestCase;

final class LocationAdminUserFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPartnerAdmin(array $overrides = []): User
    {
        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');

        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => $adminRoleId,
            'is_enabled' => 1,
            'name' => 'Иван',
            'lastname' => 'Админов',
        ], $overrides));
    }

    public function test_schema_has_admin_user_id_on_locations(): void
    {
        $this->assertTrue(Schema::hasColumn('locations', 'admin_user_id'));
    }

    public function test_index_renders_admin_filter_and_column(): void
    {
        $this->grantPermission('locations.view');

        $admin = $this->createPartnerAdmin(['name' => 'Пётр', 'lastname' => 'Главный']);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="filter-admin"', false)
            ->assertSee('data-column-key="admin_user_label"', false)
            ->assertSee('Главный Пётр', false);
    }

    public function test_store_and_update_persist_admin_user_id_with_validation(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $admin = $this->createPartnerAdmin();
        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $studentRoleId,
            'is_enabled' => 1,
        ]);

        $store = $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с админом',
            'admin_user_id' => $admin->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $store->assertOk();
        $locationId = (int) $store->json('location.id');

        $this->assertDatabaseHas('locations', [
            'id' => $locationId,
            'admin_user_id' => $admin->id,
        ]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с учеником',
            'admin_user_id' => $student->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_user_id']);

        $anotherAdmin = $this->createPartnerAdmin(['name' => 'Сергей', 'lastname' => 'Второй']);

        $this->putJson(route('admin.locations.update', ['location' => $locationId]), [
            'name' => 'Объект с админом',
            'admin_user_id' => $anotherAdmin->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('locations', [
            'id' => $locationId,
            'admin_user_id' => $anotherAdmin->id,
        ]);
    }

    public function test_one_admin_can_manage_multiple_locations(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $admin = $this->createPartnerAdmin();

        $first = $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект A',
            'admin_user_id' => $admin->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $second = $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект B',
            'admin_user_id' => $admin->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertDatabaseHas('locations', [
            'id' => (int) $first->json('location.id'),
            'admin_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('locations', [
            'id' => (int) $second->json('location.id'),
            'admin_user_id' => $admin->id,
        ]);
    }

    public function test_data_filters_by_admin_user_id(): void
    {
        $this->grantPermission('locations.view');

        $admin = $this->createPartnerAdmin(['name' => 'Анна', 'lastname' => 'Куратор']);

        $withAdmin = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'С админом',
            'admin_user_id' => $admin->id,
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Без админа',
            'admin_user_id' => null,
        ]);

        $byAdmin = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'admin_user_id' => $admin->id,
        ]))->assertOk();

        $this->assertSame(1, (int) $byAdmin->json('recordsFiltered'));
        $this->assertSame($withAdmin->id, (int) $byAdmin->json('data.0.id'));
        $this->assertSame('Куратор Анна', $byAdmin->json('data.0.admin_user_label'));

        $withoutAdmin = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'admin_user_id' => 'none',
        ]))->assertOk();

        $this->assertSame(1, (int) $withoutAdmin->json('recordsFiltered'));
        $this->assertSame('', $withoutAdmin->json('data.0.admin_user_label'));
    }

    public function test_teams_data_filters_by_location_admin_user_id(): void
    {
        $this->grantPermission('groups.view');
        $this->grantPermission('locations.view');

        $admin = $this->createPartnerAdmin();

        $locationWithAdmin = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'admin_user_id' => $admin->id,
        ]);
        $locationWithoutAdmin = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'admin_user_id' => null,
        ]);

        $teamWithAdminLocation = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа с админом объекта',
            'location_id' => $locationWithAdmin->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа без админа объекта',
            'location_id' => $locationWithoutAdmin->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа без объекта',
            'location_id' => null,
        ]);

        $byAdmin = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&admin_user_id=' . $admin->id)
            ->assertOk();

        $ids = collect($byAdmin->json('data'))->pluck('id')->all();
        $this->assertSame([$teamWithAdminLocation->id], $ids);

        $withoutAdmin = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&admin_user_id=none')
            ->assertOk();

        $noneIds = collect($withoutAdmin->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertCount(2, $noneIds);
    }

    public function test_teams_data_filters_by_location_district_id(): void
    {
        $this->grantPermission('groups.view');
        $this->grantPermission('locations.view');

        $district = \App\Models\District::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Северный',
        ]);
        $otherDistrict = \App\Models\District::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Южный',
        ]);

        $locationInDistrict = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $district->id,
        ]);
        $locationOtherDistrict = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $otherDistrict->id,
        ]);
        $locationWithoutDistrict = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => null,
        ]);

        $teamInDistrict = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа в Северном',
            'location_id' => $locationInDistrict->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа в Южном',
            'location_id' => $locationOtherDistrict->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа без района',
            'location_id' => $locationWithoutDistrict->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа без объекта',
            'location_id' => null,
        ]);

        $byDistrict = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&district_id=' . $district->id)
            ->assertOk();

        $this->assertSame([$teamInDistrict->id], collect($byDistrict->json('data'))->pluck('id')->all());

        $withoutDistrict = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&district_id=none')
            ->assertOk();

        $this->assertCount(2, collect($withoutDistrict->json('data')));
    }

    public function test_show_returns_admin_user_id(): void
    {
        $this->grantPermission('locations.view');

        $admin = $this->createPartnerAdmin();
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'admin_user_id' => $admin->id,
        ]);

        $this->getJson(route('admin.locations.show', ['location' => $location->id]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('admin_user_id', $admin->id);
    }
}
