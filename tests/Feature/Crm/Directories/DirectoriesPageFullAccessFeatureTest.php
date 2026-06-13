<?php

namespace Tests\Feature\Crm\Directories;

use App\Models\District;
use App\Models\Location;
use App\Models\SportType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Полный контроль доступа раздела «Справочники»: страницы и все endpoint'ы → 200 при наличии прав.
 */
final class DirectoriesPageFullAccessFeatureTest extends CrmTestCase
{
    private District $district;

    private Location $location;

    private SportType $sportType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();

        $this->grantPermission($this->user, 'sport_types.view');
        $this->grantPermission($this->user, 'sport_types.manage');

        $this->district = District::factory()->forPartner($this->partner->id)->create([
            'name'       => 'Full access district',
            'sort_order' => 1,
        ]);

        $this->location = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Full access object',
            'is_enabled' => true,
        ]);

        $this->sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Full access sport',
            'sort'       => 1,
            'is_enabled' => true,
        ]);
    }

    public function test_admin_all_directories_endpoints_return_200(): void
    {
        foreach ($this->allDirectoriesRoutesPayload() as $item) {
            $headers = $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'];
            if (in_array($item['method'], ['POST', 'PATCH', 'PUT', 'DELETE'], true)
                && str_contains($item['url'], '/admin/teams')) {
                $headers['HTTP_X-Requested-With'] = 'XMLHttpRequest';
            }

            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $headers
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Админ: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_districts_and_locations_view_all_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->grantPermission($actor, 'districts.view');
        $this->grantPermission($actor, 'locations.view');
        $this->grantPermission($actor, 'locations.manage');
        $this->actingAs($actor);

        foreach ($this->districtsAndLocationsRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Актор: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_sport_types_view_and_manage_all_sport_types_endpoints_return_200(): void
    {
        $actor = $this->createUserWithOnlyPermissions(['sport_types.view', 'sport_types.manage']);
        $this->actingAs($actor);

        foreach ($this->sportTypesRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Sport types actor: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_groups_view_all_teams_endpoints_return_200(): void
    {
        $actor = $this->createUserWithOnlyPermissions(['groups.view']);
        $this->actingAs($actor);

        foreach ($this->teamsRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                array_merge(
                    $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'],
                    in_array($item['method'], ['POST', 'PATCH', 'PUT', 'DELETE'], true)
                        ? ['HTTP_X-Requested-With' => 'XMLHttpRequest']
                        : []
                )
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Groups actor: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_all_directories_html_pages_return_200_and_show_section_tabs(): void
    {
        $this->grantPermission($this->user, 'districts.view');
        $this->grantPermission($this->user, 'locations.view');

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertViewIs('admin.districts.index')
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Районы</a>', false)
            ->assertSee('>Объекты</a>', false)
            ->assertSee('>Группы</a>', false)
            ->assertSee('>Виды спорта</a>', false);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertViewIs('admin.locations.index')
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Районы</a>', false)
            ->assertSee('>Объекты</a>', false)
            ->assertSee('>Группы</a>', false)
            ->assertSee('>Виды спорта</a>', false)
            ->assertSee('id="filter-district"', false)
            ->assertSee('name="district_id"', false);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewIs('admin.team')
            ->assertSee('Справочники', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Районы</a>', false)
            ->assertSee('>Объекты</a>', false)
            ->assertSee('>Группы</a>', false)
            ->assertSee('>Виды спорта</a>', false);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertViewIs('admin.sport-types.index')
            ->assertSee('Справочники', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Районы</a>', false)
            ->assertSee('>Объекты</a>', false)
            ->assertSee('>Группы</a>', false)
            ->assertSee('>Виды спорта</a>', false)
            ->assertSee('id="sport-types-table"', false);
    }

    public function test_all_directories_html_pages_show_tabs_in_groups_objects_districts_sport_types_order(): void
    {
        $this->grantPermission($this->user, 'districts.view');
        $this->grantPermission($this->user, 'locations.view');

        foreach ([
            route('admin.team.index'),
            route('admin.locations.index'),
            route('admin.districts.index'),
            route('admin.sport-types.index'),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            if (!preg_match('/<ul[^>]*id="directoriesSectionTabs"[^>]*>(.*?)<\/ul>/s', $html, $matches)) {
                $this->fail("directoriesSectionTabs not found on {$url}");
            }

            $tabs = $matches[1];
            $groups = strpos($tabs, '>Группы</a>');
            $objects = strpos($tabs, '>Объекты</a>');
            $districts = strpos($tabs, '>Районы</a>');
            $sportTypes = strpos($tabs, '>Виды спорта</a>');

            $this->assertNotFalse($groups);
            $this->assertNotFalse($objects);
            $this->assertNotFalse($districts);
            $this->assertNotFalse($sportTypes);
            $this->assertLessThan($objects, $groups);
            $this->assertLessThan($districts, $objects);
            $this->assertLessThan($sportTypes, $districts);
        }
    }

    public function test_guest_denied_on_all_directories_endpoints(): void
    {
        Auth::logout();

        foreach ($this->allDirectoriesRoutesPayload() as $item) {
            $status = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            )->getStatusCode();

            $this->assertContains($status, [302, 401, 403], "Гость: {$item['method']} {$item['url']} → {$status}");
        }
    }

    private function grantPermission(User $user, string $permissionName): void
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
    private function createUserWithOnlyPermissions(array $permissionNames): User
    {
        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name'       => 'test_dirs_fa_' . strtolower(\Illuminate\Support\Str::random(8)),
            'label'      => 'Test Directories Full Access',
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

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function sportTypesRoutesPayload(): array
    {
        $disposable = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Disposable sport FA',
        ]);

        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.sport-types.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.sport-types.data', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 10,
                    'name'   => 'Full access',
                    'status' => 'active',
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.sport-types.columns-settings.get'),
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.sport-type', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.sport-types.columns-settings.save'),
                'data'   => ['columns' => ['name' => true, 'teams_count' => true]],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.sport-types.show', $this->sportType->id),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.sport-types.store'),
                'data'   => ['name' => 'Created FA sport', 'sort' => 2, 'is_enabled' => 1],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.sport-types.update', $this->sportType->id),
                'data'   => ['name' => 'Full access sport updated', 'sort' => 3, 'is_enabled' => 1],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.sport-types.destroy', $disposable->id),
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function districtsAndLocationsRoutesPayload(): array
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Directories pivot team',
        ]);

        $disposableDistrict = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Disposable district FA',
        ]);

        $disposableLocation = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Disposable object FA',
        ]);

        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.districts.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.districts.data', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 10,
                    'name'   => 'Full access',
                    'status' => 'active',
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.districts.data', [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'order'   => [['column' => 0, 'dir' => 'asc']],
                    'columns' => [['name' => 'sort_order']],
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.districts.columns-settings.get'),
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.district', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.districts.columns-settings.save'),
                'data'   => ['columns' => ['name' => true, 'locations_label' => true]],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.districts.show', $this->district->id),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.districts.store'),
                'data'   => ['name' => 'Created FA district', 'is_enabled' => 1, 'sort_order' => 2],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.districts.update', $this->district->id),
                'data'   => ['name' => 'Full access district updated', 'is_enabled' => 1],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.districts.destroy', $disposableDistrict->id),
            ],
            [
                'method'  => 'GET',
                'url'     => route('admin.locations.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.locations.data', [
                    'draw'        => 1,
                    'start'       => 0,
                    'length'      => 10,
                    'district_id' => (string) $this->district->id,
                    'status'      => 'active',
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.locations.data', [
                    'draw'        => 1,
                    'start'       => 0,
                    'length'      => 10,
                    'district_id' => 'none',
                    'order'       => [['column' => 0, 'dir' => 'asc']],
                    'columns'     => [['name' => 'district_name']],
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.locations.columns-settings.get'),
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.location', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.locations.columns-settings.save'),
                'data'   => ['columns' => ['name' => true, 'district_name' => true]],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.locations.show', $this->location->id),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.locations.store'),
                'data'   => [
                    'name'        => 'Created FA object',
                    'district_id' => $this->district->id,
                    'is_enabled'  => 1,
                    'team_ids'    => [$team->id],
                ],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.locations.update', $this->location->id),
                'data'   => [
                    'name'        => 'Full access object updated',
                    'district_id' => $this->district->id,
                    'is_enabled'  => 1,
                    'team_ids'    => [$team->id],
                ],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.locations.destroy', $disposableLocation->id),
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function teamsRoutesPayload(): array
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Full access FA team',
            'order_by'   => 5,
        ]);

        $disposable = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Disposable FA team',
            'order_by'   => 6,
        ]);

        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.team.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.team.data', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'GET',
                'url'    => '/admin/teams/columns-settings',
            ],
            [
                'method' => 'POST',
                'url'    => '/admin/teams/columns-settings',
                'data'   => ['columns' => ['title' => true, 'status_label' => true, 'actions' => true]],
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.team'),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.team.edit', $team->id),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.team.store'),
                'data'   => [
                    'title'                    => 'Created FA team',
                    'default_duration_minutes' => 60,
                    'month_price'              => 2500,
                    'order_by'                 => 10,
                    'is_enabled'               => 1,
                ],
            ],
            [
                'method' => 'PATCH',
                'url'    => route('admin.team.update', $team->id),
                'data'   => [
                    'title'                    => 'Full access FA team updated',
                    'default_duration_minutes' => 60,
                    'month_price'              => 2600,
                    'order_by'                 => $team->order_by,
                    'is_enabled'               => (int) $team->is_enabled,
                ],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.team.delete', $disposable->id),
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allDirectoriesRoutesPayload(): array
    {
        return [
            ...$this->teamsRoutesPayload(),
            ...$this->districtsAndLocationsRoutesPayload(),
            ...$this->sportTypesRoutesPayload(),
        ];
    }
}
