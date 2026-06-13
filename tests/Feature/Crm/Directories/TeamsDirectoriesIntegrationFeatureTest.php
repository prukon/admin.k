<?php

namespace Tests\Feature\Crm\Directories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Интеграция «Группы» в раздел «Справочники»: вкладки, сайдбар, HTML.
 */
final class TeamsDirectoriesIntegrationFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /** @param list<string> $permissionNames */
    private function createUserWithPermissions(array $permissionNames): User
    {
        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name'       => 'test_teams_dirs_' . strtolower(\Illuminate\Support\Str::random(8)),
            'label'      => 'Test Teams Directories',
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

        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
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

    public function test_teams_page_renders_as_directories_section_with_active_tab(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view']);
        $this->actingAs($actor);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewIs('admin.team')
            ->assertSee('Справочники', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee(route('admin.team.index'), false)
            ->assertSee('>Группы</a>', false)
            ->assertSee('nav-link active', false)
            ->assertSee('<p>Группы</p>', false);
    }

    public function test_sidebar_directories_link_prefers_groups_over_sport_types_when_both_granted(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view', 'sport_types.view']);
        $this->actingAs($actor);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee(route('admin.team.index', [], false), false)
            ->assertSee('<p>Справочники</p>', false)
            ->assertDontSee('<p>Группы</p>', false);
    }

    public function test_sidebar_does_not_show_separate_groups_menu_item(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view']);
        $this->actingAs($actor);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('<p>Группы</p>', false)
            ->assertDontSee('<p>Справочники</p>', false);
    }

    public function test_user_with_groups_view_only_all_teams_endpoints_return_200(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view']);
        $this->actingAs($actor);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Integration team',
            'order_by'   => 1,
        ]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('id="directoriesSectionTabs"', false);

        $this->getJson('/admin/teams/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson('/admin/teams/columns-settings')->assertOk();
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => ['title' => true, 'status_label' => true],
        ])->assertOk();
        $this->getJson(route('admin.team.edit', $team->id))->assertOk();
    }

    public function test_user_with_groups_view_all_teams_endpoints_including_mutations_return_200(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view']);
        $this->actingAs($actor);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Integration mutate team',
            'order_by'   => 2,
        ]);

        $this->get(route('admin.team.index'))->assertOk();

        $this->getJson('/admin/teams/data?draw=1&start=0&length=10&title=Integration')->assertOk();
        $this->get(route('logs.data.team'))->assertOk();

        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Created in directories integration',
            'default_duration_minutes' => 60,
            'month_price'              => 1500,
            'order_by'                 => 8,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->patchJson(route('admin.team.update', $team->id), [
            'title'                    => 'Integration mutate team updated',
            'default_duration_minutes' => 60,
            'month_price'              => 1600,
            'order_by'                 => $team->order_by,
            'is_enabled'               => (int) $team->is_enabled,
        ])->assertOk();

        $disposable = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Integration delete team',
        ]);

        $this->deleteJson(route('admin.team.delete', $disposable->id))->assertOk();
    }

    public function test_districts_and_locations_pages_show_groups_tab_when_permission_granted(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'districts.view');
        $this->grantPermissionTo($this->user, 'locations.view');

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('>Группы</a>', false)
            ->assertSee(route('admin.team.index'), false);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('>Группы</a>', false);
    }

    public function test_sidebar_directories_link_prefers_locations_over_districts_without_groups_view(): void
    {
        $actor = $this->createUserWithPermissions(['districts.view', 'locations.view', 'sport_types.view']);
        $this->actingAs($actor);

        $html = $this->get(route('admin.sport-types.index'))->assertOk()->getContent();
        $sidebarStart = strpos($html, 'nav-sidebar');
        $sidebarChunk = substr($html, (int) $sidebarStart, 4000);

        preg_match(
            '/<a href="([^"]+)" class="nav-link">\s*<i class="nav-icon fa-solid fa-book">/s',
            $sidebarChunk,
            $matches
        );

        $this->assertStringContainsString(route('admin.locations.index', [], false), $matches[1] ?? '');
    }
}
