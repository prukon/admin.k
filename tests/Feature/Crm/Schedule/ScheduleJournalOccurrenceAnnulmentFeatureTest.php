<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\TeamScheduleSlot;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * P1: аннуляция занятия в журнале — DELETE schedule.occurrence.destroy.
 * AJAX-контракт, non-AJAX safety-net, откат consumes_lesson, remaining при ×N,
 * доступ guest/403.
 *
 * @see \Tests\Feature\Crm\Schedule\ScheduleJournalMutationContractsFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalOccurrenceAnnulmentFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_destroy_ajax_success_returns_full_result_deletes_utss_and_events(): void
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
        $slotId = (int) $utss->team_schedule_slot_id;

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'comment' => 'к удалению',
            ])->assertOk();

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slotId,
            'occurrence_date' => $date,
            'user_lesson_package_id' => $ulp->id,
        ]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => $date,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Занятие удалено.')
            ->assertJsonPath('result.deleted', true)
            ->assertJsonPath('result.utss_id', $utss->id)
            ->assertJsonPath('result.user_id', $student->id)
            ->assertJsonPath('result.occurrence_date', $date)
            ->assertJsonPath('result.is_flexible', false)
            ->assertJsonPath('result.is_postpay', false)
            ->assertJsonPath('result.user_lesson_package_id', $ulp->id)
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'utss_id',
                    'user_id',
                    'occurrence_date',
                    'deleted',
                    'occurrence_count',
                    'is_postpay',
                    'is_flexible',
                    'remaining',
                    'user_lesson_package_id',
                    'slots_remaining',
                    'lessons_total',
                    'package_name',
                ],
            ]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utss->id]);
        $this->assertSame(
            0,
            UserLessonOccurrenceStatusEvent::query()
                ->where('user_id', $student->id)
                ->where('team_schedule_slot_id', $slotId)
                ->whereDate('occurrence_date', $date)
                ->where('user_lesson_package_id', $ulp->id)
                ->count()
        );

        $this->assertTrue(
            MyLog::query()
                ->where('event', AuditEvent::ScheduleJournalOccurrenceDeleted->value)
                ->where('target_id', $utss->id)
                ->exists()
        );
    }

    public function test_destroy_ajax_restores_lessons_remaining_after_consuming_status(): void
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
        $afterVisit = (int) $ulp->lessons_remaining;

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => $date,
            ])
            ->assertOk()
            ->assertJsonPath('result.deleted', true);

        $ulp->refresh();
        $this->assertSame($afterVisit + 1, (int) $ulp->lessons_remaining);
    }

    public function test_destroy_ajax_flexible_returns_is_flexible_and_slots_remaining(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-08-01', lessons: 3);
        $date = '2026-08-10';

        $place = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, $date, [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                ])
            );
        $place->assertOk()->assertJsonPath('success', true);
        $utssId = (int) $place->json('result.utss_id');
        $this->assertGreaterThan(0, $utssId);

        $ulp->refresh();
        $slotsAfterPlace = $ulp->calendarSlotsRemaining();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utssId), [
                'occurrence_date' => $date,
            ]);

        $response->assertOk()
            ->assertJsonPath('result.deleted', true)
            ->assertJsonPath('result.is_flexible', true)
            ->assertJsonPath('result.user_lesson_package_id', $ulp->id)
            ->assertJsonPath('result.occurrence_count', 0)
            ->assertJsonPath('result.slots_remaining', $slotsAfterPlace + 1);

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utssId]);
        $ulp->refresh();
        $this->assertSame($slotsAfterPlace + 1, $ulp->calendarSlotsRemaining());
    }

    public function test_destroy_ajax_when_two_occurrences_returns_remaining_payload(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $date = '2026-08-05';

        $first = $this->createTrialUtss($student, $team, $date);
        $secondSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => CarbonImmutable::parse($date)->isoWeekday(),
            'time_start' => '18:00:00',
            'time_end' => '19:00:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);
        $second = UserTeamScheduleSlot::query()->create([
            'partner_id' => $student->partner_id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $secondSlot->id,
            'starts_at' => $date,
            'ends_at' => $date,
            'is_trial_lesson' => true,
            'trial_lessons_total' => 1,
            'trial_lessons_remaining' => 1,
            'created_by' => $this->user->id,
        ]);
        $this->assertNotSame($first->id, $second->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $first->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'comment' => 'первый',
            ])->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $second->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'comment' => 'второй',
            ])->assertOk();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $first), [
                'occurrence_date' => $date,
            ]);

        $response->assertOk()
            ->assertJsonPath('result.deleted', true)
            ->assertJsonPath('result.occurrence_count', 1)
            ->assertJsonPath('result.remaining.utss_id', $second->id)
            ->assertJsonPath('result.remaining.comment', 'второй')
            ->assertJsonStructure([
                'result' => [
                    'remaining' => [
                        'utss_id',
                        'comment',
                        'status' => ['id', 'title', 'icon', 'color'],
                    ],
                ],
            ]);

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $first->id]);
        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $second->id]);
    }

    public function test_destroy_ajax_date_mismatch_returns_422_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-06');

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => '2026-08-07',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_destroy_non_ajax_redirects_and_deletes_occurrence_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-07');

        $response = $this->delete(route('schedule.occurrence.destroy', $utss), [
            '_token' => csrf_token(),
            'occurrence_date' => '2026-08-07',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Занятие удалено.');
        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_destroy_foreign_partner_utss_returns_422(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-08');

        $utss->partner_id = $this->foreignPartner->id;
        $utss->save();

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => '2026-08-08',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_guest_cannot_destroy_occurrence(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-09');

        Auth::logout();

        $this->delete(route('schedule.occurrence.destroy', $utss), [
            'occurrence_date' => '2026-08-09',
        ])->assertRedirect();

        $this->deleteJson(route('schedule.occurrence.destroy', $utss), [
            'occurrence_date' => '2026-08-09',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_without_schedule_view_destroy_returns_403(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-10');
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => '2026-08-10',
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->delete(route('schedule.occurrence.destroy', $utss), [
                '_token' => csrf_token(),
                'occurrence_date' => '2026-08-10',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $utss->id]);
    }
}
