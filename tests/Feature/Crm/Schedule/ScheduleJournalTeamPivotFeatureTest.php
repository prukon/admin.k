<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Журнал /schedule: pivot team_user — несколько групп, attach/detach, контекст тренера.
 */
final class ScheduleJournalTeamPivotFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_sync_teams_replaces_student_groups(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Alpha']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Beta']);
        $teamC = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Gamma']);
        $student = $this->makeStudent($teamA->id);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson(route('user.sync.teams', $student), [
            'team_ids' => [$teamB->id, $teamC->id],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('teams_label', 'Beta, Gamma');

        $teamIds = DB::table('team_user')
            ->where('user_id', $student->id)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$teamB->id, $teamC->id], $teamIds);
    }

    public function test_sync_teams_with_empty_array_detaches_all(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();

        $this->postJson(route('user.sync.teams', $student), [
            'team_ids' => [],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('teams_label', null);

        $this->assertSame(0, DB::table('team_user')->where('user_id', $student->id)->count());
    }

    public function test_set_group_attaches_second_team_without_removing_first(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Alpha']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Beta']);
        $student = $this->makeStudent($teamA->id);

        $this->postJson(route('user.set.group', $student), [
            'team_id' => $teamB->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('teams_label', 'Alpha, Beta');

        $teamIds = DB::table('team_user')
            ->where('user_id', $student->id)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$teamA->id, $teamB->id], $teamIds);
    }

    public function test_set_group_with_empty_team_id_detaches_all_teams(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();

        $this->postJson(route('user.set.group', $student), [
            'team_id' => '',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('teams_label', null);

        $this->assertSame(0, DB::table('team_user')->where('user_id', $student->id)->count());
    }

    public function test_set_group_rejects_foreign_partner_team(): void
    {
        $student = $this->makeStudent(null);
        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);

        $this->postJson(route('user.set.group', $student), [
            'team_id' => $foreignTeam->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['team_id']);
    }

    public function test_cell_context_uses_context_team_id_for_default_trainer(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Alpha']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Beta']);

        $trainerA = $this->makeTrainerProfile('Тренер A');
        $trainerB = $this->makeTrainerProfile('Тренер B');

        foreach ([[$teamA, $trainerA], [$teamB, $trainerB]] as [$team, $trainer]) {
            DB::table('team_trainer')->insert([
                'partner_id' => $this->partner->id,
                'team_id' => $team->id,
                'trainer_profile_id' => $trainer->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $student = $this->makeStudent($teamA->id);
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => '2026-05-10',
            'context_team_id' => $teamB->id,
        ]))
            ->assertOk()
            ->assertJsonPath('team_default_trainer_profile_id', $trainerB->id)
            ->assertJsonPath('team_id', $teamB->id)
            ->assertJsonPath('teams_label', 'Alpha, Beta');
    }

    public function test_cell_context_returns_team_ids_and_teams_label(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => '2026-05-10',
        ]))
            ->assertOk()
            ->assertJsonStructure(['team_ids', 'teams_label'])
            ->assertJsonPath('team_ids', [$team->id]);
    }

    public function test_index_team_filter_shows_student_in_any_of_their_teams(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'PivotA',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'PivotB',
        ]);

        $student = $this->makeStudent($teamA->id);
        $student->update(['name' => 'Мульти', 'lastname' => 'Группа']);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$teamA->id, $teamB->id] as $filterTeamId) {
            $this->get(route('schedule.index', [
                'year' => 2026,
                'month' => '05',
                'team' => $filterTeamId,
            ]))
                ->assertOk()
                ->assertSee($student->full_name, false);
        }
    }

    public function test_user_schedule_info_uses_context_team_for_weekdays(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);

        DB::table('team_weekdays')->insert([
            ['team_id' => $teamA->id, 'weekday_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['team_id' => $teamB->id, 'weekday_id' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $student = $this->makeStudent($teamA->id);
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(route('user.schedule.info', $student) . '?context_team_id=' . $teamB->id)
            ->assertOk()
            ->assertJsonPath('user.team_id', $teamB->id)
            ->assertJsonPath('groupWeekdays', [3]);
    }
}
