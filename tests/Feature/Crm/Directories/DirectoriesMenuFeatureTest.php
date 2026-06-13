<?php

namespace Tests\Feature\Crm\Directories;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Crm\CrmTestCase;

/**
 * DirectoriesMenu: подпись и URL пункта сайдбара при одном или нескольких справочниках.
 */
final class DirectoriesMenuFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    /** @return list<array{0: list<string>, 1: string, 2: string, 3: string}> */
    public static function singleDirectoryMenuProvider(): array
    {
        return [
            'groups'       => [['groups.view'], 'Группы', 'admin.team.index', 'admin.team.index'],
            'locations'    => [['locations.view'], 'Объекты', 'admin.locations.index', 'admin.locations.index'],
            'districts'    => [['districts.view'], 'Районы', 'admin.districts.index', 'admin.districts.index'],
            'sport_types'  => [['sport_types.view'], 'Виды спорта', 'admin.sport-types.index', 'admin.sport-types.index'],
        ];
    }

    #[DataProvider('singleDirectoryMenuProvider')]
    public function test_sidebar_shows_directory_name_when_only_one_directory_available(
        array $permissions,
        string $expectedLabel,
        string $pageRoute,
        string $expectedUrlRoute
    ): void {
        $actor = $this->createUserWithPermissions($permissions);
        $this->actingAs($actor);

        $html = $this->get(route($pageRoute))->assertOk()->getContent();

        $this->assertSidebarMenu($html, $expectedLabel, route($expectedUrlRoute, [], false));
        $this->assertStringNotContainsString('<p>Справочники</p>', $this->sidebarChunk($html));
    }

    #[DataProvider('singleDirectoryMenuProvider')]
    public function test_sidebar_directory_name_is_consistent_on_unrelated_page(
        array $permissions,
        string $expectedLabel,
        string $pageRoute,
        string $expectedUrlRoute
    ): void {
        $actor = $this->createUserWithPermissions([...$permissions, 'users.view']);
        $this->actingAs($actor);

        $html = $this->get('/admin/users')->assertOk()->getContent();

        $this->assertSidebarMenu($html, $expectedLabel, route($expectedUrlRoute, [], false));
        $this->assertStringNotContainsString('<p>Справочники</p>', $this->sidebarChunk($html));

        // Страница справочника по-прежнему доступна.
        $this->get(route($pageRoute))->assertOk();
    }

    /** @return list<array{0: list<string>, 1: string}> */
    public static function multipleDirectoriesMenuProvider(): array
    {
        return [
            'groups_and_sport_types'     => [['groups.view', 'sport_types.view'], 'admin.team.index'],
            'groups_and_districts'       => [['groups.view', 'districts.view'], 'admin.team.index'],
            'locations_and_districts'    => [['locations.view', 'districts.view'], 'admin.locations.index'],
            'locations_and_sport_types'  => [['locations.view', 'sport_types.view'], 'admin.locations.index'],
            'districts_and_sport_types'  => [['districts.view', 'sport_types.view'], 'admin.districts.index'],
            'three_without_groups'       => [['locations.view', 'districts.view', 'sport_types.view'], 'admin.locations.index'],
            'all_four'                   => [['groups.view', 'locations.view', 'districts.view', 'sport_types.view'], 'admin.team.index'],
        ];
    }

    #[DataProvider('multipleDirectoriesMenuProvider')]
    public function test_sidebar_shows_directories_label_when_two_or_more_directories_available(
        array $permissions,
        string $expectedUrlRoute
    ): void {
        $actor = $this->createUserWithPermissions($permissions);
        $this->actingAs($actor);

        $accessibleRoute = $this->firstAccessibleRoute($permissions);
        $html = $this->get(route($accessibleRoute))->assertOk()->getContent();

        $this->assertSidebarMenu($html, 'Справочники', route($expectedUrlRoute, [], false));
    }

    public function test_sidebar_has_single_book_icon_menu_item_when_directories_available(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view']);
        $this->actingAs($actor);

        $sidebar = $this->sidebarChunk(
            $this->get(route('admin.team.index'))->assertOk()->getContent()
        );

        $this->assertSame(1, substr_count($sidebar, 'nav-icon fa-solid fa-book'));
    }

    public function test_sidebar_has_no_book_icon_menu_when_no_directory_permissions(): void
    {
        $actor = $this->createUserWithPermissions(['users.view']);
        $this->actingAs($actor);

        $sidebar = $this->sidebarChunk(
            $this->get('/admin/users')->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('nav-icon fa-solid fa-book', $sidebar);
        $this->assertStringNotContainsString('<p>Справочники</p>', $sidebar);
        $this->assertStringNotContainsString('<p>Группы</p>', $sidebar);
        $this->assertStringNotContainsString('<p>Объекты</p>', $sidebar);
        $this->assertStringNotContainsString('<p>Районы</p>', $sidebar);
        $this->assertStringNotContainsString('<p>Виды спорта</p>', $sidebar);
    }

    public function test_locations_label_when_only_locations_view_even_with_unrelated_permissions(): void
    {
        $actor = $this->createUserWithPermissions(['locations.view', 'users.view', 'reports.view']);
        $this->actingAs($actor);

        $html = $this->get(route('admin.locations.index'))->assertOk()->getContent();

        $this->assertSidebarMenu($html, 'Объекты', route('admin.locations.index', [], false));
    }

    /** @param list<string> $permissionNames */
    private function createUserWithPermissions(array $permissionNames): User
    {
        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name'       => 'test_dirs_menu_' . strtolower(\Illuminate\Support\Str::random(8)),
            'label'      => 'Test Directories Menu',
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

    /** @param list<string> $permissions */
    private function firstAccessibleRoute(array $permissions): string
    {
        if (in_array('groups.view', $permissions, true)) {
            return 'admin.team.index';
        }
        if (in_array('locations.view', $permissions, true)) {
            return 'admin.locations.index';
        }
        if (in_array('districts.view', $permissions, true)) {
            return 'admin.districts.index';
        }

        return 'admin.sport-types.index';
    }

    private function sidebarChunk(string $html): string
    {
        $sidebarStart = strpos($html, 'nav-sidebar');
        $this->assertNotFalse($sidebarStart, 'Sidebar not found in response');

        return substr($html, (int) $sidebarStart, 5000);
    }

    private function assertSidebarMenu(string $html, string $expectedLabel, string $expectedPathFragment): void
    {
        $sidebar = $this->sidebarChunk($html);

        $pattern = '/<a href="([^"]+)" class="nav-link">\s*<i class="nav-icon fa-solid fa-book">/s';
        $this->assertMatchesRegularExpression($pattern, $sidebar, 'Directories sidebar link not found');

        preg_match($pattern, $sidebar, $matches);
        $this->assertStringContainsString($expectedPathFragment, $matches[1] ?? '');
        $this->assertStringContainsString('<p>' . $expectedLabel . '</p>', $sidebar);
    }
}
