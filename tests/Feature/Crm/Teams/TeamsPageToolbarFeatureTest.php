<?php

namespace Tests\Feature\Crm\Teams;

use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Страница «Группы» (/admin/teams): тулбар, фильтры, фильтр по тренеру.
 */
final class TeamsPageToolbarFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_teams_page_renders_toolbar_filter_panel_and_table(): void
    {
        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewIs('admin.team')
            ->assertViewHas(['weekdays', 'trainerOptions'])
            ->getContent();

        $this->assertStringContainsString('>Группы</h4>', $html);
        $this->assertStringContainsString('>Группы</h1>', $html);
        $this->assertStringContainsString('payments-report-surface', $html);
        $this->assertStringContainsString('admin-list-toolbar', $html);
        $this->assertStringContainsString('payments-report-toolbar-actions--many', $html);

        $this->assertStringContainsString('id="new-team"', $html);
        $this->assertStringContainsString('>Добавить</span>', $html);
        $this->assertStringContainsString('>История</span>', $html);
        $this->assertStringContainsString('id="teamsReportFiltersToggle"', $html);
        $this->assertStringContainsString('>Фильтры</span>', $html);
        $this->assertStringContainsString('id="columnsDropdown"', $html);
        $this->assertStringContainsString('>Колонки</span>', $html);

        $this->assertStringContainsString('id="teamsReportFiltersCollapse"', $html);
        $this->assertStringContainsString('id="teams-report-filters"', $html);
        $this->assertStringContainsString('id="filter-title"', $html);
        $this->assertStringContainsString('id="filter-trainer"', $html);
        $this->assertStringContainsString('id="filter-status"', $html);
        $this->assertStringContainsString('value="active" selected', $html);
        $this->assertStringContainsString('value="none">Без тренера</option>', $html);
        $this->assertStringContainsString('id="filter-apply"', $html);
        $this->assertStringContainsString('id="filter-reset"', $html);

        $this->assertStringContainsString('id="teams-table"', $html);
        $this->assertStringContainsString('id="createTeamModal"', $html);
        $this->assertStringContainsString('id="historyModal"', $html);

        $this->assertStringNotContainsString('id="search-container"', $html);
        $this->assertStringNotContainsString('Добавить группу', $html);

        $addPos = strpos($html, '>Добавить</span>');
        $historyPos = strpos($html, '>История</span>');
        $filtersPos = strpos($html, '>Фильтры</span>');
        $columnsPos = strpos($html, '>Колонки</span>');

        $this->assertNotFalse($addPos);
        $this->assertNotFalse($historyPos);
        $this->assertNotFalse($filtersPos);
        $this->assertNotFalse($columnsPos);
        $this->assertLessThan($historyPos, $addPos);
        $this->assertLessThan($filtersPos, $historyPos);
        $this->assertLessThan($columnsPos, $filtersPos);
    }

    public function test_teams_page_hides_trainer_filter_without_trainers_view(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($actor);

        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="filter-trainer"', $html);
        $this->assertStringNotContainsString('data-column-key="trainer_label"', $html);
    }

    public function test_teams_data_accepts_trainer_profile_id_filter(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Фильтр тренер UI',
        ]);

        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');
        $trainerUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $trainerRoleId,
            'name'       => 'Сергей',
            'lastname'   => 'Тулбаров',
        ]);
        $profile = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $trainerUser->id,
        ]);

        DB::table('team_trainer')->insert([
            'partner_id'         => $this->partner->id,
            'team_id'            => $team->id,
            'trainer_profile_id' => $profile->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Другая без тренера',
        ]);

        $json = $this->getJson('/admin/teams/data?trainer_profile_id=' . $profile->id)
            ->assertOk()
            ->json();

        $titles = collect($json['data'])->pluck('title')->all();
        $this->assertContains('Фильтр тренер UI', $titles);
        $this->assertNotContains('Другая без тренера', $titles);
    }
}
