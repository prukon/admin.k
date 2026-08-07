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

    public function test_abonement_context_returns_weekdays_per_team(): void
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

        $this->makeMonthlyFixedAssignment($student, (int) $teamA->id, '2026-08-01', lessons: 2);
        $this->makeMonthlyFixedAssignment($student, (int) $teamB->id, '2026-08-01', lessons: 2);

        $response = $this->getJson(route('schedule.abonement.context', $student))
            ->assertOk()
            ->assertJsonPath('team_locked', false);

        $this->assertEqualsCanonicalizing([$teamA->id, $teamB->id], $response->json('user.team_ids'));

        $teams = collect($response->json('teams'))->keyBy('id');
        $this->assertSame([1], $teams[$teamA->id]['weekdays']);
        $this->assertSame([3], $teams[$teamB->id]['weekdays']);
    }

    public function test_abonement_context_excludes_teams_without_setting_prices_fixed(): void
    {
        $teamWith = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamEmpty = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent($teamWith->id);
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamEmpty->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->makeMonthlyFixedAssignment($student, (int) $teamWith->id, '2026-08-01', lessons: 2);
        $this->makeFixedAssignment($student, lessons: 2); // classic — не в модалке

        $response = $this->getJson(route('schedule.abonement.context', $student))
            ->assertOk()
            ->assertJsonPath('team_locked', true)
            ->assertJsonPath('team_id', (int) $teamWith->id);

        $teamIds = collect($response->json('teams'))->pluck('id')->all();
        $this->assertSame([(int) $teamWith->id], array_map('intval', $teamIds));
        $this->assertTrue(
            collect($response->json('assignments'))->every(
                fn ($a) => (bool) ($a['from_setting_prices'] ?? false)
            )
        );
    }

    public function test_abonement_context_locks_team_from_journal_filter(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent($teamA->id);
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->makeMonthlyFixedAssignment($student, (int) $teamA->id, '2026-08-01', lessons: 2);
        $this->makeMonthlyFixedAssignment($student, (int) $teamB->id, '2026-08-01', lessons: 2);

        $this->getJson(route('schedule.abonement.context', $student).'?context_team_id='.$teamB->id)
            ->assertOk()
            ->assertJsonPath('team_locked', true)
            ->assertJsonPath('team_id', (int) $teamB->id)
            ->assertJsonPath('teams.0.id', (int) $teamB->id);
    }
}
