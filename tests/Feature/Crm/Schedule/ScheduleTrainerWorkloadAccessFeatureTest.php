<?php

namespace Tests\Feature\Crm\Schedule;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к отчёту «Нагрузка тренеров»: гость, permission schedule.view, smoke 200.
 */
final class ScheduleTrainerWorkloadAccessFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    public function test_guest_cannot_access_trainer_workload(): void
    {
        Auth::logout();

        $this->get(route('schedule.trainer-workload'))
            ->assertStatus(302);

        $this->getJson(route('schedule.trainer-workload.data'))
            ->assertStatus(401);
    }

    public function test_trainer_workload_forbidden_without_schedule_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = $this->workloadSession();

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.trainer-workload'))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.trainer-workload.data'))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.trainer-workload.data', [
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
                'show_groups' => '1',
            ]))
            ->assertForbidden();
    }

    public function test_trainer_workload_page_and_data_endpoints_return_ok_with_schedule_view(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        try {
            $this->grantScheduleView();
            $this->makeTrainerProfile('Тренер smoke');
            [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
            $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-06');

            $dateFrom = Carbon::today()->subDays(29)->toDateString();
            $dateTo = Carbon::today()->toDateString();

            $this->get(route('schedule.trainer-workload'))
                ->assertOk()
                ->assertSee('Нагрузка тренеров', false)
                ->assertSee('trainer-workload-app', false)
                ->assertSee('data-data-url="' . route('schedule.trainer-workload.data'), false)
                ->assertSee('id="trainer-workload-show-groups"', false)
                ->assertSee('id="trainer-workload-month-presets"', false)
                ->assertSee('trainer-workload-month-link', false)
                ->assertSee('>Итого</', false)
                ->assertSee('Тренер smoke', false);

            $this->getJson(route('schedule.trainer-workload.data'))
                ->assertOk()
                ->assertJsonPath('show_groups', false)
                ->assertJsonPath('date_from', $dateFrom)
                ->assertJsonPath('date_to', $dateTo)
                ->assertJsonStructure([
                    'date_from',
                    'date_to',
                    'show_groups',
                    'report',
                    'table_html',
                ]);

            $this->getJson(route('schedule.trainer-workload.data', [
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
                'show_groups' => '1',
            ]))
                ->assertOk()
                ->assertJsonPath('show_groups', true)
                ->assertJsonPath('date_from', '2026-05-01')
                ->assertJsonPath('date_to', '2026-05-31');

            $html = (string) $this->getJson(route('schedule.trainer-workload.data', [
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
                'show_groups' => '0',
            ]))
                ->assertOk()
                ->assertJsonPath('show_groups', false)
                ->json('table_html');

            $this->assertStringContainsString('trainer-workload-sum-only', $html);
            $this->assertStringContainsString('trainer-workload-row--footer', $html);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_all_trainer_workload_endpoints_return_ok_with_schedule_view(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        try {
            $this->grantScheduleView();
            $this->makeTrainerProfile('Тренер smoke');
            [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
            $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-06');

            $defaultFrom = Carbon::today()->subDays(29)->toDateString();
            $defaultTo = Carbon::today()->toDateString();

            $this->get(route('schedule.trainer-workload'))
                ->assertOk()
                ->assertSee('trainer-workload-date-from', false)
                ->assertSee('trainer-workload-date-to', false)
                ->assertSee('id="trainer-workload-month-presets"', false);

            $this->getJson(route('schedule.trainer-workload.data'))
                ->assertOk()
                ->assertJsonPath('date_from', $defaultFrom)
                ->assertJsonPath('date_to', $defaultTo)
                ->assertJsonStructure(['date_from', 'date_to', 'show_groups', 'report', 'table_html']);

            foreach ($this->trainerWorkloadMonthPresets() as $preset) {
                $query = [
                    'date_from' => $preset['date_from'],
                    'date_to' => $preset['date_to'],
                ];

                $this->get(route('schedule.trainer-workload', $query + ['show_groups' => '0']))
                    ->assertOk()
                    ->assertSee('value="' . $preset['date_from'] . '"', false)
                    ->assertSee('value="' . $preset['date_to'] . '"', false)
                    ->assertSee('data-date-from="' . $preset['date_from'] . '"', false)
                    ->assertSee('data-date-to="' . $preset['date_to'] . '"', false);

                $pageWithGroups = $this->get(route('schedule.trainer-workload', $query + ['show_groups' => '1']));
                $pageWithGroups->assertOk();
                $this->assertMatchesRegularExpression(
                    '/id="trainer-workload-show-groups"[\s\S]*?checked/',
                    (string) $pageWithGroups->getContent(),
                );

                $this->getJson(route('schedule.trainer-workload.data', $query + ['show_groups' => '0']))
                    ->assertOk()
                    ->assertJsonPath('date_from', $preset['date_from'])
                    ->assertJsonPath('date_to', $preset['date_to'])
                    ->assertJsonPath('show_groups', false);

                $htmlWithGroups = (string) $this->getJson(route('schedule.trainer-workload.data', $query + ['show_groups' => '1']))
                    ->assertOk()
                    ->assertJsonPath('show_groups', true)
                    ->json('table_html');

                $this->assertStringContainsString('trainer-workload-table', $htmlWithGroups);
                $this->assertStringContainsString('trainer-workload-row--footer', $htmlWithGroups);
            }

            $this->get(route('schedule.trainer-workload', [
                'date_from' => '2026-01-10',
                'date_to' => '2026-02-20',
                'show_groups' => '0',
            ]))->assertOk();

            $this->getJson(route('schedule.trainer-workload.data', [
                'date_from' => '2026-01-10',
                'date_to' => '2026-02-20',
                'show_groups' => '1',
            ]))
                ->assertOk()
                ->assertJsonPath('date_from', '2026-01-10')
                ->assertJsonPath('date_to', '2026-02-20');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_schedule_index_tab_links_to_trainer_workload_with_schedule_view(): void
    {
        $this->grantScheduleView();

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee(route('schedule.trainer-workload'), false)
            ->assertSee('Нагрузка тренеров', false);
    }
}
