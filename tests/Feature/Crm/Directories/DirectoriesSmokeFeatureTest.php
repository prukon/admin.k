<?php

namespace Tests\Feature\Crm\Directories;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Smoke: меню «Справочники», вкладки раздела, комбинации прав districts.view / locations.view / groups.view / sport_types.view.
 */
final class DirectoriesSmokeFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermissionTo(User $user, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /** @param list<string> $permissionNames */
    private function createUserWithPermissions(array $permissionNames): User
    {
        $permissionNames = $this->withDirectoriesMenuPermission($permissionNames);

        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name'       => 'test_dirs_' . strtolower(\Illuminate\Support\Str::random(8)),
            'label'      => 'Test Directories Permissions',
            'is_sistem'  => 0,
            'order_by'   => 0,
            'is_visible' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($permissionNames as $permissionName) {
            DB::table('permission_role')->insert([
                'partner_id'    => $this->partner->id,
                'role_id'       => $roleId,
                'permission_id' => $this->permissionId($permissionName),
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        return \App\Models\User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
        ]);
    }

    public function test_sidebar_shows_directories_link_to_districts_when_districts_view_only(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->grantPermissionTo($actor, 'directories.view');
        $this->grantPermissionTo($actor, 'districts.view');
        $this->actingAs($actor);

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('<p>Районы</p>', false)
            ->assertSee(route('admin.districts.index', [], false), false)
            ->assertDontSee('<p>Справочники</p>', false)
            ->assertDontSee(route('admin.locations.index', [], false), false)
            ->assertDontSee(route('admin.sport-types.index', [], false), false)
            ->assertDontSee(route('admin.team.index', [], false), false);
    }

    public function test_sidebar_shows_directories_link_to_locations_when_locations_view_only(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantPermissionTo($actor, 'directories.view');
        $this->grantPermissionTo($actor, 'locations.view');
        $this->actingAs($actor);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('<p>Объекты</p>', false)
            ->assertSee(route('admin.locations.index', [], false), false)
            ->assertDontSee('<p>Справочники</p>', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Объекты</a>', false)
            ->assertDontSee('>Районы</a>', false)
            ->assertDontSee('>Виды спорта</a>', false);
    }

    public function test_sidebar_shows_directories_link_to_sport_types_when_sport_types_view_only(): void
    {
        $actor = $this->createUserWithPermissions(['sport_types.view']);
        $this->actingAs($actor);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('<p>Виды спорта</p>', false)
            ->assertSee(route('admin.sport-types.index', [], false), false)
            ->assertDontSee('<p>Справочники</p>', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Виды спорта</a>', false)
            ->assertDontSee('>Районы</a>', false)
            ->assertDontSee('>Объекты</a>', false);
    }

    public function test_sidebar_shows_directories_link_to_groups_when_groups_view_only(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view']);
        $this->actingAs($actor);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('<p>Группы</p>', false)
            ->assertSee(route('admin.team.index', [], false), false)
            ->assertDontSee('<p>Справочники</p>', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Группы</a>', false)
            ->assertDontSee('>Районы</a>', false)
            ->assertDontSee('>Объекты</a>', false)
            ->assertDontSee('>Виды спорта</a>', false);
    }

    public function test_sidebar_hides_directories_without_any_directories_permission(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->grantPermissionTo($actor, 'users.view');
        $this->actingAs($actor);

        $this->get('/admin/users')
            ->assertOk()
            ->assertDontSee('<p>Справочники</p>', false)
            ->assertDontSee('<p>Группы</p>', false)
            ->assertDontSee('<p>Объекты</p>', false)
            ->assertDontSee('<p>Районы</p>', false)
            ->assertDontSee('<p>Виды спорта</p>', false);
    }

    public function test_districts_tab_hidden_on_locations_page_without_districts_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantPermissionTo($actor, 'locations.view');
        $this->actingAs($actor);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('Объекты', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertDontSee('>Районы</a>', false)
            ->assertDontSee('>Виды спорта</a>', false)
            ->assertDontSee('>Группы</a>', false);
    }

    public function test_groups_tab_hidden_on_districts_page_without_groups_view(): void
    {
        $actor = $this->createUserWithPermissions(['districts.view']);
        $this->actingAs($actor);

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertDontSee('>Группы</a>', false);
    }

    public function test_sport_types_tab_hidden_on_districts_page_without_sport_types_view(): void
    {
        $actor = $this->createUserWithPermissions(['districts.view']);
        $this->actingAs($actor);

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertDontSee('>Виды спорта</a>', false);
    }

    public function test_districts_and_objects_tabs_hidden_on_sport_types_page_without_respective_permissions(): void
    {
        $actor = $this->createUserWithPermissions(['sport_types.view']);
        $this->actingAs($actor);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('Виды спорта', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Виды спорта</a>', false)
            ->assertDontSee('>Районы</a>', false)
            ->assertDontSee('>Объекты</a>', false)
            ->assertDontSee('>Группы</a>', false);
    }

    public function test_other_tabs_hidden_on_groups_page_without_respective_permissions(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view']);
        $this->actingAs($actor);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('Группы', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Группы</a>', false)
            ->assertDontSee('>Районы</a>', false)
            ->assertDontSee('>Объекты</a>', false)
            ->assertDontSee('>Виды спорта</a>', false);
    }

    public function test_objects_tab_hidden_on_districts_page_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->grantPermissionTo($actor, 'districts.view');
        $this->actingAs($actor);

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('Районы', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertDontSee('>Объекты</a>', false);
    }

    public function test_both_tabs_visible_when_user_has_both_permissions(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'districts.view');
        $this->grantPermissionTo($this->user, 'locations.view');

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Районы</a>', false)
            ->assertSee('>Объекты</a>', false)
            ->assertSee('<p>Справочники</p>', false);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('>Районы</a>', false)
            ->assertSee('>Объекты</a>', false);
    }

    public function test_all_four_tabs_visible_when_user_has_all_permissions(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'districts.view');
        $this->grantPermissionTo($this->user, 'locations.view');
        $this->grantPermissionTo($this->user, 'sport_types.view');

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Районы</a>', false)
            ->assertSee('>Объекты</a>', false)
            ->assertSee('>Группы</a>', false)
            ->assertSee('>Виды спорта</a>', false);
    }

    public function test_all_four_tabs_visible_on_each_directories_page_when_user_has_all_permissions(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'districts.view');
        $this->grantPermissionTo($this->user, 'locations.view');
        $this->grantPermissionTo($this->user, 'sport_types.view');

        foreach ([
            route('admin.districts.index'),
            route('admin.locations.index'),
            route('admin.team.index'),
            route('admin.sport-types.index'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('id="directoriesSectionTabs"', false)
                ->assertSee('>Районы</a>', false)
                ->assertSee('>Объекты</a>', false)
                ->assertSee('>Группы</a>', false)
                ->assertSee('>Виды спорта</a>', false);
        }
    }

    public function test_districts_locations_sport_types_tabs_without_groups_when_no_groups_view(): void
    {
        $actor = $this->createUserWithPermissions([
            'districts.view',
            'locations.view',
            'sport_types.view',
        ]);
        $this->actingAs($actor);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Районы</a>', false)
            ->assertSee('>Объекты</a>', false)
            ->assertSee('>Виды спорта</a>', false)
            ->assertDontSee('>Группы</a>', false);
    }

    public function test_locations_page_denied_without_locations_view_even_with_districts_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantPermissionTo($actor, 'districts.view');
        $this->actingAs($actor);

        $this->get(route('admin.locations.index'))->assertStatus(403);
        $this->getJson(route('admin.locations.data', ['draw' => 1]))->assertStatus(403);
    }

    public function test_districts_page_denied_without_districts_view_even_with_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->grantPermissionTo($actor, 'locations.view');
        $this->actingAs($actor);

        $this->get(route('admin.districts.index'))->assertStatus(403);
        $this->getJson(route('admin.districts.data', ['draw' => 1]))->assertStatus(403);
    }

    public function test_sport_types_page_denied_without_sport_types_view_even_with_districts_view(): void
    {
        $actor = $this->createUserWithoutPermission('sport_types.view', $this->partner);
        $this->grantPermissionTo($actor, 'districts.view');
        $this->actingAs($actor);

        $this->get(route('admin.sport-types.index'))->assertStatus(403);
        $this->getJson(route('admin.sport-types.data', ['draw' => 1]))->assertStatus(403);
    }

    public function test_teams_page_denied_without_groups_view_even_with_districts_view(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->grantPermissionTo($actor, 'districts.view');
        $this->actingAs($actor);

        $this->get(route('admin.team.index'))->assertStatus(403);
        $this->getJson(route('admin.team.data', ['draw' => 1]))->assertStatus(403);
    }
}
