<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class AdminUsersLocationFeatureTest extends CrmTestCase
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
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $permId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function defaultRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->firstOrFail()->id;
    }

    private function grantUsersViewOnly(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_users_page_and_location_endpoints_return_ok_with_locations_view(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Полный-smoke',
            'is_enabled' => true,
        ]);
        $roleId = $this->defaultRoleId();

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('activeLocations')
            ->assertSee('id="filter-location"', false)
            ->assertSee('id="create-location"', false)
            ->assertSee('Полный-smoke');

        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson('/admin/users/data?location_id=' . $location->id)->assertOk();
        $this->getJson('/admin/users/data?location_id=none')->assertOk();

        $this->getJson(route('admin.users.table-settings.get'))->assertOk();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['location' => true, 'name' => true],
        ])->assertOk();

        $store = $this->postJson(route('admin.user.store'), [
            'name'        => 'Smoke',
            'lastname'    => 'Локация',
            'role_id'     => $roleId,
            'location_id' => $location->id,
            'is_enabled'  => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $userId = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $userId);

        $this->getJson(route('admin.user.edit', $userId), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.location_id', $location->id)
            ->assertJsonPath('user.location.name', 'Полный-smoke');

        $this->patchJson(route('admin.user.update', $userId), [
            'name'        => 'Smoke',
            'lastname'    => 'Локация',
            'location_id' => null,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->assertNull(User::findOrFail($userId)->location_id);
    }

    public function test_index_hides_location_ui_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantUsersViewOnly($actor);

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Скрытая-в-ui',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertDontSee('id="filter-location"', false)
            ->assertDontSee('id="create-location"', false)
            ->assertDontSee('id="edit-location"', false)
            ->assertDontSee('data-column-key="location"', false);
    }

    public function test_store_saves_location_when_view_permission_granted(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'        => 'Иван',
            'lastname'    => 'Тестов',
            'role_id'     => $this->defaultRoleId(),
            'location_id' => $location->id,
            'is_enabled'  => 1,
        ]);

        $response->assertOk();

        $user = User::query()->where('name', 'Иван')->where('lastname', 'Тестов')->first();
        $this->assertNotNull($user);
        $this->assertSame($location->id, (int) $user->location_id);
    }

    public function test_store_ignores_location_without_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'        => 'Пётр',
            'lastname'    => 'Скрытый',
            'role_id'     => $this->defaultRoleId(),
            'location_id' => $location->id,
            'is_enabled'  => 1,
        ]);

        $response->assertOk();

        $user = User::query()->where('name', 'Пётр')->where('lastname', 'Скрытый')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->location_id);
    }

    public function test_store_clears_location_when_empty_string(): void
    {
        $this->grantPermission('locations.view');

        $this->postJson(route('admin.user.store'), [
            'name'        => 'Без',
            'lastname'    => 'Локации',
            'role_id'     => $this->defaultRoleId(),
            'location_id' => '',
            'is_enabled'  => 1,
        ])->assertOk();

        $user = User::query()->where('name', 'Без')->where('lastname', 'Локации')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->location_id);
    }

    public function test_store_rejects_disabled_location_on_create(): void
    {
        $this->grantPermission('locations.view');

        $disabled = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => false,
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'        => 'Нельзя',
            'lastname'    => 'Отключена',
            'role_id'     => $this->defaultRoleId(),
            'location_id' => $disabled->id,
            'is_enabled'  => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_store_rejects_foreign_partner_location(): void
    {
        $this->grantPermission('locations.view');

        $foreignLocation = Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => true,
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'        => 'Анна',
            'lastname'    => 'Чужая',
            'role_id'     => $this->defaultRoleId(),
            'location_id' => $foreignLocation->id,
            'is_enabled'  => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_update_allows_keeping_disabled_current_location(): void
    {
        $this->grantPermission('locations.view');

        $disabledLocation = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => false,
            'name'       => 'Архивная',
        ]);

        $target = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'location_id' => $disabledLocation->id,
            'role_id'     => $this->defaultRoleId(),
        ]);

        $this->patchJson(route('admin.user.update', $target), [
            'name'        => $target->name,
            'lastname'    => $target->lastname,
            'location_id' => $disabledLocation->id,
        ])->assertOk();

        $target->refresh();
        $this->assertSame($disabledLocation->id, (int) $target->location_id);
    }

    public function test_update_assigns_and_changes_active_location(): void
    {
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        $this->patchJson(route('admin.user.update', $target), [
            'name'        => $target->name,
            'lastname'    => $target->lastname,
            'location_id' => $locA->id,
        ])->assertOk();
        $target->refresh();
        $this->assertSame($locA->id, (int) $target->location_id);

        $this->patchJson(route('admin.user.update', $target), [
            'name'        => $target->name,
            'lastname'    => $target->lastname,
            'location_id' => $locB->id,
        ])->assertOk();
        $target->refresh();
        $this->assertSame($locB->id, (int) $target->location_id);
    }

    public function test_update_clears_location_to_null(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $target = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'location_id' => $location->id,
            'role_id'     => $this->defaultRoleId(),
        ]);

        $this->patchJson(route('admin.user.update', $target), [
            'name'        => $target->name,
            'lastname'    => $target->lastname,
            'location_id' => null,
        ])->assertOk();

        $target->refresh();
        $this->assertNull($target->location_id);
    }

    public function test_edit_json_includes_disabled_current_location_meta(): void
    {
        $this->grantPermission('locations.view');

        $disabled = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => false,
            'name'       => 'Старая',
        ]);

        $target = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'location_id' => $disabled->id,
            'role_id'     => $this->defaultRoleId(),
        ]);

        $this->getJson(route('admin.user.edit', $target), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.location_id', $disabled->id)
            ->assertJsonPath('user.location.name', 'Старая')
            ->assertJsonPath('user.location.is_enabled', false);
    }

    public function test_update_rejects_assigning_disabled_location_not_current(): void
    {
        $this->grantPermission('locations.view');

        $disabledLocation = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => false,
        ]);

        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        $this->patchJson(route('admin.user.update', $target), [
            'location_id' => $disabledLocation->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_data_filters_by_location_and_hides_column_without_permission(): void
    {
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Локация А',
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Локация Б',
            'is_enabled' => true,
        ]);

        $userA = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'location_id' => $locA->id,
        ]);
        $userB = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'location_id' => $locB->id,
        ]);
        $userNone = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'location_id' => null,
        ]);

        $json = $this->getJson('/admin/users/data?location_id=' . $locA->id)->json();
        $ids = collect($json['data'])->pluck('id')->all();
        $this->assertContains($userA->id, $ids);
        $this->assertNotContains($userB->id, $ids);

        $jsonNone = $this->getJson('/admin/users/data?location_id=none')->json();
        $idsNone = collect($jsonNone['data'])->pluck('id')->all();
        $this->assertContains($userNone->id, $idsNone);
        $this->assertNotContains($userA->id, $idsNone);

        $row = collect($json['data'])->firstWhere('id', $userA->id);
        $this->assertSame('Локация А', $row['location'] ?? null);

        $actor = $this->createUserWithoutPermission('locations.view');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantUsersViewOnly($actor);

        $jsonHidden = $this->getJson('/admin/users/data?location_id=' . $locA->id)->json();
        $rowHidden = collect($jsonHidden['data'])->firstWhere('id', $userA->id);
        $this->assertSame('', $rowHidden['location'] ?? null);
    }

    public function test_data_location_filter_is_not_applied_without_locations_view(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $withLocation = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'location_id' => $loc->id,
        ]);
        $withoutLocation = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'location_id' => null,
        ]);

        $actor = $this->createUserWithoutPermission('locations.view');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantUsersViewOnly($actor);

        $json = $this->getJson('/admin/users/data?location_id=' . $loc->id)->json();
        $ids = collect($json['data'])->pluck('id')->all();

        $this->assertContains($withLocation->id, $ids);
        $this->assertContains($withoutLocation->id, $ids);
    }

    public function test_data_sorts_by_location_column(): void
    {
        $this->grantPermission('locations.view');

        $locZ = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Я-локация',
            'is_enabled' => true,
        ]);
        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'А-локация',
            'is_enabled' => true,
        ]);

        $userZ = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'location_id' => $locZ->id,
            'lastname'    => 'Zzz',
        ]);
        $userA = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'location_id' => $locA->id,
            'lastname'    => 'Aaa',
        ]);

        $json = $this->getJson('/admin/users/data?order[0][column]=4&order[0][dir]=asc')->json();
        $ids = collect($json['data'])->pluck('id')->all();

        $posA = array_search($userA->id, $ids, true);
        $posZ = array_search($userZ->id, $ids, true);

        $this->assertNotFalse($posA);
        $this->assertNotFalse($posZ);
        $this->assertTrue($posA < $posZ, 'Ожидали сортировку по названию локации asc: А раньше Я');
    }

    public function test_index_shows_location_filter_with_permission(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Кабинет 101',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('id="filter-location"', false)
            ->assertSee('Кабинет 101');
    }

    public function test_columns_settings_supports_location_key(): void
    {
        $this->grantPermission('locations.view');

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'location' => false,
                'name'     => true,
            ],
        ])->assertOk();

        $this->getJson(route('admin.users.table-settings.get'))
            ->assertOk()
            ->assertJsonPath('location', false);
    }

    public function test_users_section_forbidden_without_users_view_even_with_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('users.view');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $peer = User::factory()->create(['partner_id' => $this->partner->id]);

        $this->get(route('admin.user1'))->assertForbidden();
        $this->getJson('/admin/users/data?draw=1')->assertForbidden();
        $this->getJson('/admin/users/data?location_id=' . $location->id)->assertForbidden();
        $this->getJson(route('admin.users.table-settings.get'))->assertForbidden();
        $this->postJson(route('admin.users.table-settings.save'), ['columns' => ['location' => true]])->assertForbidden();

        $this->postJson(route('admin.user.store'), [
            'name'        => 'X',
            'lastname'    => 'Y',
            'role_id'     => $this->defaultRoleId(),
            'location_id' => $location->id,
        ])->assertForbidden();

        $this->getJson(route('admin.user.edit', $peer))->assertForbidden();
        $this->patchJson(route('admin.user.update', $peer), ['location_id' => $location->id])->assertForbidden();
    }
}
