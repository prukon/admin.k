<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Locations;

use App\Models\Location;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\LocationAdminUsersSyncService;
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

    private function attachAdmins(Location $location, array $adminIds): void
    {
        app(LocationAdminUsersSyncService::class)->syncAdminsForLocation($location, $adminIds);
    }

    public function test_schema_has_location_admin_user_pivot(): void
    {
        $this->assertTrue(Schema::hasTable('location_admin_user'));
        $this->assertFalse(Schema::hasColumn('locations', 'admin_user_id'));
    }

    public function test_index_renders_admin_filter_and_column(): void
    {
        $this->grantPermission('locations.view');

        $admin = $this->createPartnerAdmin(['name' => 'Пётр', 'lastname' => 'Главный']);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="filter-admin"', false)
            ->assertSee('data-column-key="admin_user_label"', false)
            ->assertSee('name="admin_user_ids[]"', false)
            ->assertSee('Главный Пётр', false);
    }

    public function test_store_and_update_persist_admin_user_ids_with_validation(): void
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
            'admin_user_ids' => [$admin->id],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $store->assertOk();
        $locationId = (int) $store->json('location.id');

        $this->assertDatabaseHas('location_admin_user', [
            'location_id' => $locationId,
            'user_id' => $admin->id,
            'partner_id' => $this->partner->id,
        ]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с учеником',
            'admin_user_ids' => [$student->id],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_user_ids.0']);

        $anotherAdmin = $this->createPartnerAdmin(['name' => 'Сергей', 'lastname' => 'Второй']);

        $this->putJson(route('admin.locations.update', ['location' => $locationId]), [
            'name' => 'Объект с админом',
            'admin_user_ids' => [$anotherAdmin->id],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('location_admin_user', [
            'location_id' => $locationId,
            'user_id' => $anotherAdmin->id,
        ]);
        $this->assertDatabaseMissing('location_admin_user', [
            'location_id' => $locationId,
            'user_id' => $admin->id,
        ]);
    }

    public function test_store_rejects_more_than_five_admins(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $adminIds = [];
        for ($i = 0; $i < 6; $i++) {
            $adminIds[] = $this->createPartnerAdmin(['name' => 'Админ' . $i, 'lastname' => 'Тестов'])->id;
        }

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с шестью админами',
            'admin_user_ids' => $adminIds,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_user_ids']);
    }

    public function test_one_admin_can_manage_multiple_locations(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $admin = $this->createPartnerAdmin();

        $first = $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект A',
            'admin_user_ids' => [$admin->id],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $second = $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект B',
            'admin_user_ids' => [$admin->id],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertDatabaseHas('location_admin_user', [
            'location_id' => (int) $first->json('location.id'),
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('location_admin_user', [
            'location_id' => (int) $second->json('location.id'),
            'user_id' => $admin->id,
        ]);
    }

    public function test_location_can_have_multiple_admins(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $firstAdmin = $this->createPartnerAdmin(['name' => 'Первый', 'lastname' => 'Админ']);
        $secondAdmin = $this->createPartnerAdmin(['name' => 'Второй', 'lastname' => 'Админ']);

        $store = $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с двумя админами',
            'admin_user_ids' => [$firstAdmin->id, $secondAdmin->id],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $locationId = (int) $store->json('location.id');

        $this->assertDatabaseHas('location_admin_user', [
            'location_id' => $locationId,
            'user_id' => $firstAdmin->id,
        ]);
        $this->assertDatabaseHas('location_admin_user', [
            'location_id' => $locationId,
            'user_id' => $secondAdmin->id,
        ]);
    }

    public function test_data_filters_by_admin_user_id(): void
    {
        $this->grantPermission('locations.view');

        $admin = $this->createPartnerAdmin(['name' => 'Анна', 'lastname' => 'Куратор']);

        $withAdmin = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'С админом',
        ]);
        $this->attachAdmins($withAdmin, [$admin->id]);

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Без админа',
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
        ]);
        $this->attachAdmins($locationWithAdmin, [$admin->id]);

        $locationWithoutAdmin = Location::factory()->create([
            'partner_id' => $this->partner->id,
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

    public function test_show_returns_admin_user_ids(): void
    {
        $this->grantPermission('locations.view');

        $admin = $this->createPartnerAdmin();
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
        ]);
        $this->attachAdmins($location, [$admin->id]);

        $this->getJson(route('admin.locations.show', ['location' => $location->id]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('admin_user_ids', [$admin->id]);
    }

    public function test_index_renders_admin_multiselect_in_create_and_edit_modals(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $this->createPartnerAdmin(['name' => 'Модал', 'lastname' => 'Тест']);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="locationCreateAdminUserIds"', false)
            ->assertSee('id="locationEditAdminUserIds"', false)
            ->assertSee('js-generic-multiselect-select', false)
            ->assertSee('data-placeholder="Выберите администраторов"', false);
    }

    public function test_data_returns_full_label_for_two_admins(): void
    {
        $this->grantPermission('locations.view');

        $first = $this->createPartnerAdmin(['name' => 'Иван', 'lastname' => 'Альфа']);
        $second = $this->createPartnerAdmin(['name' => 'Пётр', 'lastname' => 'Бета']);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Два админа',
        ]);
        $this->attachAdmins($location, [$first->id, $second->id]);

        $response = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $location->id);
        $this->assertNotNull($row);
        $this->assertSame('Альфа Иван, Бета Пётр', $row['admin_user_label']);
        $this->assertSame('Альфа Иван, Бета Пётр', $row['admin_user_label_full']);
        $this->assertSame(['Альфа Иван', 'Бета Пётр'], $row['admin_user_names']);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $row['admin_user_ids']);
    }

    public function test_data_truncates_label_when_three_or_more_admins(): void
    {
        $this->grantPermission('locations.view');

        $admins = [];
        foreach ([
            ['lastname' => 'Альфа', 'name' => 'Первый'],
            ['lastname' => 'Бета', 'name' => 'Второй'],
            ['lastname' => 'Гамма', 'name' => 'Третий'],
        ] as $attrs) {
            $admins[] = $this->createPartnerAdmin($attrs);
        }

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Три админа',
        ]);
        $this->attachAdmins($location, array_map(fn (User $u) => $u->id, $admins));

        $row = collect($this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->json('data'))->firstWhere('id', $location->id);

        $this->assertNotNull($row);
        $this->assertSame('Альфа Первый, еще 2 шт.', $row['admin_user_label']);
        $this->assertSame('Альфа Первый, Бета Второй, Гамма Третий', $row['admin_user_label_full']);
        $this->assertCount(3, $row['admin_user_names']);
    }

    public function test_data_filter_finds_location_when_searched_admin_is_one_of_multiple(): void
    {
        $this->grantPermission('locations.view');

        $first = $this->createPartnerAdmin(['name' => 'Первый', 'lastname' => 'Админ']);
        $second = $this->createPartnerAdmin(['name' => 'Второй', 'lastname' => 'Админ']);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Мульти-админ объект',
        ]);
        $this->attachAdmins($location, [$first->id, $second->id]);

        $bySecond = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'admin_user_id' => $second->id,
        ]))->assertOk();

        $this->assertSame(1, (int) $bySecond->json('recordsFiltered'));
        $this->assertSame($location->id, (int) $bySecond->json('data.0.id'));
    }

    public function test_data_sort_by_admin_user_label_asc_and_desc(): void
    {
        $this->grantPermission('locations.view');

        $adminA = $this->createPartnerAdmin(['name' => 'Анна', 'lastname' => 'Ааа']);
        $adminZ = $this->createPartnerAdmin(['name' => 'Зоя', 'lastname' => 'Яяя']);

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Sort A',
        ]);
        $locZ = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Sort Z',
        ]);
        $this->attachAdmins($locA, [$adminA->id]);
        $this->attachAdmins($locZ, [$adminZ->id]);

        $sortAsc = fn (string $dir) => $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'order' => [['column' => 0, 'dir' => $dir]],
            'columns' => [['name' => 'admin_user_label']],
        ]))->assertOk()->json('data');

        $ascIds = collect($sortAsc('asc'))->pluck('id')->all();
        $this->assertSame([$locA->id, $locZ->id], array_values(array_intersect([$locA->id, $locZ->id], $ascIds)));

        $descIds = collect($sortAsc('desc'))->pluck('id')->all();
        $this->assertSame([$locZ->id, $locA->id], array_values(array_intersect([$locZ->id, $locA->id], $descIds)));
    }

    public function test_store_accepts_exactly_five_admins(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $adminIds = [];
        for ($i = 0; $i < 5; $i++) {
            $adminIds[] = $this->createPartnerAdmin(['name' => 'Админ' . $i, 'lastname' => 'Пять'])->id;
        }

        $store = $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с пятью админами',
            'admin_user_ids' => $adminIds,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $locationId = (int) $store->json('location.id');
        $this->assertSame(5, DB::table('location_admin_user')->where('location_id', $locationId)->count());
    }

    public function test_update_sync_replaces_all_admins(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $first = $this->createPartnerAdmin(['name' => 'Старый', 'lastname' => 'Один']);
        $second = $this->createPartnerAdmin(['name' => 'Старый', 'lastname' => 'Два']);
        $replacement = $this->createPartnerAdmin(['name' => 'Новый', 'lastname' => 'Три']);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Sync replace',
        ]);
        $this->attachAdmins($location, [$first->id, $second->id]);

        $this->putJson(route('admin.locations.update', ['location' => $location->id]), [
            'name' => 'Sync replace',
            'admin_user_ids' => [$replacement->id],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertDatabaseHas('location_admin_user', [
            'location_id' => $location->id,
            'user_id' => $replacement->id,
        ]);
        $this->assertDatabaseMissing('location_admin_user', [
            'location_id' => $location->id,
            'user_id' => $first->id,
        ]);
        $this->assertDatabaseMissing('location_admin_user', [
            'location_id' => $location->id,
            'user_id' => $second->id,
        ]);
        $this->assertSame(1, DB::table('location_admin_user')->where('location_id', $location->id)->count());
    }

    public function test_store_rejects_foreign_partner_admin(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $foreignAdmin = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => (int) Role::query()->where('name', 'admin')->value('id'),
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с чужим админом',
            'admin_user_ids' => [$foreignAdmin->id],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_user_ids.0']);
    }

    public function test_store_rejects_disabled_admin(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $disabledAdmin = $this->createPartnerAdmin(['is_enabled' => 0]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Объект с выключенным админом',
            'admin_user_ids' => [$disabledAdmin->id],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_user_ids.0']);
    }

    public function test_destroy_cascades_pivot_rows(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $admin = $this->createPartnerAdmin();
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
        ]);
        $this->attachAdmins($location, [$admin->id]);

        $this->assertDatabaseHas('location_admin_user', [
            'location_id' => $location->id,
            'user_id' => $admin->id,
        ]);

        $this->deleteJson(route('admin.locations.destroy', ['location' => $location->id]))
            ->assertOk();

        $this->assertDatabaseMissing('location_admin_user', [
            'location_id' => $location->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_columns_settings_roundtrip_includes_admin_user_label(): void
    {
        $this->grantPermission('locations.view');

        $this->postJson(route('admin.locations.columns-settings.save'), [
            'columns' => [
                'id' => true,
                'name' => true,
                'admin_user_label' => false,
                'district_name' => true,
            ],
        ])->assertOk();

        $this->getJson(route('admin.locations.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('admin_user_label', false);
    }

    public function test_teams_data_filter_finds_team_when_location_has_multiple_admins(): void
    {
        $this->grantPermission('groups.view');
        $this->grantPermission('locations.view');

        $first = $this->createPartnerAdmin();
        $second = $this->createPartnerAdmin(['name' => 'Второй', 'lastname' => 'Фильтр']);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
        ]);
        $this->attachAdmins($location, [$first->id, $second->id]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа мульти-админ объекта',
            'location_id' => $location->id,
        ]);

        $bySecondAdmin = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&admin_user_id=' . $second->id)
            ->assertOk();

        $this->assertSame([$team->id], collect($bySecondAdmin->json('data'))->pluck('id')->all());
    }
}
