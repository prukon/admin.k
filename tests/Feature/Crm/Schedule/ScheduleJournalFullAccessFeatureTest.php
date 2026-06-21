<?php

namespace Tests\Feature\Crm\Schedule;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к журналу /schedule: smoke 200 для страницы и всех эндпоинтов при schedule.view.
 */
final class ScheduleJournalFullAccessFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_schedule_index_returns_ok_with_journal_markers(): void
    {
        [$student] = $this->makeStudentTeamAndTrainer();

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('schedule-section', false)
            ->assertSee('id="filter-year"', false)
            ->assertSee('id="filter-month"', false)
            ->assertSee('id="filter-team"', false)
            ->assertSee('id="cellEditModal"', false)
            ->assertSee('id="cell-trainer-wrap"', false)
            ->assertSee('id="cell-trainer-profile-id"', false)
            ->assertSee('SCHEDULE_VISITED_STATUS_ID', false)
            ->assertSee('data-is-visited="1"', false)
            ->assertSee('id="status-' . $this->visitedStatusId . '"', false)
            ->assertSee($student->full_name, false);
    }

    public function test_schedule_index_with_year_month_and_team_filters_returns_ok(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();

        $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '05',
            'team' => $team->id,
        ]))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->assertSee('value="2026"', false)
            ->assertSee('value="05"', false)
            ->assertSee('value="' . $team->id . '"', false);
    }

    public function test_schedule_index_with_none_team_filter_returns_ok(): void
    {
        $student = $this->makeStudent(null);
        $student->update(['name' => 'БезГруппы', 'lastname' => 'Доступ']);

        $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '05',
            'team' => 'none',
        ]))
            ->assertOk()
            ->assertSee($student->full_name, false);
    }

    public function test_schedule_cell_context_returns_ok_for_student(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => '2026-05-15',
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'visited_status_id',
                'current_status_id',
                'team_id',
                'team_ids',
                'teams_label',
                'team_default_trainer_profile_id',
                'trainer_profile_id_for_select',
                'trainers',
            ])
            ->assertJsonPath('team_default_trainer_profile_id', $trainer->id)
            ->assertJsonPath('visited_status_id', $this->visitedStatusId);
    }

    public function test_schedule_update_returns_ok_for_student(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-15';

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => $date,
            'status_id' => $this->visitedStatusId,
            'description' => 'Комментарий smoke',
            'trainer_profile_id' => $trainer->id,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_schedule_logs_data_returns_ok(): void
    {
        [$student] = $this->makeStudentTeamAndTrainer();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => '2026-05-15',
            'status_id' => $this->visitedStatusId,
        ])->assertOk();

        $this->getJson(route('logs.data.schedule', ['draw' => 1]))
            ->assertOk();
    }

    public function test_user_schedule_info_returns_ok(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();

        $this->getJson(route('user.schedule.info', $student))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $student->id)
            ->assertJsonPath('user.team_id', $team->id);
    }

    public function test_user_set_group_returns_ok(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();

        $this->postJson(route('user.set.group', $student), [
            'team_id' => $team->id,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_user_sync_teams_returns_ok(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();

        $this->postJson(route('user.sync.teams', $student), [
            'team_ids' => [$team->id],
        ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['team_ids', 'teams_label']);
    }

    public function test_user_update_schedule_range_returns_ok(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        try {
            [$student] = $this->makeStudentTeamAndTrainer();
            $weekday = (int) now()->isoWeekday();
            $today = now()->toDateString();

            $this->postJson(route('user.update.schedule', $student), [
                'weekdays' => [$weekday],
                'date_from' => $today,
                'date_to' => $today,
            ])
                ->assertOk()
                ->assertJson(['success' => true]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_statuses_index_returns_ok(): void
    {
        $this->getJson(route('statuses.index'))
            ->assertOk()
            ->assertJsonStructure(['statuses']);
    }

    public function test_statuses_store_returns_ok(): void
    {
        $this->postJson(route('statuses.store'), [
            'name' => 'Статус smoke',
            'icon' => 'fas fa-circle',
            'color' => '#abcdef',
            'sort_order' => 88,
        ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['status' => ['id', 'name']]);
    }

    public function test_statuses_update_returns_ok(): void
    {
        $create = $this->postJson(route('statuses.store'), [
            'name' => 'Статус для update',
            'icon' => 'fas fa-circle',
            'color' => '#abcdef',
            'sort_order' => 89,
        ])->assertOk();

        $statusId = (int) $create->json('status.id');

        $this->patchJson(route('statuses.update', $statusId), [
            'name' => 'Статус для update (изм.)',
            'icon' => 'fas fa-circle',
            'color' => '#112233',
            'sort_order' => 90,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_statuses_destroy_returns_ok(): void
    {
        $create = $this->postJson(route('statuses.store'), [
            'name' => 'Статус для delete',
            'icon' => 'fas fa-circle',
            'color' => '#abcdef',
            'sort_order' => 91,
        ])->assertOk();

        $statusId = (int) $create->json('status.id');

        $this->deleteJson(route('statuses.destroy', $statusId))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_all_schedule_journal_endpoints_return_ok_with_schedule_view(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        try {
            [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
            $date = '2026-05-15';
            $weekday = (int) now()->isoWeekday();
            $today = now()->toDateString();

            $this->get(route('schedule.index'))->assertOk();

            $this->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $date,
            ]))->assertOk();

            $this->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'date' => $date,
                'status_id' => $this->visitedStatusId,
                'trainer_profile_id' => $trainer->id,
            ])->assertOk();

            $this->getJson(route('logs.data.schedule', ['draw' => 1]))->assertOk();

            $this->getJson(route('user.schedule.info', $student))->assertOk();

            $this->postJson(route('user.set.group', $student), [
                'team_id' => $team->id,
            ])->assertOk();

            $this->postJson(route('user.sync.teams', $student), [
                'team_ids' => [$team->id],
            ])->assertOk();

            $this->postJson(route('user.update.schedule', $student), [
                'weekdays' => [$weekday],
                'date_from' => $today,
                'date_to' => $today,
            ])->assertOk();

            $this->getJson(route('statuses.index'))->assertOk();

            $create = $this->postJson(route('statuses.store'), [
                'name' => 'Статус all-endpoints',
                'icon' => 'fas fa-circle',
                'color' => '#abcdef',
                'sort_order' => 92,
            ])->assertOk();

            $statusId = (int) $create->json('status.id');

            $this->patchJson(route('statuses.update', $statusId), [
                'name' => 'Статус all-endpoints (изм.)',
                'icon' => 'fas fa-circle',
                'color' => '#112233',
                'sort_order' => 93,
            ])->assertOk();

            $this->deleteJson(route('statuses.destroy', $statusId))->assertOk();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_guest_schedule_journal_endpoints_return_unauthorized(): void
    {
        Auth::logout();

        $this->getJson(route('schedule.cell-context', ['user_id' => 1, 'date' => '2026-05-01']))
            ->assertUnauthorized();
        $this->postJson(route('schedule.update'), [])->assertUnauthorized();
        $this->getJson(route('logs.data.schedule', ['draw' => 1]))->assertUnauthorized();
        $this->getJson(route('statuses.index'))->assertUnauthorized();
    }
}
