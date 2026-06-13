<?php

namespace Tests\Feature\Crm\SportTypes;

use App\Models\SportType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к /admin/sport-types и связанным эндпоинтам
 * (sport_types.view / sport_types.manage → 200, без права → 403).
 */
final class SportTypesPageFullAccessFeatureTest extends CrmTestCase
{
    private SportType $sportType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();

        $this->sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Full access sport',
            'sort' => 1,
            'is_enabled' => true,
        ]);
    }

    public function test_sport_types_index_page_returns_200_with_view_permission(): void
    {
        $this->grantSportTypesViewForUser($this->user);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertViewIs('admin.sport-types.index')
            ->assertSee('Справочники', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('>Виды спорта</a>', false)
            ->assertSee('id="sport-types-table"', false)
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('sportTypesReportFiltersCollapse', false)
            ->assertSee('sportTypesColumnsDropdown', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee("linkClass: 'js-sport-type-edit'", false);
    }

    public function test_all_sport_types_page_endpoints_return_200_for_admin_with_manage(): void
    {
        $this->grantSportTypesViewForUser($this->user);
        $this->grantSportTypesManageForUser($this->user);

        $this->get(route('admin.sport-types.index'))->assertOk();

        $this->getJson(route('admin.sport-types.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.sport-types.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'name' => 'Full access',
            'status' => 'active',
        ]))->assertOk();

        $this->getJson(route('admin.sport-types.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.sport-types.columns-settings.save'), [
            'columns' => [
                'sort' => true,
                'name' => true,
                'teams_count' => true,
                'is_enabled_label' => true,
                'actions' => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('admin.sport-types.show', $this->sportType->id))
            ->assertOk()
            ->assertJsonPath('id', $this->sportType->id);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'sport_type_id' => $this->sportType->id,
            'title' => 'Linked team',
        ]);

        $this->postJson(route('admin.sport-types.store'), [
            'name' => 'Created via full access test',
            'sort' => 2,
            'is_enabled' => 1,
        ])->assertOk();

        $this->putJson(route('admin.sport-types.update', $this->sportType->id), [
            'name' => 'Full access sport updated',
            'description' => 'Описание',
            'sort' => 3,
            'is_enabled' => 1,
        ])->assertOk();

        $disposable = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Disposable for delete smoke',
        ]);

        $this->deleteJson(route('admin.sport-types.destroy', $disposable->id))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_with_only_sport_types_view_can_access_read_endpoints_and_mutations_return_403(): void
    {
        $actor = $this->createUserWithoutPermission('sport_types.view', $this->partner);
        $this->grantSportTypesViewForUser($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertDontSee('id="new-sport-type"', false)
            ->assertDontSee('sportTypeCreateModal', false);

        $this->getJson(route('admin.sport-types.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->getJson(route('admin.sport-types.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.sport-types.columns-settings.save'), [
            'columns' => ['name' => true],
        ])->assertOk();

        $this->getJson(route('admin.sport-types.show', $this->sportType->id))->assertOk();

        $this->postJson(route('admin.sport-types.store'), [
            'name' => 'Forbidden create',
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->putJson(route('admin.sport-types.update', $this->sportType->id), [
            'name' => 'Forbidden update',
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->deleteJson(route('admin.sport-types.destroy', $this->sportType->id))
            ->assertStatus(403);
    }

    public function test_user_with_sport_types_view_and_manage_can_access_all_section_endpoints_return_ok(): void
    {
        $actor = $this->createUserWithoutPermission('sport_types.view', $this->partner);
        $this->grantSportTypesViewForUser($actor);
        $this->grantSportTypesManageForUser($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Manage smoke sport',
        ]);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('id="new-sport-type"', false)
            ->assertSee('sportTypeEditModal', false)
            ->assertSee('id="sportTypeDeleteBtn"', false);

        $this->getJson(route('admin.sport-types.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'name' => 'Manage',
            'status' => 'active',
        ]))->assertOk();

        $this->getJson(route('admin.sport-types.show', $sportType->id))->assertOk();

        $this->postJson(route('admin.sport-types.store'), [
            'name' => 'Created with manage',
            'is_enabled' => 1,
        ])->assertOk();

        $this->putJson(route('admin.sport-types.update', $sportType->id), [
            'name' => 'Manage smoke updated',
            'is_enabled' => 0,
        ])->assertOk();

        $toDelete = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'To delete manage smoke',
        ]);

        $this->deleteJson(route('admin.sport-types.destroy', $toDelete->id))
            ->assertOk()
            ->assertJsonPath('message', 'Вид спорта удалён');
    }

    public function test_sport_types_index_returns_403_without_sport_types_view(): void
    {
        $actor = $this->createUserWithoutPermission('sport_types.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.sport-types.index'))->assertStatus(403);
    }

    public function test_sport_types_data_returns_403_without_sport_types_view(): void
    {
        $actor = $this->createUserWithoutPermission('sport_types.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.sport-types.data', ['draw' => 1]))->assertStatus(403);
    }

    public function test_columns_settings_return_403_without_sport_types_view(): void
    {
        $actor = $this->createUserWithoutPermission('sport_types.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.sport-types.columns-settings.get'))->assertStatus(403);

        $this->postJson(route('admin.sport-types.columns-settings.save'), [
            'columns' => ['name' => true],
        ])->assertStatus(403);
    }

    public function test_show_returns_403_without_sport_types_view(): void
    {
        $actor = $this->createUserWithoutPermission('sport_types.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.sport-types.show', $this->sportType->id))->assertStatus(403);
    }

    public function test_guest_cannot_access_any_sport_types_endpoint(): void
    {
        Auth::logout();

        $endpoints = [
            fn () => $this->get(route('admin.sport-types.index')),
            fn () => $this->getJson(route('admin.sport-types.data', ['draw' => 1])),
            fn () => $this->getJson(route('admin.sport-types.columns-settings.get')),
            fn () => $this->postJson(route('admin.sport-types.columns-settings.save'), [
                'columns' => ['name' => true],
            ]),
            fn () => $this->getJson(route('admin.sport-types.show', $this->sportType->id)),
            fn () => $this->postJson(route('admin.sport-types.store'), [
                'name' => 'x',
                'is_enabled' => 1,
            ]),
            fn () => $this->putJson(route('admin.sport-types.update', $this->sportType->id), [
                'name' => 'x',
                'is_enabled' => 1,
            ]),
            fn () => $this->deleteJson(route('admin.sport-types.destroy', $this->sportType->id)),
        ];

        foreach ($endpoints as $call) {
            $status = $call()->getStatusCode();
            $this->assertContains($status, [302, 401, 403], 'Unexpected status: ' . $status);
        }
    }

    private function grantSportTypesViewForUser(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('sport_types.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantSportTypesManageForUser(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('sport_types.manage'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
