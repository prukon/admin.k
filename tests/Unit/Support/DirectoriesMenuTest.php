<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\DirectoriesMenu;
use Tests\TestCase;

final class DirectoriesMenuTest extends TestCase
{
    public function test_returns_null_for_guest(): void
    {
        $this->assertNull(DirectoriesMenu::forUser(null));
    }

    public function test_returns_null_when_no_directory_permissions(): void
    {
        $user = $this->mockUserWithPermissions(['users.view']);

        $this->assertNull(DirectoriesMenu::forUser($user));
    }

    public function test_single_permission_uses_directory_label(): void
    {
        $cases = [
            ['groups.view', 'Группы', 'admin.team.index'],
            ['locations.view', 'Объекты', 'admin.locations.index'],
            ['districts.view', 'Районы', 'admin.districts.index'],
            ['sport_types.view', 'Виды спорта', 'admin.sport-types.index'],
        ];

        foreach ($cases as [$permission, $expectedLabel, $expectedRoute]) {
            $user = $this->mockUserWithPermissions([$permission]);
            $menu = DirectoriesMenu::forUser($user);

            $this->assertNotNull($menu, $permission);
            $this->assertSame($expectedLabel, $menu['label'], $permission);
            $this->assertSame(route($expectedRoute), $menu['url'], $permission);
        }
    }

    public function test_multiple_permissions_use_directories_label(): void
    {
        $user = $this->mockUserWithPermissions([
            'groups.view',
            'districts.view',
        ]);

        $menu = DirectoriesMenu::forUser($user);

        $this->assertSame('Справочники', $menu['label']);
        $this->assertSame(route('admin.team.index'), $menu['url']);
    }

    public function test_url_follows_groups_locations_districts_sport_types_priority(): void
    {
        $user = $this->mockUserWithPermissions([
            'districts.view',
            'sport_types.view',
            'locations.view',
        ]);

        $menu = DirectoriesMenu::forUser($user);

        $this->assertSame('Справочники', $menu['label']);
        $this->assertSame(route('admin.locations.index'), $menu['url']);
    }

    /** @param list<string> $permissions */
    private function mockUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->make();
        $mock = \Mockery::mock($user)->makePartial();
        $mock->shouldReceive('can')
            ->andReturnUsing(static fn (string $permission): bool => in_array($permission, $permissions, true));

        return $mock;
    }
}
