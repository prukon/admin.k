<?php

namespace Tests\Feature\Crm\Teams;

use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Новый функционал страницы /admin/teams: тулбар, фильтры, поиск DataTables, пагинация, порядок полей в модалках.
 */
final class TeamsPageNewFeaturesFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_teams_datatable_default_page_length_is_ten(): void
    {
        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('pageLength: 10', $html);
        $this->assertStringNotContainsString('pageLength: 20', $html);
    }

    public function test_teams_page_includes_filter_panel_scripts_and_collapse_ids(): void
    {
        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('teamsFilterParams()', $html);
        $this->assertStringContainsString('teamsHasNonDefaultFilters()', $html);
        $this->assertStringContainsString('syncTeamsFiltersCollapseState()', $html);
        $this->assertStringContainsString("defaultFilterStatus = 'active'", $html);
        $this->assertStringContainsString('teams-report-filters', $html);
        $this->assertStringContainsString('payments-report-filters-submit', $html);
        $this->assertStringContainsString('payments-report-filters-reset', $html);
    }

    public function test_teams_data_filter_combinations_return_ok_with_json_structure(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Комбо фильтр',
            'is_enabled' => 1,
        ]);

        $profile = $this->makeTrainerProfile('Комбо', 'Тренер');

        DB::table('team_trainer')->insert([
            'partner_id'         => $this->partner->id,
            'team_id'            => $team->id,
            'trainer_profile_id' => $profile->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $queries = [
            '/admin/teams/data?draw=1&start=0&length=10',
            '/admin/teams/data?draw=1&start=0&length=10&status=active',
            '/admin/teams/data?draw=1&start=0&length=10&status=inactive',
            '/admin/teams/data?draw=1&start=0&length=10&title=Комбо',
            '/admin/teams/data?draw=1&start=0&length=10&trainer_profile_id=' . $profile->id,
            '/admin/teams/data?draw=1&start=0&length=10&trainer_profile_id=none',
            '/admin/teams/data?draw=1&start=0&length=10&search[value]=Комбо',
            '/admin/teams/data?draw=1&start=0&length=10&status=active&title=Комбо&trainer_profile_id=' . $profile->id,
            '/admin/teams/data?draw=1&start=0&length=10&status=active&search[value]=Комбо&trainer_profile_id=' . $profile->id,
        ];

        foreach ($queries as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonStructure([
                    'draw',
                    'recordsTotal',
                    'recordsFiltered',
                    'data',
                ]);
        }
    }

    public function test_teams_data_status_active_excludes_inactive_teams(): void
    {
        $active = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Активная группа статус',
            'is_enabled' => 1,
        ]);

        $inactive = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Неактивная группа статус',
            'is_enabled' => 0,
        ]);

        $ids = collect(
            $this->getJson('/admin/teams/data?draw=1&start=0&length=100&status=active')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_teams_data_search_finds_team_by_order_by_number(): void
    {
        $target = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Порядок уникальный заголовок',
            'order_by'   => 424242,
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Другая группа порядок',
            'order_by'   => 1,
        ]);

        $json = $this->getJson('/admin/teams/data?search[value]=424242')
            ->assertOk()
            ->json();

        $this->assertSame(1, $json['recordsFiltered']);
        $this->assertSame($target->id, $json['data'][0]['id']);
    }

    public function test_teams_data_combined_title_and_trainer_filters(): void
    {
        $match = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'СовпадениеКомбо',
        ]);

        $otherTitle = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ДругоеКомбо',
        ]);

        $profile = $this->makeTrainerProfile('Иван', 'Совпадение');

        DB::table('team_trainer')->insert([
            [
                'partner_id'         => $this->partner->id,
                'team_id'            => $match->id,
                'trainer_profile_id' => $profile->id,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'partner_id'         => $this->partner->id,
                'team_id'            => $otherTitle->id,
                'trainer_profile_id' => $profile->id,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
        ]);

        $titles = collect(
            $this->getJson(
                '/admin/teams/data?title=СовпадениеКомбо&trainer_profile_id=' . $profile->id
            )
                ->assertOk()
                ->json('data')
        )->pluck('title')->all();

        $this->assertSame(['СовпадениеКомбо'], $titles);
    }

    public function test_create_team_modal_activity_is_last_and_order_by_is_before_activity(): void
    {
        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->getContent();

        $this->assertFieldComesBefore($html, 'id="trainer_profile_id"', 'id="order_by"');
        $this->assertFieldComesBefore($html, 'id="order_by"', 'id=\'activity\'');
        $this->assertFieldComesBefore($html, 'id=\'activity\'', 'modal-footer-create-team');
    }

    public function test_edit_team_modal_activity_is_last_and_order_by_is_before_activity(): void
    {
        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->getContent();

        $this->assertFieldComesBefore($html, 'id="edit-trainer-profile-id"', 'id="edit-order_by"');
        $this->assertFieldComesBefore($html, 'id="edit-order_by"', 'id="edit-activity"');
        $this->assertFieldComesBefore($html, 'id="edit-activity"', 'id="update-team-btn"');
    }

    public function test_create_team_modal_without_trainers_view_still_has_order_before_activity(): void
    {
        $actor = $this->userWithOnlyGroupsView();
        $this->actingAs($actor);

        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="trainer_profile_id"', $html);
        $this->assertFieldComesBefore($html, 'id="order_by"', 'id=\'activity\'');
        $this->assertFieldComesBefore($html, 'id=\'activity\'', 'modal-footer-create-team');
    }

    private function userWithOnlyGroupsView(): User
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return $actor;
    }

    private function makeTrainerProfile(string $name, string $lastname): TrainerProfile
    {
        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $trainerRoleId,
            'name'       => $name,
            'lastname'   => $lastname,
            'email'      => strtolower($name) . '-' . uniqid() . '@example.test',
        ]);

        return TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $user->id,
        ]);
    }

    private function assertFieldComesBefore(string $html, string $earlierMarker, string $laterMarker): void
    {
        $earlierPos = strpos($html, $earlierMarker);
        $laterPos = strpos($html, $laterMarker);

        $this->assertNotFalse($earlierPos, "Marker not found: {$earlierMarker}");
        $this->assertNotFalse($laterPos, "Marker not found: {$laterMarker}");
        $this->assertLessThan($laterPos, $earlierPos, "{$earlierMarker} must appear before {$laterMarker}");
    }
}
