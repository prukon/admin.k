<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\Location;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Crm\CrmTestCase;

/**
 * После удаления users.location_id: UI и API пользователей не используют локацию ученика.
 */
final class UsersLocationRemovedFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantUsersView($this->user);
    }

    private function grantUsersView(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_users_page_has_no_location_filter_or_column(): void
    {
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Не в списке пользователей',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertDontSee('id="filter-location"', false)
            ->assertDontSee('id="create-location"', false)
            ->assertDontSee('id="edit-location"', false)
            ->assertDontSee('data-column-key="location"', false);
    }

    public function test_users_data_ignores_location_id_query_param(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $location = Location::factory()->create(['partner_id' => $this->partner->id]);

        $withTeam = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'is_enabled' => 1,
        ]);
        $withoutTeam = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => null,
            'is_enabled' => 1,
        ]);

        $json = $this->getJson(
            '/admin/users/data?draw=1&start=0&length=100&location_id=' . $location->id
        )->assertOk()->json();

        $ids = collect($json['data'] ?? [])->pluck('id')->all();
        $this->assertContains($withTeam->id, $ids);
        $this->assertContains($withoutTeam->id, $ids);

        $firstRow = $json['data'][0] ?? [];
        $this->assertArrayNotHasKey('location', $firstRow);
    }

    public function test_users_store_and_edit_do_not_persist_or_return_location(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $location = Location::factory()->create(['partner_id' => $this->partner->id]);

        $store = $this->postJson('/admin/users', [
            'name' => 'Ученик',
            'lastname' => 'Без локации',
            'role_id' => $roleId,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $store->assertOk();
        $userId = (int) $store->json('user.id');

        $this->assertFalse(Schema::hasColumn('users', 'location_id'));

        $edit = $this->getJson('/admin/users/' . $userId . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $userPayload = $edit->json('user') ?? [];
        $this->assertArrayNotHasKey('location_id', $userPayload);
        $this->assertArrayNotHasKey('location', $userPayload);
    }

    public function test_users_section_endpoints_return_200_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantUsersView($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $roleId = (int) Role::query()->where('name', 'user')->value('id');
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get(route('admin.user1'))->assertOk();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();

        $store = $this->postJson('/admin/users', [
            'name' => 'A',
            'lastname' => 'B',
            'role_id' => $roleId,
            'team_id' => $team->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $userId = (int) $store->json('user.id');

        $this->getJson('/admin/users/' . $userId . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->getJson(route('admin.users.table-settings.get'))->assertOk();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['name' => true, 'teams' => true],
        ])->assertOk();
    }
}
