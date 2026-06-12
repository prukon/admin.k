<?php

namespace Tests\Feature\Crm\Locations;

use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к странице /admin/locations и связанным эндпоинтам
 * (locations.view / locations.manage → 200, без права → 403).
 */
final class LocationsPageFullAccessFeatureTest extends CrmTestCase
{
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();

        $this->location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Full access smoke',
            'address' => 'ул. Тестовая, 1',
            'is_enabled' => true,
        ]);
    }

    public function test_locations_index_page_returns_200_with_locations_view(): void
    {
        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertViewIs('admin.locations.index')
            ->assertSee('id="locations-table"', false)
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('locationsReportFiltersCollapse', false)
            ->assertSee('locationsColumnsDropdown', false)
            ->assertSee('KidsCrmDataTable.create', false);
    }

    public function test_all_locations_page_endpoints_return_200_for_admin_with_manage(): void
    {
        $this->get(route('admin.locations.index'))->assertOk();

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'name' => 'Full access',
            'status' => 'active',
        ]))->assertOk();

        $this->getJson(route('admin.locations.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.locations.columns-settings.save'), [
            'columns' => [
                'id' => true,
                'name' => true,
                'address' => true,
                'is_enabled_label' => true,
                'actions' => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('admin.locations.show', $this->location->id))
            ->assertOk()
            ->assertJsonPath('id', $this->location->id);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Full access team',
        ]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Created via full access test',
            'address' => 'Адрес',
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertOk();

        $this->putJson(route('admin.locations.update', $this->location->id), [
            'name' => 'Full access smoke updated',
            'address' => 'ул. Обновлённая',
            'description' => 'Описание',
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertOk();

        $disposable = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Disposable for delete smoke',
        ]);

        $this->deleteJson(route('admin.locations.destroy', $disposable->id))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_with_only_locations_view_can_access_read_endpoints_and_mutations_return_403(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantLocationsViewForUser($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertDontSee('id="new-location"', false)
            ->assertDontSee('locationCreateModal', false);

        $this->getJson(route('admin.locations.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->getJson(route('admin.locations.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.locations.columns-settings.save'), [
            'columns' => ['id' => true, 'name' => true],
        ])->assertOk();

        $this->getJson(route('admin.locations.show', $this->location->id))->assertOk();

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Forbidden create',
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->putJson(route('admin.locations.update', $this->location->id), [
            'name' => 'Forbidden update',
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->deleteJson(route('admin.locations.destroy', $this->location->id))
            ->assertStatus(403);
    }

    public function test_user_with_locations_view_and_manage_can_access_all_section_endpoints_return_ok(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantLocationsViewForUser($actor);
        $this->grantLocationsManageForUser($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Manage smoke location',
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Manage smoke team',
        ]);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="new-location"', false)
            ->assertSee('locationEditModal', false)
            ->assertSee('id="locationDeleteBtn"', false)
            ->assertSee('showConfirmDeleteModal', false)
            ->assertSee('KidsCrmGenericMultiselectSelect2', false)
            ->assertSee('KidsCrmDataTable.create', false);

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'name' => 'Manage',
            'status' => 'active',
        ]))->assertOk();

        $this->getJson(route('admin.locations.show', $loc->id))->assertOk();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Manage pivot team',
        ]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Created with manage',
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertOk();

        $this->putJson(route('admin.locations.update', $loc->id), [
            'name' => 'Manage smoke updated',
            'is_enabled' => 0,
            'team_ids' => [$team->id],
        ])->assertOk();

        $toDelete = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'To delete manage smoke',
        ]);

        $this->deleteJson(route('admin.locations.destroy', $toDelete->id))
            ->assertOk()
            ->assertJsonPath('message', 'Локация удалена');
    }

    public function test_locations_index_returns_403_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.locations.index'))->assertStatus(403);
    }

    public function test_locations_data_returns_403_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.locations.data', ['draw' => 1]))->assertStatus(403);
    }

    public function test_columns_settings_return_403_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.locations.columns-settings.get'))->assertStatus(403);

        $this->postJson(route('admin.locations.columns-settings.save'), [
            'columns' => ['name' => true],
        ])->assertStatus(403);
    }

    public function test_show_returns_403_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.locations.show', $this->location->id))->assertStatus(403);
    }

    public function test_all_locations_endpoints_return_200_with_teams_ui_and_pivot_payloads(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Pivot full stack',
        ]);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="colLocationTeams"', false)
            ->assertSee('id="locationCreateTeamIds"', false)
            ->assertSee('id="locationEditTeamIds"', false);

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'address',
                        'teams_label',
                        'teams_label_full',
                        'teams_titles',
                        'is_enabled',
                        'is_enabled_label',
                    ],
                ],
            ]);

        $create = $this->postJson(route('admin.locations.store'), [
            'name' => 'Full stack with teams',
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertOk();

        $createdId = (int) ($create->json('location.id') ?? 0);
        $this->assertGreaterThan(0, $createdId);

        $this->getJson(route('admin.locations.show', $createdId))
            ->assertOk()
            ->assertJsonPath('team_ids', [$team->id]);

        $this->putJson(route('admin.locations.update', $createdId), [
            'name' => 'Full stack updated',
            'is_enabled' => 1,
            'team_ids' => [],
        ])->assertOk();

        $this->getJson(route('admin.locations.show', $createdId))
            ->assertOk()
            ->assertJsonPath('team_ids', []);

        $this->deleteJson(route('admin.locations.destroy', $createdId))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_guest_cannot_access_any_locations_endpoint(): void
    {
        Auth::logout();

        $endpoints = [
            fn () => $this->get(route('admin.locations.index')),
            fn () => $this->getJson(route('admin.locations.data', ['draw' => 1])),
            fn () => $this->getJson(route('admin.locations.columns-settings.get')),
            fn () => $this->postJson(route('admin.locations.columns-settings.save'), [
                'columns' => ['name' => true],
            ]),
            fn () => $this->getJson(route('admin.locations.show', $this->location->id)),
            fn () => $this->postJson(route('admin.locations.store'), [
                'name' => 'x',
                'is_enabled' => 1,
            ]),
            fn () => $this->putJson(route('admin.locations.update', $this->location->id), [
                'name' => 'x',
                'is_enabled' => 1,
            ]),
            fn () => $this->deleteJson(route('admin.locations.destroy', $this->location->id)),
        ];

        foreach ($endpoints as $call) {
            $status = $call()->getStatusCode();
            $this->assertContains($status, [302, 401, 403], 'Unexpected status: ' . $status);
        }
    }

    private function grantLocationsViewForUser(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantLocationsManageForUser(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('locations.manage'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
