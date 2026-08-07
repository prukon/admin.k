<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * P1: AJAX-контракт + non-AJAX safety-net для мутаций журнала /schedule
 * (place-fixed, update статуса, sync-teams).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalMutationContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_place_fixed_ajax_success_returns_json_message_and_creates_utss(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'preview', 'result' => ['linked_count', 'starts_at', 'ends_at']])
            ->assertJsonPath('preview', false)
            ->assertJsonPath('message', 'Абонемент разложен в расписание.');

        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $ulp->refresh();
        $this->assertNotNull($ulp->starts_at);
    }

    public function test_place_fixed_ajax_validation_returns_422_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                // нет start_date / weekdays
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_fixed_ajax_business_error_returns_422_with_field_errors(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ])->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['user_lesson_package_id']]);
    }

    public function test_place_fixed_non_ajax_redirects_and_creates_utss(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $response = $this->post(route('schedule.abonement.place-fixed', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Абонемент разложен в расписание.');

        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $ulp->refresh();
        $this->assertNotNull($ulp->starts_at);

        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $this->assertSame(
            2,
            UserLessonOccurrenceStatusEvent::query()
                ->where('user_lesson_package_id', $ulp->id)
                ->where('lesson_occurrence_status_id', $scheduledId)
                ->count()
        );
    }

    public function test_place_fixed_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.abonement.place-fixed', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['start_date', 'weekdays']);
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_fixed_non_ajax_business_error_redirects_with_session_errors(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->post(route('schedule.abonement.place-fixed', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
        ])->assertRedirect(route('schedule.index'));

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.abonement.place-fixed', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHasErrors(['user_lesson_package_id']);
    }

    public function test_update_ajax_success_returns_json_and_consumes_lesson(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ])->assertOk();

        $utss = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->orderBy('starts_at')
            ->firstOrFail();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => CarbonImmutable::parse($utss->starts_at)->toDateString(),
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'comment' => 'AJAX посетил',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Статус занятия сохранён.')
            ->assertJsonPath('result.utss_id', $utss->id)
            ->assertJsonPath('result.created', false)
            ->assertJsonPath('result.occurrence_date', CarbonImmutable::parse($utss->starts_at)->toDateString())
            ->assertJsonPath('result.comment', 'AJAX посетил')
            ->assertJsonPath('result.status.id', $this->visitedStatusId)
            ->assertJsonPath('result.trainer_name', null)
            ->assertJsonStructure([
                'result' => [
                    'utss_id',
                    'occurrence_date',
                    'comment',
                    'created',
                    'trainer_name',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ]);

        $ulp->refresh();
        $this->assertSame(1, (int) $ulp->lessons_remaining);
    }

    public function test_destroy_occurrence_ajax_returns_result_and_restores_lessons_remaining(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ])->assertOk();

        $utss = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->orderBy('starts_at')
            ->firstOrFail();
        $date = CarbonImmutable::parse($utss->starts_at)->toDateString();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])->assertOk();

        $ulp->refresh();
        $remainingAfterVisit = (int) $ulp->lessons_remaining;

        $response = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => $date,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Занятие удалено.')
            ->assertJsonPath('result.deleted', true)
            ->assertJsonPath('result.utss_id', $utss->id)
            ->assertJsonPath('result.is_flexible', false)
            ->assertJsonStructure([
                'result' => [
                    'utss_id',
                    'occurrence_date',
                    'deleted',
                    'occurrence_count',
                    'user_lesson_package_id',
                    'slots_remaining',
                ],
            ]);

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utss->id]);
        $ulp->refresh();
        $this->assertSame($remainingAfterVisit + 1, (int) $ulp->lessons_remaining);
    }

    public function test_destroy_occurrence_non_ajax_redirects(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-04');

        $response = $this->delete(route('schedule.occurrence.destroy', $utss), [
            '_token' => csrf_token(),
            'occurrence_date' => '2026-08-04',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Занятие удалено.');
        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_update_ajax_validation_returns_422_with_errors(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                // нет lesson_occurrence_status_id
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_update_non_ajax_redirects_and_persists_status_event(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $date = '2026-08-03';
        $utss = $this->createTrialUtss($student, $team, $date);

        $response = $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
            'comment' => 'non-ajax',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Статус занятия сохранён.');

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
            'comment' => 'non-ajax',
        ]);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.update'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['lesson_occurrence_status_id']);
    }

    public function test_sync_teams_non_ajax_redirects_and_updates_pivot(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $teamB = \App\Models\Team::factory()->create(['partner_id' => $this->partner->id]);

        $response = $this->post(route('user.sync.teams', $student), [
            '_token' => csrf_token(),
            'team_ids' => [$team->id, $teamB->id],
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Группы ученика обновлены.');

        $ids = \Illuminate\Support\Facades\DB::table('team_user')
            ->where('user_id', $student->id)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([(int) $team->id, (int) $teamB->id], $ids);
    }

    public function test_guest_mutations_are_denied_not_empty_200(): void
    {
        Auth::logout();
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeFixedAssignment($student, lessons: 1, durationDays: 7);
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $this->post(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
        ])->assertRedirect();

        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
        ])->assertUnauthorized();

        $this->post(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertRedirect();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertUnauthorized();

        $this->delete(route('schedule.occurrence.destroy', $utss), [
            'occurrence_date' => '2026-08-03',
        ])->assertRedirect();

        $this->deleteJson(route('schedule.occurrence.destroy', $utss), [
            'occurrence_date' => '2026-08-03',
        ])->assertUnauthorized();
    }

    public function test_without_permission_mutations_return_403(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeFixedAssignment($student, lessons: 1, durationDays: 7);
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.abonement.place-fixed', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => '2026-08-03',
            ])
            ->assertForbidden();
    }
}
