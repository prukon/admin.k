<?php

namespace Tests\Feature\Crm\Directories;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Smoke: меню «Справочники», вкладки раздела, комбинации прав districts.view / locations.view.
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

    public function test_sidebar_shows_directories_link_to_districts_when_districts_view_only(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->grantPermissionTo($actor, 'districts.view');
        $this->actingAs($actor);

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('Справочники', false)
            ->assertSee(route('admin.districts.index', [], false), false)
            ->assertDontSee(route('admin.locations.index', [], false), false);
    }

    public function test_sidebar_shows_directories_link_to_locations_when_locations_view_only(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantPermissionTo($actor, 'locations.view');
        $this->actingAs($actor);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('Справочники', false)
            ->assertSee(route('admin.locations.index', [], false), false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Объекты</a>', false)
            ->assertDontSee('>Районы</a>', false);
    }

    public function test_sidebar_hides_directories_without_districts_or_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->actingAs($actor);
        $this->grantPermissionTo($actor, 'groups.view');

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertDontSee('<p>Справочники</p>', false);
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
            ->assertDontSee('>Районы</a>', false);
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
            ->assertSee('>Объекты</a>', false);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('>Районы</a>', false)
            ->assertSee('>Объекты</a>', false);
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
}
