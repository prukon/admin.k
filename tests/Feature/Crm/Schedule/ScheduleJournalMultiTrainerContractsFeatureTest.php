<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserTeamScheduleSlot;
use App\Services\Schedule\TrainerWorkloadReportService;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * P1: мультивыбор тренеров при «Посетил» (ячейка + гибкий абонемент).
 * HTTP/API, non-AJAX safety-net, Blade-разметка, JS-контракт (см. BladeInlineJsSyntaxTest),
 * нагрузка/ЗП по pivot, дефолты team_default, негативные кейсы.
 *
 * @see ScheduleCellTrainerFeatureTest
 * @see ScheduleJournalFlexibleContractsFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalMultiTrainerContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    // -------------------------------------------------------------------------
    // Blade / страница журнала
    // -------------------------------------------------------------------------

    public function test_journal_page_renders_multiselect_trainer_fields_for_cell_and_flexible_modals(): void
    {
        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '05']));
        $page->assertOk();

        $html = (string) $page->getContent();
        $this->assertStringContainsString('id="cell-trainer-profile-ids"', $html);
        $this->assertStringContainsString('id="flexible-trainer-profile-ids"', $html);
        $this->assertStringContainsString('name="trainer_profile_ids[]"', $html);
        $this->assertStringContainsString('js-generic-multiselect-select', $html);
        $this->assertStringContainsString('generic-multiselect-field', $html);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2', $html);
        $this->assertStringContainsString('js/schedule-journal.js', $html);
        $this->assertStringContainsString('data-placeholder="Без тренера"', $html);
        // Empty-cell остаётся одиночным (BC через trainer_profile_id).
        $this->assertStringContainsString('id="empty-cell-trainer-profile-id"', $html);
        $this->assertStringContainsString('name="trainer_profile_id"', $html);
        // Старые одиночные id модалок ячейки/гибкого больше не рендерятся.
        $this->assertDoesNotMatchRegularExpression('/id="cell-trainer-profile-id"(?!s)/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="flexible-trainer-profile-id"(?!s)/', $html);
    }

    // -------------------------------------------------------------------------
    // Доступ: гость / без права
    // -------------------------------------------------------------------------

    public function test_guest_cannot_save_multiple_trainers_on_update_or_flexible_place(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $utss = $this->createTrialUtss($student, $team, '2026-05-20');
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $attendedId = (int) $this->visitedStatusId;

        Auth::logout();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-05-20',
            'lesson_occurrence_status_id' => $attendedId,
            'trainer_profile_ids' => [$trainer->id],
        ])->assertStatus(401);

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => $attendedId,
            'trainer_profile_ids' => [$trainer->id],
        ])->assertStatus(401);

        $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-05-20',
            'lesson_occurrence_status_id' => $attendedId,
            'trainer_profile_ids' => [$trainer->id],
        ])->assertRedirect();

        $this->assertSame(0, DB::table('user_lesson_occurrence_status_event_trainers')->count());
    }

    public function test_manager_without_schedule_view_gets_403_when_saving_multiple_trainers(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $utss = $this->createTrialUtss($student, $team, '2026-05-21');
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-05-21',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [$trainer->id],
            ])
            ->assertStatus(403);

        $this->assertSame(0, DB::table('user_lesson_occurrence_status_event_trainers')->count());
    }

    // -------------------------------------------------------------------------
    // AJAX: update ячейки
    // -------------------------------------------------------------------------

    public function test_update_ajax_attended_with_two_trainers_returns_comma_separated_names_and_pivot(): void
    {
        [$student, $team, $trainerA] = $this->makeStudentTeamAndTrainer();
        $trainerB = $this->makeTrainerProfile('Второй');
        $nameA = $this->renameTrainer($trainerA, 'Иванов', 'Алексей');
        $nameB = $this->renameTrainer($trainerB, 'Петров', 'Борис');
        $date = '2026-05-22';
        $utss = $this->createTrialUtss($student, $team, $date);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [$trainerA->id, $trainerB->id],
                'comment' => 'Два тренера',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Статус занятия сохранён.')
            ->assertJsonPath('result.trainer_profile_ids', [(int) $trainerA->id, (int) $trainerB->id])
            ->assertJsonPath('result.trainer_name', $nameA.', '.$nameB)
            ->assertJsonStructure([
                'success',
                'message',
                'result' => ['utss_id', 'trainer_name', 'trainer_profile_ids', 'status'],
            ]);

        $event = $this->latestEventForUtss($utss, $date);
        $this->assertSame((int) $trainerA->id, (int) $event->trainer_profile_id);
        $this->assertEqualsCanonicalizing(
            [(int) $trainerA->id, (int) $trainerB->id],
            $this->pivotTrainerIds($event)
        );
    }

    public function test_update_ajax_attended_without_trainers_is_allowed_and_clears_pivot(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-23';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [$trainer->id],
            ])
            ->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('result.trainer_name', null)
            ->assertJsonPath('result.trainer_profile_ids', []);

        $event = $this->latestEventForUtss($utss, $date);
        $this->assertNull($event->trainer_profile_id);
        $this->assertSame([], $this->pivotTrainerIds($event));
    }

    public function test_update_ajax_rejects_foreign_partner_trainer_with_field_error(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();
        $foreign = $this->makeTrainerProfile('Чужой', $this->foreignPartner->id);
        $date = '2026-05-24';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [$foreign->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trainer_profile_ids.0']);

        $this->assertSame(0, UserLessonOccurrenceStatusEvent::query()
            ->where('user_id', $student->id)
            ->whereDate('occurrence_date', $date)
            ->count());
    }

    public function test_update_ajax_legacy_single_trainer_profile_id_still_writes_pivot(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Legacy один');
        $date = '2026-05-25';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_id' => $trainer->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.trainer_profile_ids', [(int) $trainer->id]);

        $event = $this->latestEventForUtss($utss, $date);
        $this->assertEqualsCanonicalizing([(int) $trainer->id], $this->pivotTrainerIds($event));
    }

    public function test_update_ajax_non_attended_status_clears_trainers_even_if_ids_sent(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-26';
        $utss = $this->createTrialUtss($student, $team, $date);
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $this->assertNotNull($scheduledId);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $scheduledId,
                'trainer_profile_ids' => [$trainer->id],
            ])
            ->assertOk()
            ->assertJsonPath('result.trainer_name', null)
            ->assertJsonPath('result.trainer_profile_ids', []);

        $event = $this->latestEventForUtss($utss, $date);
        $this->assertNull($event->trainer_profile_id);
        $this->assertSame([], $this->pivotTrainerIds($event));
    }

    public function test_cell_context_restores_all_saved_trainer_ids_for_attended_occurrence(): void
    {
        [$student, $team, $trainerA] = $this->makeStudentTeamAndTrainer();
        $trainerB = $this->makeTrainerProfile('Второй');
        $nameA = $this->renameTrainer($trainerA, 'Сидоров', 'Антон');
        $nameB = $this->renameTrainer($trainerB, 'Козлов', 'Борис');
        $date = '2026-05-27';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [$trainerA->id, $trainerB->id],
            ])
            ->assertOk();

        $ctx = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $date,
                'utss_id' => $utss->id,
            ]))
            ->assertOk();

        $ctx->assertJsonPath('trainer_profile_ids_for_select', [(int) $trainerA->id, (int) $trainerB->id])
            ->assertJsonPath('trainer_profile_id_for_select', (string) $trainerA->id)
            ->assertJsonPath('selected.trainer_profile_ids', [(int) $trainerA->id, (int) $trainerB->id])
            ->assertJsonPath('selected.trainer_name', $nameA.', '.$nameB);

        $hover = (string) $ctx->json('selected.package_hover');
        $this->assertStringContainsString('Тренер: '.$nameA.', '.$nameB, $hover);

        // Дефолт группы не подменяет уже сохранённый список из двух тренеров.
        $this->assertCount(2, $ctx->json('trainer_profile_ids_for_select'));
    }

    public function test_cell_context_for_non_attended_does_not_preselect_trainers(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-28';
        $utss = $this->createTrialUtss($student, $team, $date);
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $scheduledId,
            ])
            ->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $date,
                'utss_id' => $utss->id,
            ]))
            ->assertOk()
            ->assertJsonPath('trainer_profile_ids_for_select', [])
            ->assertJsonPath('trainer_profile_id_for_select', null)
            ->assertJsonPath('team_default_trainer_profile_id', $trainer->id);
    }

    public function test_cell_context_keeps_explicit_empty_trainers_when_attended_was_saved_without_trainer(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-06-04';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('result.trainer_profile_ids', []);

        // Контракт для JS trainerIdsForVisited: при current_status_id = Посетил
        // пустой trainer_profile_ids_for_select НЕ заменяется team_default.
        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $date,
                'utss_id' => $utss->id,
            ]))
            ->assertOk()
            ->assertJsonPath('current_status_id', $this->visitedStatusId)
            ->assertJsonPath('trainer_profile_ids_for_select', [])
            ->assertJsonPath('trainer_profile_id_for_select', '')
            ->assertJsonPath('team_default_trainer_profile_id', $trainer->id)
            ->assertJsonPath('selected.trainer_name', null);
    }

    // -------------------------------------------------------------------------
    // Non-AJAX safety-net: update + flexible
    // -------------------------------------------------------------------------

    public function test_update_non_ajax_with_multiple_trainers_redirects_and_persists_pivot_not_empty_200(): void
    {
        [$student, $team, $trainerA] = $this->makeStudentTeamAndTrainer('NonAjax А');
        $trainerB = $this->makeTrainerProfile('NonAjax Б');
        $date = '2026-05-29';
        $utss = $this->createTrialUtss($student, $team, $date);

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.update'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [$trainerA->id, $trainerB->id],
                'comment' => 'non-ajax multi',
            ]);

        $response->assertStatus(302)
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHas('status');
        $this->assertNotSame(200, $response->getStatusCode());

        $event = $this->latestEventForUtss($utss, $date);
        $this->assertEqualsCanonicalizing(
            [(int) $trainerA->id, (int) $trainerB->id],
            $this->pivotTrainerIds($event)
        );
        $this->assertSame('non-ajax multi', (string) $event->comment);
    }

    public function test_update_non_ajax_invalid_trainer_redirects_with_field_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();
        $foreign = $this->makeTrainerProfile('Чужой non-ajax', $this->foreignPartner->id);
        $date = '2026-05-30';
        $utss = $this->createTrialUtss($student, $team, $date);

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.update'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [$foreign->id],
            ]);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['trainer_profile_ids.0']);
        $this->assertSame(0, UserLessonOccurrenceStatusEvent::query()
            ->where('user_id', $student->id)
            ->whereDate('occurrence_date', $date)
            ->count());
    }

    public function test_place_flexible_non_ajax_with_multiple_trainers_redirects_and_persists_pivot(): void
    {
        [$student, $team, $trainerA] = $this->makeStudentTeamAndTrainer('Гибкий NonAjax А');
        $trainerB = $this->makeTrainerProfile('Гибкий NonAjax Б');
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.abonement.place-flexible', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-15',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [$trainerA->id, $trainerB->id],
            ]);

        $response->assertStatus(302)
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHas('status');
        $this->assertNotSame(200, $response->getStatusCode());

        $event = UserLessonOccurrenceStatusEvent::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->whereDate('occurrence_date', '2026-09-15')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertEqualsCanonicalizing(
            [(int) $trainerA->id, (int) $trainerB->id],
            $this->pivotTrainerIds($event)
        );
    }

    public function test_place_flexible_ajax_rejects_foreign_trainer_under_trainer_profile_ids(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $foreign = $this->makeTrainerProfile('Чужой гибкий', $this->foreignPartner->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-16',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [$foreign->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trainer_profile_ids.0']);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    // -------------------------------------------------------------------------
    // Empty-cell BC: одиночный trainer_profile_id → pivot
    // -------------------------------------------------------------------------

    public function test_place_trial_ajax_legacy_single_trainer_still_credits_pivot_for_salary(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Пробный тренер');
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.empty-cell.place-trial', $student), [
                'team_id' => $team->id,
                'occurrence_date' => '2026-05-31',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_id' => $trainer->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $event = UserLessonOccurrenceStatusEvent::query()
            ->where('user_id', $student->id)
            ->whereDate('occurrence_date', '2026-05-31')
            ->whereNull('user_lesson_package_id')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertEqualsCanonicalizing([(int) $trainer->id], $this->pivotTrainerIds($event));
    }

    // -------------------------------------------------------------------------
    // Нагрузка / ЗП: каждому тренеру зачёт
    // -------------------------------------------------------------------------

    public function test_workload_credits_each_trainer_on_same_attended_visit(): void
    {
        [$student, $team, $trainerA] = $this->makeStudentTeamAndTrainer('Нагрузка А');
        $trainerB = $this->makeTrainerProfile('Нагрузка Б');
        $date = '2026-06-01'; // понедельник

        $this->createScheduleStatusEntry(
            (int) $student->id,
            (int) $this->visitedStatusId,
            $date,
            null,
            [(int) $trainerA->id, (int) $trainerB->id]
        );

        $totals = app(TrainerWorkloadReportService::class)
            ->trainerRowTrainingsTotals((int) $this->partner->id, '2026-06-01', '2026-06-30');

        $this->assertSame(1, $totals[(int) $trainerA->id] ?? 0);
        $this->assertSame(1, $totals[(int) $trainerB->id] ?? 0);
    }

    public function test_workload_ignores_legacy_trainer_column_without_pivot_rows(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer('Только legacy');
        $date = '2026-06-02';
        $slot = $this->teamScheduleSlotForUser($student, $date);

        // Имитация «битой» записи: колонка заполнена, pivot пуст (до миграции / ручной insert).
        $event = UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => $date,
            'user_lesson_package_id' => null,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
            'created_by' => $this->user->id,
        ]);
        $this->assertSame(0, $event->trainerProfiles()->count());

        $totals = app(TrainerWorkloadReportService::class)
            ->trainerRowTrainingsTotals((int) $this->partner->id, '2026-06-01', '2026-06-30');

        $this->assertSame(0, $totals[(int) $trainer->id] ?? 0);
    }

    public function test_month_occurrences_package_hover_lists_all_trainers_comma_separated(): void
    {
        [$student, $team, $trainerA] = $this->makeStudentTeamAndTrainer();
        $trainerB = $this->makeTrainerProfile('Второй');
        $nameA = $this->renameTrainer($trainerA, 'Ховеров', 'Анна');
        $nameB = $this->renameTrainer($trainerB, 'Ховерова', 'Белла');
        $date = '2026-06-03';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_ids' => [$trainerA->id, $trainerB->id],
            ])
            ->assertOk();

        $ctx = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $date,
                'utss_id' => $utss->id,
            ]))
            ->assertOk();

        $this->assertSame($nameA.', '.$nameB, $ctx->json('selected.trainer_name'));
        $this->assertStringContainsString(
            'Тренер: '.$nameA.', '.$nameB,
            (string) $ctx->json('selected.package_hover')
        );
    }

    public function test_flexible_context_exposes_all_partner_trainers_not_only_team_default(): void
    {
        [$student, $team, $teamTrainer] = $this->makeStudentTeamAndTrainer('Групповой');
        $other = $this->makeTrainerProfile('Вне группы');
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.flexible-context', $student).'?occurrence_date=2026-09-17&context_team_id='.$team->id)
            ->assertOk();

        $ids = collect($response->json('trainers'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $teamTrainer->id, $ids);
        $this->assertContains((int) $other->id, $ids);
        $response->assertJsonPath('team_default_trainer_profile_id', $teamTrainer->id);
    }

    /**
     * @return list<int>
     */
    private function pivotTrainerIds(UserLessonOccurrenceStatusEvent $event): array
    {
        return $event->trainerProfiles()
            ->pluck('trainer_profiles.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function latestEventForUtss(UserTeamScheduleSlot $utss, string $date): UserLessonOccurrenceStatusEvent
    {
        $event = UserLessonOccurrenceStatusEvent::query()
            ->where('user_id', $utss->user_id)
            ->where('team_schedule_slot_id', $utss->team_schedule_slot_id)
            ->whereDate('occurrence_date', $date)
            ->when(
                $utss->user_lesson_package_id !== null,
                fn ($q) => $q->where('user_lesson_package_id', $utss->user_lesson_package_id),
                fn ($q) => $q->whereNull('user_lesson_package_id')
            )
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($event);

        return $event;
    }

    private function renameTrainer(TrainerProfile $trainer, string $lastname, string $name): string
    {
        $trainer->loadMissing('user');
        $trainer->user->update([
            'lastname' => $lastname,
            'name' => $name,
        ]);

        return (string) $trainer->user->fresh()->full_name;
    }
}
