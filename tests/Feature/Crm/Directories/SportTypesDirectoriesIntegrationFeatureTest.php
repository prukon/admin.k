<?php

namespace Tests\Feature\Crm\Directories;

use App\Models\SportType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Интеграция «Виды спорта» в раздел «Справочники»: вкладки, сайдбар, HTML.
 * Полный доступ к эндпоинтам при sport_types.view / sport_types.manage → 200.
 */
final class SportTypesDirectoriesIntegrationFeatureTest extends CrmTestCase
{
    private SportType $sportType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Integration sport',
            'sort'       => 1,
            'is_enabled' => true,
        ]);
    }

    /** @param list<string> $permissionNames */
    private function createUserWithPermissions(array $permissionNames): User
    {
        $permissionNames = $this->withDirectoriesMenuPermission($permissionNames);

        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name'       => 'test_dirs_integration_' . strtolower(\Illuminate\Support\Str::random(8)),
            'label'      => 'Test Directories Integration',
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

    public function test_tabs_order_groups_before_sport_types_when_both_permissions_granted(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'districts.view');
        $this->grantPermissionTo($this->user, 'locations.view');
        $this->grantPermissionTo($this->user, 'sport_types.view');

        $html = $this->get(route('admin.sport-types.index'))->assertOk()->getContent();
        preg_match('/<ul[^>]*id="directoriesSectionTabs"[^>]*>(.*?)<\/ul>/s', $html, $matches);
        $tabs = $matches[1];

        $this->assertLessThan(strpos($tabs, '>Виды спорта</a>'), strpos($tabs, '>Группы</a>'));
        $this->assertLessThan(strpos($tabs, '>Районы</a>'), strpos($tabs, '>Объекты</a>'));
    }

    public function test_sport_types_page_renders_as_directories_section_with_active_tab(): void
    {
        $actor = $this->createUserWithPermissions(['sport_types.view']);
        $this->actingAs($actor);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertViewIs('admin.sport-types.index')
            ->assertSee('Справочники', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee(route('admin.sport-types.index'), false)
            ->assertSee('>Виды спорта</a>', false)
            ->assertSee('nav-link active', false)
            ->assertSee('id="sport-types-table"', false)
            ->assertSee('<p>Виды спорта</p>', false)
            ->assertDontSee('<p>Справочники</p>', false);
    }

    public function test_districts_page_shows_sport_types_tab_when_permission_granted(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'districts.view');
        $this->grantPermissionTo($this->user, 'sport_types.view');

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('Справочники', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee(route('admin.sport-types.index'), false)
            ->assertSee('>Виды спорта</a>', false)
            ->assertSee('>Районы</a>', false);
    }

    public function test_locations_page_shows_sport_types_tab_when_permission_granted(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'locations.view');
        $this->grantPermissionTo($this->user, 'sport_types.view');

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('Справочники', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee(route('admin.sport-types.index'), false)
            ->assertSee('>Виды спорта</a>', false)
            ->assertSee('>Объекты</a>', false);
    }

    public function test_sidebar_directories_link_prefers_groups_when_all_permissions_granted(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'districts.view');
        $this->grantPermissionTo($this->user, 'locations.view');
        $this->grantPermissionTo($this->user, 'sport_types.view');

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee(route('admin.team.index', [], false), false)
            ->assertDontSee('<p>Виды спорта</p>', false);
    }

    public function test_sidebar_directories_link_prefers_locations_when_no_districts_view(): void
    {
        $actor = $this->createUserWithPermissions(['locations.view', 'sport_types.view']);
        $this->actingAs($actor);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee(route('admin.locations.index', [], false), false)
            ->assertDontSee(route('admin.districts.index', [], false), false);
    }

    public function test_sidebar_does_not_show_separate_sport_types_menu_item(): void
    {
        $actor = $this->createUserWithPermissions(['sport_types.view', 'groups.view']);
        $this->actingAs($actor);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('<p>Справочники</p>', false)
            ->assertDontSee('<p>Виды спорта</p>', false);
    }

    public function test_user_with_sport_types_view_only_all_read_endpoints_return_200(): void
    {
        $actor = $this->createUserWithPermissions(['sport_types.view']);
        $this->actingAs($actor);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('id="directoriesSectionTabs"', false);

        $this->getJson(route('admin.sport-types.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'name'   => 'Integration',
            'status' => 'active',
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.sport-types.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.sport-types.columns-settings.save'), [
            'columns' => [
                'sort'             => true,
                'name'             => true,
                'teams_count'      => true,
                'is_enabled_label' => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('admin.sport-types.show', $this->sportType->id))
            ->assertOk()
            ->assertJsonPath('id', $this->sportType->id);
    }

    public function test_user_with_sport_types_view_and_manage_all_endpoints_return_200(): void
    {
        $actor = $this->createUserWithPermissions(['sport_types.view', 'sport_types.manage']);
        $this->actingAs($actor);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('id="new-sport-type"', false)
            ->assertSee('sportTypeCreateModal', false);

        $this->getJson(route('admin.sport-types.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->getJson(route('admin.sport-types.show', $this->sportType->id))->assertOk();

        $this->postJson(route('admin.sport-types.store'), [
            'name'       => 'Created in integration test',
            'sort'       => 2,
            'is_enabled' => 1,
        ])->assertOk();

        $this->putJson(route('admin.sport-types.update', $this->sportType->id), [
            'name'        => 'Integration sport updated',
            'description' => 'Описание',
            'sort'        => 3,
            'is_enabled'  => 1,
        ])->assertOk();

        $disposable = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Disposable integration sport',
        ]);

        $this->deleteJson(route('admin.sport-types.destroy', $disposable->id))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Вид спорта удалён');
    }

    public function test_mutations_return_403_without_sport_types_manage(): void
    {
        $actor = $this->createUserWithPermissions(['sport_types.view']);
        $this->actingAs($actor);

        $this->postJson(route('admin.sport-types.store'), [
            'name'       => 'Forbidden',
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->putJson(route('admin.sport-types.update', $this->sportType->id), [
            'name'       => 'Forbidden',
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->deleteJson(route('admin.sport-types.destroy', $this->sportType->id))
            ->assertStatus(403);
    }
}
