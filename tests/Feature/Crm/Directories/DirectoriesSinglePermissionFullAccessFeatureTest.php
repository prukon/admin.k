<?php

namespace Tests\Feature\Crm\Directories;

use App\Models\District;
use App\Models\Location;
use App\Models\Role;
use App\Models\SportType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Единственный доступный справочник: HTML-страница, все endpoint'ы раздела → 200, сайдбар — имя справочника.
 */
final class DirectoriesSinglePermissionFullAccessFeatureTest extends CrmTestCase
{
    private District $district;

    private Location $location;

    private SportType $sportType;

    private Team $team;

    private User $partnerAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->district = District::factory()->forPartner($this->partner->id)->create([
            'name'       => 'Single perm district',
            'sort_order' => 1,
        ]);

        $this->location = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Single perm object',
            'is_enabled' => true,
        ]);

        $this->sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Single perm sport',
            'sort'       => 1,
            'is_enabled' => true,
        ]);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Single perm team',
            'order_by'   => 1,
        ]);

        $this->partnerAdmin = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => (int) Role::query()->where('name', 'admin')->value('id'),
            'is_enabled' => 1,
            'name' => 'Single',
            'lastname' => 'Admin',
        ]);
    }

    public function test_groups_only_all_teams_endpoints_return_200_and_sidebar_shows_groups(): void
    {
        $actor = $this->createUserWithOnlyPermissions(['groups.view']);
        $this->actingAs($actor);

        foreach ($this->teamsRoutesPayload() as $item) {
            $this->assertEndpointReturns200($item, 'groups.view only');
        }

        $html = $this->get(route('admin.team.index'))->assertOk()->getContent();
        $this->assertSidebarLabel($html, 'Группы');
        $this->assertStringNotContainsString('<p>Справочники</p>', $this->sidebarChunk($html));
    }

    public function test_districts_only_all_districts_endpoints_return_200_and_sidebar_shows_districts(): void
    {
        $actor = $this->createUserWithOnlyPermissions(['districts.view']);
        $this->actingAs($actor);

        foreach ($this->districtsRoutesPayload() as $item) {
            $this->assertEndpointReturns200($item, 'districts.view only');
        }

        $html = $this->get(route('admin.districts.index'))->assertOk()->getContent();
        $this->assertSidebarLabel($html, 'Районы');
    }

    public function test_locations_view_and_manage_only_all_locations_endpoints_return_200_and_sidebar_shows_objects(): void
    {
        $actor = $this->createUserWithOnlyPermissions(['locations.view', 'locations.manage']);
        $this->actingAs($actor);

        foreach ($this->locationsRoutesPayload() as $item) {
            $this->assertEndpointReturns200($item, 'locations.view+manage only');
        }

        $html = $this->get(route('admin.locations.index'))->assertOk()->getContent();
        $this->assertSidebarLabel($html, 'Объекты');
    }

    public function test_sport_types_view_and_manage_only_all_endpoints_return_200_and_sidebar_shows_sport_types(): void
    {
        $actor = $this->createUserWithOnlyPermissions(['sport_types.view', 'sport_types.manage']);
        $this->actingAs($actor);

        foreach ($this->sportTypesRoutesPayload() as $item) {
            $this->assertEndpointReturns200($item, 'sport_types.view+manage only');
        }

        $html = $this->get(route('admin.sport-types.index'))->assertOk()->getContent();
        $this->assertSidebarLabel($html, 'Виды спорта');
    }

    public function test_sport_types_view_only_read_endpoints_return_200_mutations_return_403(): void
    {
        $actor = $this->createUserWithOnlyPermissions(['sport_types.view']);
        $this->actingAs($actor);

        foreach ($this->sportTypesReadRoutesPayload() as $item) {
            $this->assertEndpointReturns200($item, 'sport_types.view read');
        }

        foreach ($this->sportTypesMutationRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "sport_types.view only mutation: {$item['method']} {$item['url']}"
            );
        }

        $html = $this->get(route('admin.sport-types.index'))->assertOk()->getContent();
        $this->assertSidebarLabel($html, 'Виды спорта');
    }

    public function test_locations_view_only_read_endpoints_return_200_mutations_return_403(): void
    {
        $actor = $this->createUserWithOnlyPermissions(['locations.view']);
        $this->actingAs($actor);

        foreach ($this->locationsReadRoutesPayload() as $item) {
            $this->assertEndpointReturns200($item, 'locations.view read');
        }

        foreach ($this->locationsMutationRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "locations.view only mutation: {$item['method']} {$item['url']}"
            );
        }

        $html = $this->get(route('admin.locations.index'))->assertOk()->getContent();
        $this->assertSidebarLabel($html, 'Объекты');
    }

    public function test_other_directories_endpoints_return_403_when_only_groups_view(): void
    {
        $actor = $this->createUserWithOnlyPermissions(['groups.view']);
        $this->actingAs($actor);

        $this->get(route('admin.districts.index'))->assertStatus(403);
        $this->get(route('admin.locations.index'))->assertStatus(403);
        $this->get(route('admin.sport-types.index'))->assertStatus(403);
        $this->getJson(route('admin.districts.data', ['draw' => 1]))->assertStatus(403);
        $this->getJson(route('admin.locations.data', ['draw' => 1]))->assertStatus(403);
        $this->getJson(route('admin.sport-types.data', ['draw' => 1]))->assertStatus(403);
    }

    public function test_other_directories_endpoints_return_403_when_only_districts_view(): void
    {
        $actor = $this->createUserWithOnlyPermissions(['districts.view']);
        $this->actingAs($actor);

        $this->get(route('admin.team.index'))->assertStatus(403);
        $this->get(route('admin.locations.index'))->assertStatus(403);
        $this->get(route('admin.sport-types.index'))->assertStatus(403);
    }

    /** @param list<string> $permissionNames */
    private function createUserWithOnlyPermissions(array $permissionNames): User
    {
        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name'       => 'test_dirs_single_' . strtolower(\Illuminate\Support\Str::random(8)),
            'label'      => 'Test Single Directory Access',
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
     * @param array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>} $item
     */
    private function assertEndpointReturns200(array $item, string $context): void
    {
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
            "{$context}: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
        );
    }

    private function assertSidebarLabel(string $html, string $expectedLabel): void
    {
        $this->assertStringContainsString('<p>' . $expectedLabel . '</p>', $this->sidebarChunk($html));
    }

    private function sidebarChunk(string $html): string
    {
        $sidebarStart = strpos($html, 'nav-sidebar');
        $this->assertNotFalse($sidebarStart, 'Sidebar not found');

        return substr($html, (int) $sidebarStart, 5000);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function teamsRoutesPayload(): array
    {
        $disposable = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Disposable single perm team',
            'order_by'   => 99,
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
                'data'   => ['columns' => ['title' => true, 'status_label' => true]],
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.team'),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.team.edit', $this->team->id),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.team.store'),
                'data'   => [
                    'title'                    => 'Created single perm team',
                    'default_duration_minutes' => 60,
                    'order_by'                 => 20,
                    'is_enabled'               => 1,
                ],
            ],
            [
                'method' => 'PATCH',
                'url'    => route('admin.team.update', $this->team->id),
                'data'   => [
                    'title'                    => 'Updated single perm team',
                    'default_duration_minutes' => 60,
                    'order_by'                 => $this->team->order_by,
                    'is_enabled'               => (int) $this->team->is_enabled,
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
    private function districtsRoutesPayload(): array
    {
        $disposable = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Disposable single perm district',
        ]);

        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.districts.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.districts.data', ['draw' => 1, 'start' => 0, 'length' => 10]),
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
                'data'   => ['columns' => ['name' => true]],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.districts.show', $this->district->id),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.districts.store'),
                'data'   => ['name' => 'Created single perm district', 'is_enabled' => 1],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.districts.update', $this->district->id),
                'data'   => ['name' => 'Updated single perm district', 'is_enabled' => 1],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.districts.destroy', $disposable->id),
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function locationsRoutesPayload(): array
    {
        $disposable = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Disposable single perm object',
        ]);

        return [
            ...$this->locationsReadRoutesPayload(),
            ...$this->locationsMutationRoutesPayload($disposable),
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function locationsReadRoutesPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.locations.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.locations.data', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.locations.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'admin_user_id' => $this->partnerAdmin->id,
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.locations.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'admin_user_id' => 'none',
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
                'data'   => ['columns' => ['name' => true, 'admin_user_label' => true]],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.locations.show', $this->location->id),
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function locationsMutationRoutesPayload(?Location $disposable = null): array
    {
        $disposable ??= Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Mutation target object',
        ]);

        return [
            [
                'method' => 'POST',
                'url'    => route('admin.locations.store'),
                'data'   => [
                    'name'        => 'Created single perm object',
                    'district_id' => $this->district->id,
                    'admin_user_ids' => [$this->partnerAdmin->id],
                    'is_enabled'  => 1,
                ],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.locations.update', $this->location->id),
                'data'   => [
                    'name'        => 'Updated single perm object',
                    'district_id' => $this->district->id,
                    'admin_user_ids' => [],
                    'is_enabled'  => 1,
                ],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.locations.destroy', $disposable->id),
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function sportTypesRoutesPayload(): array
    {
        $disposable = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Disposable single perm sport',
        ]);

        return [
            ...$this->sportTypesReadRoutesPayload(),
            ...$this->sportTypesMutationRoutesPayload($disposable),
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function sportTypesReadRoutesPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.sport-types.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.sport-types.data', ['draw' => 1, 'start' => 0, 'length' => 10]),
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
                'data'   => ['columns' => ['name' => true]],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.sport-types.show', $this->sportType->id),
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function sportTypesMutationRoutesPayload(?SportType $disposable = null): array
    {
        $disposable ??= SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Mutation target sport',
        ]);

        return [
            [
                'method' => 'POST',
                'url'    => route('admin.sport-types.store'),
                'data'   => ['name' => 'Created single perm sport', 'is_enabled' => 1],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.sport-types.update', $this->sportType->id),
                'data'   => ['name' => 'Updated single perm sport', 'is_enabled' => 1],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.sport-types.destroy', $disposable->id),
            ],
        ];
    }
}
