<?php

namespace Tests\Feature\Crm\Directories;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Справочники»: порядок вкладок, приоритет сайдбара, UI на всех страницах.
 */
final class DirectoriesSectionIntegrationFeatureTest extends CrmTestCase
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
            'name'       => 'test_dirs_section_' . strtolower(\Illuminate\Support\Str::random(8)),
            'label'      => 'Test Directories Section',
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

    private function extractSectionTabsHtml(string $html): string
    {
        if (!preg_match('/<ul[^>]*id="directoriesSectionTabs"[^>]*>(.*?)<\/ul>/s', $html, $matches)) {
            $this->fail('directoriesSectionTabs not found in response');
        }

        return $matches[1];
    }

    private function assertTabsAppearInOrder(string $tabsHtml, array $labelsInOrder): void
    {
        $positions = [];
        foreach ($labelsInOrder as $label) {
            $pos = strpos($tabsHtml, '>' . $label . '</a>');
            $this->assertNotFalse($pos, "Tab «{$label}» not found in section tabs");
            $positions[] = $pos;
        }

        for ($i = 1, $count = count($positions); $i < $count; $i++) {
            $this->assertLessThan(
                $positions[$i],
                $positions[$i - 1],
                'Expected tab order: ' . implode(' → ', $labelsInOrder)
            );
        }
    }

    private function assertSidebarDirectoriesUrl(string $html, string $expectedPath): void
    {
        $sidebarStart = strpos($html, 'nav-sidebar');
        $this->assertNotFalse($sidebarStart, 'Sidebar not found in response');

        $sidebarChunk = substr($html, $sidebarStart, 4000);

        $pattern = '/<a href="([^"]+)" class="nav-link">\s*<i class="nav-icon fa-solid fa-book">/s';
        $this->assertMatchesRegularExpression($pattern, $sidebarChunk, 'Directories sidebar link not found');

        preg_match($pattern, $sidebarChunk, $matches);
        $this->assertStringContainsString($expectedPath, $matches[1] ?? '');
    }

    private function assertSidebarDirectoriesLabel(string $html, string $expectedLabel): void
    {
        $sidebarStart = strpos($html, 'nav-sidebar');
        $this->assertNotFalse($sidebarStart, 'Sidebar not found in response');

        $sidebarChunk = substr($html, $sidebarStart, 4000);

        $this->assertStringContainsString('<p>' . $expectedLabel . '</p>', $sidebarChunk);
    }

    public function test_tabs_order_groups_objects_districts_sport_types_on_all_pages_with_all_permissions(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'districts.view');
        $this->grantPermissionTo($this->user, 'locations.view');
        $this->grantPermissionTo($this->user, 'sport_types.view');

        $expectedOrder = ['Группы', 'Объекты', 'Районы', 'Виды спорта'];

        foreach ([
            route('admin.team.index'),
            route('admin.locations.index'),
            route('admin.districts.index'),
            route('admin.sport-types.index'),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertTabsAppearInOrder($this->extractSectionTabsHtml($html), $expectedOrder);
        }
    }

    public function test_tabs_order_respects_visible_subset_when_partial_permissions(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view', 'sport_types.view']);
        $this->actingAs($actor);

        $tabsHtml = $this->extractSectionTabsHtml(
            $this->get(route('admin.team.index'))->assertOk()->getContent()
        );

        $this->assertTabsAppearInOrder($tabsHtml, ['Группы', 'Виды спорта']);
        $this->assertStringNotContainsString('>Объекты</a>', $tabsHtml);
        $this->assertStringNotContainsString('>Районы</a>', $tabsHtml);
    }

    public function test_sidebar_link_points_to_groups_when_groups_view_granted(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'districts.view');
        $this->grantPermissionTo($this->user, 'locations.view');
        $this->grantPermissionTo($this->user, 'sport_types.view');

        $html = $this->get(route('admin.districts.index'))->assertOk()->getContent();
        $this->assertSidebarDirectoriesUrl($html, route('admin.team.index', [], false));
        $this->assertSidebarDirectoriesLabel($html, 'Справочники');
    }

    public function test_sidebar_link_points_to_locations_when_no_groups_but_locations_and_districts(): void
    {
        $actor = $this->createUserWithPermissions(['districts.view', 'locations.view']);
        $this->actingAs($actor);

        $html = $this->get(route('admin.districts.index'))->assertOk()->getContent();
        $this->assertSidebarDirectoriesUrl($html, route('admin.locations.index', [], false));
        $this->assertSidebarDirectoriesLabel($html, 'Справочники');
    }

    public function test_sidebar_link_points_to_districts_when_only_districts_view(): void
    {
        $actor = $this->createUserWithPermissions(['districts.view']);
        $this->actingAs($actor);

        $html = $this->get(route('admin.districts.index'))->assertOk()->getContent();
        $this->assertSidebarDirectoriesUrl($html, route('admin.districts.index', [], false));
        $this->assertSidebarDirectoriesLabel($html, 'Районы');
    }

    public function test_sidebar_link_points_to_sport_types_when_only_sport_types_view(): void
    {
        $actor = $this->createUserWithPermissions(['sport_types.view']);
        $this->actingAs($actor);

        $html = $this->get(route('admin.sport-types.index'))->assertOk()->getContent();
        $this->assertSidebarDirectoriesUrl($html, route('admin.sport-types.index', [], false));
        $this->assertSidebarDirectoriesLabel($html, 'Виды спорта');
    }

    public function test_sidebar_label_is_groups_when_only_groups_view(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view']);
        $this->actingAs($actor);

        $html = $this->get(route('admin.team.index'))->assertOk()->getContent();
        $this->assertSidebarDirectoriesLabel($html, 'Группы');
        $this->assertStringNotContainsString('<p>Справочники</p>', $html);
    }

    public function test_teams_page_shows_groups_tab_on_districts_and_locations_pages_when_groups_view_granted(): void
    {
        $this->asAdmin();
        $this->grantPermissionTo($this->user, 'districts.view');
        $this->grantPermissionTo($this->user, 'locations.view');

        foreach ([route('admin.districts.index'), route('admin.locations.index')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('>Группы</a>', false)
                ->assertSee(route('admin.team.index'), false);
        }
    }

    public function test_no_separate_sidebar_items_for_groups_or_sport_types(): void
    {
        $actor = $this->createUserWithPermissions([
            'groups.view',
            'sport_types.view',
            'districts.view',
            'locations.view',
        ]);
        $this->actingAs($actor);

        $html = $this->get(route('admin.team.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<p>Справочники</p>', $html);
        $this->assertStringNotContainsString('<p>Группы</p>', $html);
        $this->assertStringNotContainsString('<p>Виды спорта</p>', $html);
    }
}
