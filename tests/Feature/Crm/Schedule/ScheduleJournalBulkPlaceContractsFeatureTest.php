<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use App\Services\Postpay\PostpayJournalService;
use App\Services\Schedule\JournalMonthlyPaymentStatusService;
use App\Services\TeamTrainerSyncService;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * P1: доступ, поля ошибок, non-AJAX safety-net и разметка массовой постановки.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalBulkPlaceContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_manager_with_schedule_view_gets_json_with_placed_lesson_and_attendance(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 3);
        $statusId = $this->scheduledStatusId();

        $response = $this->postJson(route('schedule.bulk-place'), $this->payload($team->id, [$student->id], $statusId), $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Занятие поставлено.')
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'placed' => [[
                        'user_id',
                        'billing',
                        'utss_id',
                        'occurrence_date',
                        'status' => ['id', 'title'],
                        'consuming_count',
                        'payment_status',
                        'attendance' => ['by_date', 'average_label'],
                    ]],
                    'failed',
                    'attendance' => ['by_date', 'average_label'],
                ],
            ])
            ->assertJsonPath('result.placed.0.billing', 'prepaid')
            ->assertJsonPath('result.placed.0.consuming_count', 0)
            ->assertJsonPath('result.failed', []);

        $this->assertSame(3, (int) $ulp->fresh()->lessons_remaining);
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_manager_without_schedule_view_gets_403_and_nothing_is_saved(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];
        $body = $this->payload($team->id, [$student->id], $this->scheduledStatusId());

        $this->actingAs($actor)->withSession($session)
            ->journalIndex( ['year' => 2026, 'month' => '10'])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.bulk-place'), $body, $this->ajaxHeaders())
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.bulk-place'), $body)
            ->assertForbidden();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_guest_json_is_401_and_plain_post_redirects_without_saving(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);
        Auth::logout();
        $body = $this->payload($team->id, [$student->id], $this->scheduledStatusId());

        $this->postJson(route('schedule.bulk-place'), $body)->assertUnauthorized();
        $this->getJson(route('schedule.bulk-place'))->assertNotFound();
        $this->patchJson(route('schedule.bulk-place'), [])->assertStatus(405);
        $this->deleteJson(route('schedule.bulk-place'))->assertStatus(405);
        $this->post(route('schedule.bulk-place'), $body)->assertRedirect();
        $this->get(route('schedule.bulk-place'))->assertNotFound();
        $this->get(route('schedule.index'))->assertRedirect();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_opening_bulk_place_by_get_patch_or_delete_does_not_save_a_lesson(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);
        $body = $this->payload($team->id, [$student->id], $this->scheduledStatusId());

        $this->getJson(route('schedule.bulk-place'))->assertNotFound();
        $this->patchJson(route('schedule.bulk-place'), $body)->assertStatus(405);
        $this->deleteJson(route('schedule.bulk-place'), $body)->assertStatus(405);
        $this->get(route('schedule.bulk-place'))->assertNotFound();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_missing_status_returns_422_under_the_status_field_and_does_not_save(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);

        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'occurrence_date' => '2026-10-05',
            'user_ids' => [$student->id],
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.lesson_occurrence_status_id.0', 'Выберите статус.');

        $this->from(route('schedule.index'))
            ->post(route('schedule.bulk-place'), [
                'team_id' => $team->id,
                'occurrence_date' => '2026-10-05',
                'user_ids' => [$student->id],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors([
                'lesson_occurrence_status_id' => 'Выберите статус.',
            ]);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_empty_students_bad_date_and_foreign_team_return_field_errors(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);
        $statusId = $this->scheduledStatusId();
        $foreignTeam = Team::factory()->create([
            'partner_id' => Partner::factory()->create()->id,
        ]);

        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'occurrence_date' => '2026-10-05',
            'user_ids' => [],
            'lesson_occurrence_status_id' => $statusId,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.user_ids.0', 'Выберите учеников.');

        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'occurrence_date' => '05.10.2026',
            'user_ids' => [$student->id],
            'lesson_occurrence_status_id' => $statusId,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.occurrence_date.0', 'Некорректный формат даты занятия.');

        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $foreignTeam->id,
            'occurrence_date' => '2026-10-05',
            'user_ids' => [$student->id],
            'lesson_occurrence_status_id' => $statusId,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.team_id.0', 'Группа не найдена.');

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_more_than_one_hundred_students_is_rejected_before_saving(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);

        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'occurrence_date' => '2026-10-05',
            'user_ids' => range(1, 101),
            'lesson_occurrence_status_id' => $this->scheduledStatusId(),
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.user_ids.0', 'За один раз можно поставить занятие не больше чем 100 ученикам.');

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_inactive_status_and_foreign_trainer_return_field_errors(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 2);
        $inactive = LessonOccurrenceStatus::query()->create([
            'partner_id' => $this->partner->id,
            'code' => 'custom_'.bin2hex(random_bytes(4)),
            'title' => 'Выключенный',
            'icon' => 'fa-solid fa-star',
            'color' => '#eeeeee',
            'is_system' => false,
            'is_active' => false,
            'consumes_lesson' => false,
            'sort_order' => 80,
        ]);
        $foreignTrainer = $this->makeTrainerProfile('Чужой тренер', (int) Partner::factory()->create()->id);

        $this->postJson(route('schedule.bulk-place'), $this->payload($team->id, [$student->id], (int) $inactive->id), $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.lesson_occurrence_status_id.0', 'Выбранный статус не найден или неактивен.');

        $this->postJson(route('schedule.bulk-place'), $this->payload($team->id, [$student->id], (int) $this->visitedStatusId, [
            'trainer_profile_ids' => [$foreignTrainer->id],
        ]), $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'trainer_profile_ids.0' => 'Выбранный тренер не найден.',
            ]);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_plain_post_redirects_to_journal_and_saves_the_lesson(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);

        $this->post(route('schedule.bulk-place'), $this->payload($team->id, [$student->id], $this->scheduledStatusId()))
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHas('status', 'Занятие поставлено.');

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $student->id)->whereDate('starts_at', '2026-10-05')->count());
    }

    public function test_plain_post_with_a_skipped_student_still_saves_the_rest_and_redirects_with_error(): void
    {
        [$prepaid, $team] = $this->makeStudentWithTeam();
        $newcomer = $this->makeStudent();
        app(TeamUserSyncService::class)->syncTeamsForStudent($newcomer, [(int) $team->id]);
        $this->makeMonthlyFlexibleAssignment($prepaid, (int) $team->id, '2026-10-01', lessons: 2);

        $this->post(route('schedule.bulk-place'), $this->payload($team->id, [$prepaid->id, $newcomer->id], $this->scheduledStatusId()))
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHas('error', 'Занятие поставлено не всем ученикам.');

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $prepaid->id)->count());
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $newcomer->id)->count());
    }

    public function test_student_outside_the_group_is_skipped_and_the_group_member_is_saved(): void
    {
        [$member, $team] = $this->makeStudentWithTeam();
        $outsider = $this->makeStudent();
        $this->makeMonthlyFlexibleAssignment($member, (int) $team->id, '2026-10-01', lessons: 2);
        $this->makeMonthlyFlexibleAssignment($outsider, (int) $team->id, '2026-10-01', lessons: 2);
        app(TeamUserSyncService::class)->syncTeamsForStudent($outsider, []);
        $missingId = 900000000 + (int) $outsider->id;

        $response = $this->postJson(
            route('schedule.bulk-place'),
            $this->payload($team->id, [$member->id, $outsider->id, $missingId], $this->scheduledStatusId()),
            $this->ajaxHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $failed = collect($response->json('result.failed'))->keyBy('user_id');
        $this->assertSame('Ученик не состоит в выбранной группе.', $failed[(int) $outsider->id]['message']);
        $this->assertSame('Ученик не найден.', $failed[$missingId]['message']);
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $member->id)->count());
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $outsider->id)->count());
    }

    public function test_duplicate_student_is_saved_once(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 3);

        $response = $this->postJson(route('schedule.bulk-place'), $this->payload(
            $team->id,
            [$student->id, $student->id],
            $this->scheduledStatusId()
        ), $this->ajaxHeaders());

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('result.placed'));
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_student_with_both_abonements_is_charged_to_prepaid_while_it_has_lessons_left(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 3);
        $this->attachPostpay((int) $student->id, (int) $team->id, '2026-10-01', paid: false);

        $response = $this->postJson(route('schedule.bulk-place'), $this->payload(
            $team->id,
            [$student->id],
            (int) $this->visitedStatusId
        ), $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonPath('result.placed.0.billing', 'prepaid')
            ->assertJsonPath('result.placed.0.consuming_count', 1);
        $this->assertSame(2, (int) $ulp->fresh()->lessons_remaining);
        $this->assertSame(1, UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('user_lesson_package_id', $ulp->id)
            ->count());
    }

    public function test_exhausted_prepaid_falls_through_to_open_postpay(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 2);
        $ulp->update(['lessons_remaining' => 0]);
        $this->attachPostpay((int) $student->id, (int) $team->id, '2026-10-01', paid: false);

        $response = $this->postJson(route('schedule.bulk-place'), $this->payload($team->id, [$student->id], $this->scheduledStatusId()), $this->ajaxHeaders());

        $response->assertOk()->assertJsonPath('result.placed.0.billing', 'postpay');
        $this->assertSame(0, (int) $ulp->fresh()->lessons_remaining);
        $this->assertSame(1, UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->whereNull('user_lesson_package_id')
            ->count());
    }

    public function test_paid_postpay_without_prepaid_lessons_is_not_saved(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachPostpay((int) $student->id, (int) $team->id, '2026-10-01', paid: true);

        $response = $this->postJson(route('schedule.bulk-place'), $this->payload($team->id, [$student->id], $this->scheduledStatusId()), $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Не удалось поставить занятие.')
            ->assertJsonPath('result.placed', [])
            ->assertJsonPath('result.failed.0.message', PostpayJournalService::LOCKED_MESSAGE);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_visited_status_saves_trainer_and_record_status_does_not(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 4);

        $scheduled = $this->postJson(route('schedule.bulk-place'), $this->payload($team->id, [$student->id], $this->scheduledStatusId(), [
            'trainer_profile_ids' => [$trainer->id],
            'occurrence_date' => '2026-10-05',
        ]), $this->ajaxHeaders());
        $scheduled->assertOk()->assertJsonPath('result.placed.0.billing', 'prepaid');
        $scheduledEvent = UserLessonOccurrenceStatusEvent::query()->where('user_id', $student->id)->latest('id')->first();
        $this->assertNotNull($scheduledEvent);
        $this->assertNull($scheduledEvent->trainer_profile_id);
        $this->assertSame(0, $scheduledEvent->trainerProfiles()->count());
        $this->assertSame(4, (int) $ulp->fresh()->lessons_remaining);

        $visited = $this->postJson(route('schedule.bulk-place'), $this->payload($team->id, [$student->id], (int) $this->visitedStatusId, [
            'trainer_profile_ids' => [$trainer->id],
            'occurrence_date' => '2026-10-06',
        ]), $this->ajaxHeaders());
        $visited->assertOk()->assertJsonPath('result.placed.0.consuming_count', 1);
        $visitedEvent = UserLessonOccurrenceStatusEvent::query()->where('user_id', $student->id)->latest('id')->first();
        $this->assertNotNull($visitedEvent);
        $this->assertSame((int) $trainer->id, (int) $visitedEvent->trainer_profile_id);
        $this->assertTrue($visitedEvent->trainerProfiles()->whereKey($trainer->id)->exists());
        $this->assertSame(3, (int) $ulp->fresh()->lessons_remaining);
    }

    public function test_journal_cells_show_who_can_be_selected_before_any_click(): void
    {
        [$prepaid, $team] = $this->makeStudentWithTeam();
        $emptyPrepaid = $this->studentInTeam($team);
        $postpay = $this->studentInTeam($team);
        $paidPostpay = $this->studentInTeam($team);
        $both = $this->studentInTeam($team);
        $prepaidDespitePaidPostpay = $this->studentInTeam($team);
        $fixed = $this->studentInTeam($team);
        $newcomer = $this->studentInTeam($team);

        $this->makeMonthlyFlexibleAssignment($prepaid, (int) $team->id, '2026-10-01', lessons: 4);
        $empty = $this->makeMonthlyFlexibleAssignment($emptyPrepaid, (int) $team->id, '2026-10-01', lessons: 2);
        $empty->update(['lessons_remaining' => 0]);
        $this->attachPostpay((int) $postpay->id, (int) $team->id, '2026-10-01', paid: false);
        $this->attachPostpay((int) $paidPostpay->id, (int) $team->id, '2026-10-01', paid: true);
        $this->makeMonthlyFlexibleAssignment($both, (int) $team->id, '2026-10-01', lessons: 3);
        $this->attachPostpay((int) $both->id, (int) $team->id, '2026-10-01', paid: false);
        $this->makeMonthlyFlexibleAssignment($prepaidDespitePaidPostpay, (int) $team->id, '2026-10-01', lessons: 3);
        $this->attachPostpay((int) $prepaidDespitePaidPostpay->id, (int) $team->id, '2026-10-01', paid: true);
        $this->makeMonthlyFixedAssignment($fixed, (int) $team->id, '2026-10-01', lessons: 4);

        $html = $this->journalIndex( ['year' => 2026, 'month' => '10', 'team' => $team->id])
            ->assertOk()
            ->getContent();

        $this->assertSame(['eligible', 'prepaid'], $this->cellFlags($html, (int) $prepaid->id, '2026-10-05'));
        $this->assertSame(['prepaid_empty', ''], $this->cellFlags($html, (int) $emptyPrepaid->id, '2026-10-05'));
        $this->assertSame(['eligible', 'postpay'], $this->cellFlags($html, (int) $postpay->id, '2026-10-05'));
        $this->assertSame(['postpay_paid', ''], $this->cellFlags($html, (int) $paidPostpay->id, '2026-10-05'));
        $this->assertSame(['eligible', 'prepaid'], $this->cellFlags($html, (int) $both->id, '2026-10-05'));
        $this->assertSame(['eligible', 'prepaid'], $this->cellFlags($html, (int) $prepaidDespitePaidPostpay->id, '2026-10-05'));
        $this->assertSame(['fixed_only', ''], $this->cellFlags($html, (int) $fixed->id, '2026-10-05'));
        $this->assertSame(['no_abonement', ''], $this->cellFlags($html, (int) $newcomer->id, '2026-10-05'));

        $modalStart = strpos($html, 'id="bulkPlaceModal"');
        $modalEnd = strpos($html, 'id="cellEditModal"');
        $this->assertNotFalse($modalStart);
        $this->assertNotFalse($modalEnd);
        $modal = substr($html, (int) $modalStart, $modalEnd - $modalStart);
        $this->assertStringContainsString('checked', $modal);
        $this->assertStringContainsString('data-is-scheduled="1"', $modal);
        $this->assertStringContainsString('data-is-visited="1"', $modal);
        $this->assertStringNotContainsString('name="comment"', $modal);
        $this->assertStringContainsString('id="bulk-trainer-wrap"', $modal);
        $this->assertStringContainsString('d-none', substr($modal, (int) strpos($modal, 'id="bulk-trainer-wrap"') - 80, 80));
        $this->assertStringContainsString('schedule-bulk-bar d-none', $html);
        $this->assertStringContainsString('schedule-group-day', $html);

        $visitedInput = $this->inputTag($modal, 'bulk-status-'.(int) $this->visitedStatusId);
        $this->assertNotNull($visitedInput);
        $this->assertStringContainsString('checked', $visitedInput);
        $scheduledInput = $this->inputTag($modal, 'bulk-status-'.$this->scheduledStatusId());
        $this->assertNotNull($scheduledInput);
        $this->assertStringNotContainsString('checked', $scheduledInput);
    }

    public function test_after_saving_that_day_the_cell_is_no_longer_selectable_and_another_day_still_is(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 4);

        $this->postJson(route('schedule.bulk-place'), $this->payload($team->id, [$student->id], $this->scheduledStatusId(), [
            'occurrence_date' => '2026-10-05',
        ]), $this->ajaxHeaders())->assertOk();

        $html = $this->journalIndex( ['year' => 2026, 'month' => '10', 'team' => $team->id])
            ->assertOk()
            ->getContent();

        $this->assertSame(['occupied', ''], $this->cellFlags($html, (int) $student->id, '2026-10-05'));
        $this->assertSame(['eligible', 'prepaid'], $this->cellFlags($html, (int) $student->id, '2026-10-06'));
    }

    public function test_same_student_stays_selectable_in_the_other_group_after_one_group_is_filled(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoGroups();
        $this->makeMonthlyFlexibleAssignment($student, (int) $teamA->id, '2026-10-01', lessons: 4);
        $this->attachPostpay((int) $student->id, (int) $teamB->id, '2026-10-01', paid: false);

        $before = $this->journalIndex(['year' => 2026, 'month' => '10'])->assertOk()->getContent();
        $this->assertSame(
            ['eligible', 'prepaid'],
            $this->cellFlagsInTeam($before, (int) $student->id, '2026-10-05', (int) $teamA->id)
        );
        $this->assertSame(
            ['eligible', 'postpay'],
            $this->cellFlagsInTeam($before, (int) $student->id, '2026-10-05', (int) $teamB->id)
        );
        $modal = $this->bulkModalHtml($before);
        $this->assertSame(1, substr_count($modal, 'id="bulk-trainer-profile-ids"'));
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);

        $this->postJson(route('schedule.bulk-place'), [
            'lesson_occurrence_status_id' => $this->scheduledStatusId(),
            'lessons' => [[
                'user_id' => $student->id,
                'occurrence_date' => '2026-10-05',
                'team_id' => $teamA->id,
            ]],
        ], $this->ajaxHeaders())->assertOk();

        $after = $this->journalIndex(['year' => 2026, 'month' => '10'])->assertOk()->getContent();
        $this->assertSame(
            ['occupied', ''],
            $this->cellFlagsInTeam($after, (int) $student->id, '2026-10-05', (int) $teamA->id)
        );
        $this->assertSame(
            ['eligible', 'postpay'],
            $this->cellFlagsInTeam($after, (int) $student->id, '2026-10-05', (int) $teamB->id)
        );
        $this->assertSame(
            ['eligible', 'prepaid'],
            $this->cellFlagsInTeam($after, (int) $student->id, '2026-10-06', (int) $teamA->id)
        );
    }

    public function test_two_groups_keep_their_own_training_count_and_payment_after_one_save(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoGroups();
        $this->makeMonthlyFlexibleAssignment($student, (int) $teamA->id, '2026-10-01', lessons: 4);
        $this->attachPostpay((int) $student->id, (int) $teamB->id, '2026-10-01', paid: false);

        $response = $this->postJson(route('schedule.bulk-place'), [
            'lesson_occurrence_status_id' => (int) $this->visitedStatusId,
            'lessons' => [
                [
                    'user_id' => $student->id,
                    'occurrence_date' => '2026-10-05',
                    'team_id' => $teamA->id,
                ],
                [
                    'user_id' => $student->id,
                    'occurrence_date' => '2026-10-05',
                    'team_id' => $teamB->id,
                ],
            ],
        ], $this->ajaxHeaders());

        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('result.failed', []);
        $placed = collect($response->json('result.placed'))->keyBy('team_id');
        $this->assertSame(1, (int) $placed[(int) $teamA->id]['consuming_count']);
        $this->assertSame(1, (int) $placed[(int) $teamB->id]['consuming_count']);
        $this->assertSame(
            JournalMonthlyPaymentStatusService::STATE_NONE,
            $placed[(int) $teamA->id]['payment_status']['state']
        );
        $this->assertSame('due', $placed[(int) $teamB->id]['payment_status']['state']);
        $this->assertSame(50000, (int) $placed[(int) $teamB->id]['payment_status']['amount_cents']);
        $this->assertNotSame(
            $placed[(int) $teamA->id]['payment_status']['state'],
            $placed[(int) $teamB->id]['payment_status']['state']
        );
    }

    public function test_group_on_the_lesson_is_not_replaced_by_the_single_team_of_the_request(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoGroups();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $teamA->id, '2026-10-01', lessons: 4);
        $this->attachPostpay((int) $student->id, (int) $teamB->id, '2026-10-01', paid: false);

        $response = $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $teamA->id,
            'lesson_occurrence_status_id' => $this->scheduledStatusId(),
            'lessons' => [[
                'user_id' => $student->id,
                'occurrence_date' => '2026-10-05',
                'team_id' => $teamB->id,
            ]],
        ], $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.placed.0.team_id', (int) $teamB->id)
            ->assertJsonPath('result.placed.0.billing', 'postpay');
        $this->assertSame(4, (int) $ulp->fresh()->lessons_remaining);
        $this->assertSame(0, $this->slotsOnTeam((int) $student->id, (int) $teamA->id, '2026-10-05'));
        $this->assertSame(1, $this->slotsOnTeam((int) $student->id, (int) $teamB->id, '2026-10-05'));
    }

    public function test_same_student_same_day_is_saved_once_per_group(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoGroups();
        $this->makeMonthlyFlexibleAssignment($student, (int) $teamA->id, '2026-10-01', lessons: 4);
        $this->attachPostpay((int) $student->id, (int) $teamB->id, '2026-10-01', paid: false);

        $response = $this->postJson(route('schedule.bulk-place'), [
            'lesson_occurrence_status_id' => $this->scheduledStatusId(),
            'lessons' => [
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-05', 'team_id' => $teamA->id],
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-05', 'team_id' => $teamA->id],
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-05', 'team_id' => $teamB->id],
            ],
        ], $this->ajaxHeaders());

        $response->assertOk()->assertJsonPath('result.failed', []);
        $this->assertCount(2, $response->json('result.placed'));
        $this->assertSame(1, $this->slotsOnTeam((int) $student->id, (int) $teamA->id, '2026-10-05'));
        $this->assertSame(1, $this->slotsOnTeam((int) $student->id, (int) $teamB->id, '2026-10-05'));
    }

    public function test_lesson_without_a_group_returns_422_under_that_lesson_and_saves_nothing(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoGroups();
        $this->makeMonthlyFlexibleAssignment($student, (int) $teamA->id, '2026-10-01', lessons: 2);
        $this->attachPostpay((int) $student->id, (int) $teamB->id, '2026-10-01', paid: false);
        $body = [
            'lesson_occurrence_status_id' => $this->scheduledStatusId(),
            'lessons' => [[
                'user_id' => $student->id,
                'occurrence_date' => '2026-10-05',
            ]],
        ];

        $missingGroup = $this->postJson(route('schedule.bulk-place'), $body, $this->ajaxHeaders())
            ->assertStatus(422);
        $this->assertSame('Выберите группу.', $missingGroup->json('errors')['lessons.0.team_id'][0] ?? null);

        $this->from(route('schedule.index'))
            ->post(route('schedule.bulk-place'), $body)
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHasErrors(['lessons.0.team_id' => 'Выберите группу.']);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_foreign_group_on_one_lesson_rejects_the_whole_batch(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 2);
        $foreignTeam = Team::factory()->create([
            'partner_id' => Partner::factory()->create()->id,
        ]);

        $foreign = $this->postJson(route('schedule.bulk-place'), [
            'lesson_occurrence_status_id' => $this->scheduledStatusId(),
            'lessons' => [
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-05', 'team_id' => $team->id],
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-06', 'team_id' => $foreignTeam->id],
            ],
        ], $this->ajaxHeaders())->assertStatus(422);
        $this->assertSame('Группа не найдена.', $foreign->json('errors')['lessons.1.team_id'][0] ?? null);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_trainer_of_one_group_cannot_place_a_lesson_into_another_group(): void
    {
        [$student, $ownTeam, $otherTeam] = $this->studentInTwoGroups();
        $this->makeMonthlyFlexibleAssignment($student, (int) $ownTeam->id, '2026-10-01', lessons: 2);
        $this->attachPostpay((int) $student->id, (int) $otherTeam->id, '2026-10-01', paid: false);
        $this->actAsTrainerOf($ownTeam);

        $otherGroup = $this->postJson(route('schedule.bulk-place'), [
            'lesson_occurrence_status_id' => $this->scheduledStatusId(),
            'lessons' => [
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-05', 'team_id' => $ownTeam->id],
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-06', 'team_id' => $otherTeam->id],
            ],
        ], $this->ajaxHeaders())->assertStatus(422);
        $this->assertSame('Выберите группу из списка.', $otherGroup->json('errors')['lessons.1.team_id'][0] ?? null);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_plain_post_of_two_groups_redirects_to_the_journal_and_saves_both(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoGroups();
        $this->makeMonthlyFlexibleAssignment($student, (int) $teamA->id, '2026-10-01', lessons: 2);
        $this->attachPostpay((int) $student->id, (int) $teamB->id, '2026-10-01', paid: false);

        $this->post(route('schedule.bulk-place'), [
            'lesson_occurrence_status_id' => $this->scheduledStatusId(),
            'lessons' => [
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-03', 'team_id' => $teamA->id],
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-04', 'team_id' => $teamB->id],
            ],
        ])->assertRedirect(route('schedule.index'))
            ->assertSessionHas('status', 'Занятие поставлено выбранным ученикам.');

        $this->assertSame(1, $this->slotsOnTeam((int) $student->id, (int) $teamA->id, '2026-10-03'));
        $this->assertSame(1, $this->slotsOnTeam((int) $student->id, (int) $teamB->id, '2026-10-04'));
    }

    public function test_the_other_group_is_saved_when_one_group_runs_out_of_lessons(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoGroups();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $teamA->id, '2026-10-01', lessons: 1);
        $this->attachPostpay((int) $student->id, (int) $teamB->id, '2026-10-01', paid: false);

        $response = $this->postJson(route('schedule.bulk-place'), [
            'lesson_occurrence_status_id' => (int) $this->visitedStatusId,
            'lessons' => [
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-04', 'team_id' => $teamA->id],
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-03', 'team_id' => $teamA->id],
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-04', 'team_id' => $teamB->id],
            ],
        ], $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Занятие поставлено не всем ученикам.');
        $failed = collect($response->json('result.failed'));
        $this->assertCount(1, $failed);
        $this->assertSame((int) $teamA->id, (int) $failed[0]['team_id']);
        $this->assertSame('2026-10-04', $failed[0]['occurrence_date']);
        $this->assertSame('В абонементе не осталось занятий.', $failed[0]['message']);
        $this->assertSame(0, (int) $ulp->fresh()->lessons_remaining);
        $this->assertSame(1, $this->slotsOnTeam((int) $student->id, (int) $teamA->id, '2026-10-03'));
        $this->assertSame(0, $this->slotsOnTeam((int) $student->id, (int) $teamA->id, '2026-10-04'));
        $this->assertSame(1, $this->slotsOnTeam((int) $student->id, (int) $teamB->id, '2026-10-04'));
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(int $teamId, array $userIds, int $statusId, array $extra = []): array
    {
        return array_merge([
            'team_id' => $teamId,
            'occurrence_date' => '2026-10-05',
            'user_ids' => $userIds,
            'lesson_occurrence_status_id' => $statusId,
        ], $extra);
    }

    private function scheduledStatusId(): int
    {
        $id = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $this->assertNotNull($id);

        return (int) $id;
    }

    private function studentInTeam(Team $team): User
    {
        $student = $this->makeStudent();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);

        return $student;
    }

    private function attachPostpay(int $userId, int $teamId, string $month, bool $paid): void
    {
        $package = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->postpay()
            ->create([
                'name' => 'Постоплата журнал '.uniqid(),
                'price_cents' => 50000,
            ]);

        UserPrice::query()->create([
            'user_id' => $userId,
            'team_id' => $teamId,
            'new_month' => $month,
            'lesson_package_id' => $package->id,
            'price_cents' => 50000,
            'discount_percent' => 0,
            'is_paid' => $paid ? 1 : 0,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function cellFlags(string $html, int $userId, string $date): array
    {
        $pattern = '/<td\b(?=[^>]*\bdata-user-id="'.$userId.'")(?=[^>]*\bdata-date="'.preg_quote($date, '/').'")[^>]*>/';
        $this->assertSame(1, preg_match($pattern, $html, $tag), 'Нет ячейки ученика '.$userId.' на '.$date);

        preg_match('/data-bulk-block="([^"]*)"/', $tag[0], $block);
        preg_match('/data-bulk-billing="([^"]*)"/', $tag[0], $billing);

        return [$block[1] ?? '', $billing[1] ?? ''];
    }

    private function inputTag(string $html, string $id): ?string
    {
        if (! preg_match('/<input\b(?=[^>]*\bid="'.preg_quote($id, '/').'")[^>]*>/', $html, $tag)) {
            return null;
        }

        return $tag[0];
    }

    /**
     * @return array{0: User, 1: Team, 2: Team}
     */
    private function studentInTwoGroups(): array
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Вторая группа '.uniqid(),
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        return [$student, $teamA, $teamB];
    }

    private function slotsOnTeam(int $userId, int $teamId, string $date): int
    {
        return UserTeamScheduleSlot::query()
            ->where('user_id', $userId)
            ->whereDate('starts_at', $date)
            ->whereHas('slot', static fn ($query) => $query->where('team_id', $teamId))
            ->count();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function cellFlagsInTeam(string $html, int $userId, string $date, int $teamId): array
    {
        $pattern = '/<td\b(?=[^>]*\bdata-user-id="'.$userId.'")(?=[^>]*\bdata-date="'.preg_quote($date, '/').'")(?=[^>]*\bdata-context-team-id="'.$teamId.'")[^>]*>/';
        $this->assertSame(1, preg_match($pattern, $html, $tag), 'Нет ячейки ученика '.$userId.' группы '.$teamId.' на '.$date);

        preg_match('/data-bulk-block="([^"]*)"/', $tag[0], $block);
        preg_match('/data-bulk-billing="([^"]*)"/', $tag[0], $billing);

        return [$block[1] ?? '', $billing[1] ?? ''];
    }

    private function bulkModalHtml(string $html): string
    {
        $start = strpos($html, 'id="bulkPlaceModal"');
        $end = strpos($html, 'id="cellEditModal"');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, (int) $start, $end - $start);
    }

    private function actAsTrainerOf(Team $team): void
    {
        $trainer = $this->createUserWithRole('trainer', $this->partner, [
            'name' => 'Тренер',
            'lastname' => 'СвоихГрупп',
            'is_enabled' => 1,
        ]);
        $profile = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $trainer->id,
        ]);
        foreach (['schedule.view', 'groups.own'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $trainer->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        app(TeamTrainerSyncService::class)->attachTrainerToTeam($team, (int) $profile->id);
        $this->actingAs($trainer)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }
}
