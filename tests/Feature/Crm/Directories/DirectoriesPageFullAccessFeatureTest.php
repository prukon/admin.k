<?php

namespace Tests\Feature\Crm\Directories;

use App\Models\District;
use App\Models\Location;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();

        $this->district = District::factory()->forPartner($this->partner->id)->create([
            'name'       => 'Full access district',
            'sort_order' => 1,
        ]);

        $this->location = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Full access object',
            'is_enabled' => true,
        ]);
    }

    public function test_admin_all_directories_endpoints_return_200(): void
    {
        foreach ($this->allDirectoriesRoutesPayload() as $item) {
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

        foreach ($this->allDirectoriesRoutesPayload() as $item) {
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

    public function test_districts_html_page_and_locations_html_page_return_200_for_admin(): void
    {
        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertViewIs('admin.districts.index')
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Районы</a>', false)
            ->assertSee('>Объекты</a>', false);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertViewIs('admin.locations.index')
            ->assertSee('id="filter-district"', false)
            ->assertSee('name="district_id"', false);
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

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allDirectoriesRoutesPayload(): array
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
                'method' => 'POST',
                'url'    => route('admin.districts.columns-settings.save'),
                'data'   => ['columns' => ['name' => true, 'locations_count' => true]],
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
}
