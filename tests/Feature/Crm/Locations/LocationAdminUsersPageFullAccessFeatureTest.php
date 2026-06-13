<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Locations;

use App\Models\Location;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\LocationAdminUsersSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Полный smoke-доступ к /admin/locations с M2M-администраторами:
 * страница и все endpoint'ы → 200 при наличии прав.
 */
final class LocationAdminUsersPageFullAccessFeatureTest extends CrmTestCase
{
    private User $partnerAdmin;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();

        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');
        $this->partnerAdmin = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $adminRoleId,
            'is_enabled' => 1,
            'name' => 'Smoke',
            'lastname' => 'Admin',
        ]);

        $this->location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Admin M2M smoke location',
            'is_enabled' => true,
        ]);
    }

    public function test_admin_all_locations_admin_m2m_endpoints_return_200(): void
    {
        $secondAdmin = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => (int) Role::query()->where('name', 'admin')->value('id'),
            'is_enabled' => 1,
            'name' => 'Второй',
            'lastname' => 'Админ',
        ]);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertViewIs('admin.locations.index')
            ->assertSee('id="filter-admin"', false)
            ->assertSee('id="colLocationAdmin"', false)
            ->assertSee('id="locationCreateAdminUserIds"', false)
            ->assertSee('id="locationEditAdminUserIds"', false)
            ->assertSee('type: \'list\'', false)
            ->assertSee('itemsKey: \'admin_user_names\'', false);

        foreach ($this->adminReadEndpointsPayload() as $item) {
            $this->assertEndpointReturns200($item, 'admin M2M read');
        }

        $create = $this->postJson(route('admin.locations.store'), [
            'name' => 'M2M full access create',
            'admin_user_ids' => [$this->partnerAdmin->id, $secondAdmin->id],
            'is_enabled' => 1,
        ])->assertOk();

        $createdId = (int) $create->json('location.id');
        $this->assertGreaterThan(0, $createdId);

        $this->getJson(route('admin.locations.show', $createdId))
            ->assertOk()
            ->assertJsonPath('admin_user_ids', [$this->partnerAdmin->id, $secondAdmin->id]);

        $this->putJson(route('admin.locations.update', $createdId), [
            'name' => 'M2M full access updated',
            'admin_user_ids' => [$secondAdmin->id],
            'is_enabled' => 1,
        ])->assertOk();

        $this->getJson(route('admin.locations.show', $createdId))
            ->assertOk()
            ->assertJsonPath('admin_user_ids', [$secondAdmin->id]);

        $this->deleteJson(route('admin.locations.destroy', $createdId))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_with_locations_view_and_manage_all_admin_m2m_endpoints_return_200(): void
    {
        $actor = $this->createUserWithLocationsPermissions(['locations.view', 'locations.manage']);
        $this->actingAs($actor);

        foreach ($this->adminReadEndpointsPayload() as $item) {
            $this->assertEndpointReturns200($item, 'view+manage M2M read');
        }

        foreach ($this->adminMutationEndpointsPayload() as $item) {
            $this->assertEndpointReturns200($item, 'view+manage M2M mutation');
        }
    }

    public function test_user_with_only_locations_view_admin_read_endpoints_return_200_mutations_return_403(): void
    {
        app(LocationAdminUsersSyncService::class)->syncAdminsForLocation(
            $this->location,
            [$this->partnerAdmin->id]
        );

        $actor = $this->createUserWithLocationsPermissions(['locations.view']);
        $this->actingAs($actor);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="filter-admin"', false)
            ->assertSee('data-column-key="admin_user_label"', false)
            ->assertDontSee('id="locationCreateAdminUserIds"', false);

        foreach ($this->adminReadEndpointsPayload() as $item) {
            $this->assertEndpointReturns200($item, 'view only M2M read');
        }

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Forbidden M2M create',
            'admin_user_ids' => [$this->partnerAdmin->id],
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->putJson(route('admin.locations.update', $this->location->id), [
            'name' => 'Forbidden M2M update',
            'admin_user_ids' => [],
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->deleteJson(route('admin.locations.destroy', $this->location->id))
            ->assertStatus(403);
    }

    public function test_guest_cannot_access_locations_admin_m2m_endpoints(): void
    {
        Auth::logout();

        foreach ([
            fn () => $this->get(route('admin.locations.index')),
            fn () => $this->getJson(route('admin.locations.data', [
                'draw' => 1,
                'admin_user_id' => $this->partnerAdmin->id,
            ])),
            fn () => $this->postJson(route('admin.locations.store'), [
                'name' => 'Guest',
                'admin_user_ids' => [$this->partnerAdmin->id],
                'is_enabled' => 1,
            ]),
        ] as $call) {
            $status = $call()->getStatusCode();
            $this->assertContains($status, [302, 401, 403], 'Unexpected guest status: ' . $status);
        }
    }

    public function test_locations_data_with_admin_filters_sort_and_columns_settings_return_200(): void
    {
        app(LocationAdminUsersSyncService::class)->syncAdminsForLocation(
            $this->location,
            [$this->partnerAdmin->id]
        );

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'admin_user_id' => $this->partnerAdmin->id,
            'status' => 'active',
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'admin_user_ids',
                    'admin_user_label',
                    'admin_user_label_full',
                    'admin_user_names',
                ]],
            ]);

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'admin_user_id' => 'none',
        ]))->assertOk();

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'order' => [['column' => 0, 'dir' => 'asc']],
            'columns' => [['name' => 'admin_user_label']],
        ]))->assertOk();

        $this->postJson(route('admin.locations.columns-settings.save'), [
            'columns' => [
                'name' => true,
                'admin_user_label' => true,
                'district_name' => false,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('admin.locations.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('admin_user_label', true);
    }

    public function test_teams_page_admin_filter_with_m2m_location_returns_200(): void
    {
        $this->grantPermission('groups.view');
        $this->grantPermission('locations.view');

        app(LocationAdminUsersSyncService::class)->syncAdminsForLocation(
            $this->location,
            [$this->partnerAdmin->id]
        );

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Team for M2M admin filter',
            'location_id' => $this->location->id,
        ]);

        $this->get(route('admin.team.index'))->assertOk();

        $this->getJson('/admin/teams/data?draw=1&start=0&length=10&admin_user_id=' . $this->partnerAdmin->id)
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson('/admin/teams/data?draw=1&start=0&length=10&admin_user_id=none')
            ->assertOk();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function adminReadEndpointsPayload(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.locations.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.locations.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                ]),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.locations.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'admin_user_id' => $this->partnerAdmin->id,
                ]),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.locations.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'admin_user_id' => 'none',
                ]),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.locations.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'order' => [['column' => 0, 'dir' => 'desc']],
                    'columns' => [['name' => 'admin_user_label']],
                ]),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.locations.columns-settings.get'),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.locations.columns-settings.save'),
                'data' => ['columns' => ['admin_user_label' => true, 'name' => true]],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.locations.show', $this->location->id),
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function adminMutationEndpointsPayload(): array
    {
        $disposable = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Disposable M2M mutation target',
        ]);

        return [
            [
                'method' => 'POST',
                'url' => route('admin.locations.store'),
                'data' => [
                    'name' => 'Created via M2M mutation payload',
                    'admin_user_ids' => [$this->partnerAdmin->id],
                    'is_enabled' => 1,
                ],
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.locations.update', $this->location->id),
                'data' => [
                    'name' => 'Updated via M2M mutation payload',
                    'admin_user_ids' => [$this->partnerAdmin->id],
                    'is_enabled' => 1,
                ],
            ],
            [
                'method' => 'DELETE',
                'url' => route('admin.locations.destroy', $disposable->id),
            ],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function createUserWithLocationsPermissions(array $permissions): User
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');
        $actor = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'is_enabled' => 1,
        ]);

        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        return $actor;
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}  $item
     */
    private function assertEndpointReturns200(array $item, string $context): void
    {
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
            sprintf('%s: %s %s returned %d', $context, $item['method'], $item['url'], $response->getStatusCode())
        );
    }
}
