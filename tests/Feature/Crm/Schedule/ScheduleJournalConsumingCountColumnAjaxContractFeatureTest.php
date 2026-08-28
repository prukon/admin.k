<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;

/**
 * P1: AJAX-контракт колонки «кол-во тренировок»:
 * postJson/deleteJson с X-Requested-With → JSON success/message/result.consuming_count.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalConsumingCountColumnAjaxContractFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_update_ajax_success_returns_consuming_count_and_message(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Статус занятия сохранён.')
            ->assertJsonPath('result.consuming_count', 1)
            ->assertJsonPath('result.utss_id', (int) $utss->id)
            ->assertJsonPath('result.status.id', $this->visitedStatusId)
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'utss_id',
                    'occurrence_date',
                    'consuming_count',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ]);
        $this->assertIsInt($response->json('result.consuming_count'));

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ]);
    }

    public function test_update_ajax_to_scheduled_returns_zero_consuming_count(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($utss, (int) $this->visitedStatusId);
        $scheduledId = $this->occurrenceStatusIdByCode(LessonOccurrenceStatus::CODE_SCHEDULED);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $scheduledId,
                'journal_team_filter' => 'all',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.consuming_count', 0);
    }

    public function test_update_ajax_validation_returns_422_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['lesson_occurrence_status_id']);

        $this->assertSame(
            0,
            UserLessonOccurrenceStatusEvent::query()->where('user_id', $student->id)->count()
        );
    }

    public function test_update_ajax_respects_journal_team_filter(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $utssA = $this->createTrialUtss($student, $teamA, '2026-08-03');
        $utssB = $this->createTrialUtss($student, $teamB, '2026-08-04');
        $this->markUtssOccurrenceStatus($utssB, (int) $this->visitedStatusId);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utssA->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $teamA->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.consuming_count', 1);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utssA->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('result.consuming_count', 2);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utssA->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'not-a-team',
            ])
            ->assertOk()
            ->assertJsonPath('result.consuming_count', 2);
    }

    public function test_destroy_ajax_returns_zero_consuming_count_after_deleting_visit(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($utss, (int) $this->visitedStatusId);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => '2026-08-03',
                'journal_team_filter' => 'all',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Занятие удалено.')
            ->assertJsonPath('result.deleted', true)
            ->assertJsonPath('result.consuming_count', 0)
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'utss_id',
                    'deleted',
                    'consuming_count',
                    'occurrence_count',
                ],
            ]);
        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_destroy_ajax_date_mismatch_returns_422_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => '2026-08-04',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_place_flexible_ajax_attended_returns_consuming_count_one(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-08-01', lessons: 3);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-10',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Занятие из гибкого абонемента поставлено в журнал.')
            ->assertJsonPath('result.consuming_count', 1)
            ->assertJsonStructure([
                'success',
                'message',
                'result' => ['utss_id', 'consuming_count', 'status' => ['id', 'title', 'icon', 'color']],
            ]);
    }

    public function test_place_flexible_ajax_scheduled_returns_consuming_count_zero(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-10',
                'lesson_occurrence_status_id' => $scheduledId,
                'journal_team_filter' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('result.consuming_count', 0);
    }

    public function test_place_trial_and_single_ajax_return_consuming_count(): void
    {
        $this->grantLessonPackagesView();
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate();

        $trial = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-trial', $student),
                $this->placeTrialPayload((int) $team->id, '2026-08-10', [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                    'journal_team_filter' => 'all',
                ])
            );
        $trial->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Пробное занятие записано в журнал.')
            ->assertJsonPath('result.consuming_count', 1)
            ->assertJsonStructure(['success', 'message', 'result' => ['utss_id', 'consuming_count']]);

        $single = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-08-11', 1000, [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                    'journal_team_filter' => 'all',
                ])
            );
        $single->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Разовое занятие записано в журнал.')
            ->assertJsonPath('result.consuming_count', 2);
    }

    public function test_place_flexible_ajax_validation_returns_422_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-08-01');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['occurrence_date']);
    }

    public function test_guest_ajax_mutations_are_unauthorized_not_empty_200(): void
    {
        Auth::logout();
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertUnauthorized();

        $this->deleteJson(route('schedule.occurrence.destroy', $utss), [
            'occurrence_date' => '2026-08-03',
        ])->assertUnauthorized();
    }
}
