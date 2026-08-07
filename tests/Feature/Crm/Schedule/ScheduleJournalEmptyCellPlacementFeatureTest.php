<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Доменные сценарии пробного / разового на пустой ячейке журнала /schedule.
 * Контракты/доступ/non-AJAX — {@see ScheduleJournalEmptyCellContractsFeatureTest}.
 */
final class ScheduleJournalEmptyCellPlacementFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
        $this->grantLessonPackagesView();
    }

    public function test_journal_index_shows_empty_cell_lesson_affordance_when_both_permissions(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeSingleLessonTemplate('Шаблон для affordance');

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('emptyCellPlaceModal', false)
            ->assertSee('emptyCellPlaceForm', false)
            ->assertSee('id="emptyCellPlaceForm" novalidate', false)
            ->assertSee('data-empty-lesson="1"', false)
            ->assertSee('Пробное или разовое занятие', false)
            ->assertSee('empty_cell_lesson_occurrence_status_id', false)
            ->assertSee($student->full_name, false);
    }

    public function test_journal_index_hides_empty_cell_lesson_affordance_without_lesson_packages_view(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeSingleLessonTemplate();

        // Снимаем lessonPackages.view у текущего admin-актора.
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('lessonPackages.view'))
            ->delete();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->assertDontSee('data-empty-lesson="1"', false);
    }

    public function test_empty_cell_context_returns_trial_and_single_options_with_prices(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate('Разовое с ценой', 250050);
        $freeUlp = $this->makeFreeSingleLessonAssignment($student, null, 180000);
        $feeLabelTemplate = Money::formatRub(250050, ' руб');
        $feeLabelBind = Money::formatRub(180000, ' руб');

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('team_id', (int) $team->id)
            ->assertJsonPath('team_locked', true)
            ->assertJsonPath('trial.allowed', true)
            ->assertJsonPath('trial.label', 'Пробное (бесплатное)')
            ->assertJsonPath('trial.reason', null);

        $options = $response->json('single_options') ?? [];
        $this->assertNotEmpty($options);

        $bind = collect($options)->firstWhere('mode', 'bind_existing');
        $create = collect($options)->first(
            fn (array $row): bool => ($row['mode'] ?? '') === 'create_new'
                && (int) ($row['lesson_package_id'] ?? 0) === (int) $template->id
        );
        $this->assertNotNull($bind);
        $this->assertSame((int) $freeUlp->id, (int) $bind['user_lesson_package_id']);
        $this->assertStringContainsString($feeLabelBind, (string) $bind['label']);
        $this->assertNotNull($create);
        $this->assertStringContainsString($feeLabelTemplate, (string) $create['label']);
        $this->assertSame(2500.5, (float) $create['fee_amount']);
    }

    public function test_empty_cell_context_locks_team_only_when_filter_team_passed(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeSingleLessonTemplate();

        $locked = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]));
        $locked->assertOk()
            ->assertJsonPath('team_locked', true)
            ->assertJsonPath('team_id', (int) $team->id);

        $unlocked = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-10');
        $unlocked->assertOk()
            ->assertJsonPath('team_locked', false)
            ->assertJsonPath('team_id', (int) $team->id);
    }

    public function test_place_trial_creates_utss_status_and_marks_trial_used(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-10'),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.is_trial_lesson', true)
            ->assertJsonPath('result.status.id', $scheduledId);

        $utss = UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('is_trial_lesson', true)
            ->whereNull('user_lesson_package_id')
            ->whereDate('starts_at', '2026-09-10')
            ->first();
        $this->assertNotNull($utss);
        $this->assertSame(1, (int) $utss->trial_lessons_total);
        $this->assertSame(1, (int) $utss->trial_lessons_remaining);

        $student->refresh();
        $this->assertTrue((bool) $student->has_used_school_schedule_trial);

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => $scheduledId,
        ]);
    }

    public function test_place_trial_with_attended_status_trainer_and_comment(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-11', [
                'lesson_occurrence_status_id' => $attendedId,
                'trainer_profile_id' => $trainer->id,
                'comment' => 'Пробное посетил',
            ]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('result.status.id', $attendedId)
            ->assertJsonPath('result.comment', 'Пробное посетил');

        $event = UserLessonOccurrenceStatusEvent::query()
            ->where('user_id', $student->id)
            ->whereNull('user_lesson_package_id')
            ->whereDate('occurrence_date', '2026-09-11')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame((int) $attendedId, (int) $event->lesson_occurrence_status_id);
        $this->assertSame((int) $trainer->id, (int) $event->trainer_profile_id);
        $this->assertSame('Пробное посетил', (string) $event->comment);

        $utss = UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('is_trial_lesson', true)
            ->whereDate('starts_at', '2026-09-11')
            ->first();
        $this->assertNotNull($utss);
        $this->assertSame(0, (int) $utss->trial_lessons_remaining);
    }

    public function test_place_trial_blocked_when_already_used(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->forceFill(['has_used_school_schedule_trial' => true])->save();

        $ctx = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-10&context_team_id='.$team->id);
        $ctx->assertOk()
            ->assertJsonPath('trial.allowed', false);
        $this->assertNotEmpty((string) $ctx->json('trial.reason'));

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-10'),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors']);

        $this->assertSame(
            0,
            UserTeamScheduleSlot::query()->where('user_id', $student->id)->where('is_trial_lesson', true)->count()
        );
    }

    public function test_place_single_create_new_creates_ulp_and_utss_with_status(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate('Создание разового', 200000);
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $response = $this->postJson(
            route('schedule.empty-cell.place-single', $student),
            $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-12', 1999.5),
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.is_trial_lesson', false)
            ->assertJsonPath('result.status.id', $scheduledId);

        $ulpId = (int) $response->json('result.user_lesson_package_id');
        $this->assertGreaterThan(0, $ulpId);

        $ulp = UserLessonPackage::query()->find($ulpId);
        $this->assertNotNull($ulp);
        $this->assertSame((int) $student->id, (int) $ulp->user_id);
        $this->assertSame((int) $template->id, (int) $ulp->lesson_package_id);
        $this->assertSame(199950, (int) $ulp->fee_amount_cents);
        $this->assertSame(1, $ulp->userTeamScheduleSlots()->count());

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulpId,
            'occurrence_date' => '2026-09-12',
            'lesson_occurrence_status_id' => $scheduledId,
        ]);
    }

    public function test_place_single_bind_existing_free_assignment(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeFreeSingleLessonAssignment($student, null, 120000);

        $this->postJson(
            route('schedule.empty-cell.place-single', $student),
            $this->placeSingleBindPayload($ulp, (int) $team->id, '2026-09-13'),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('result.user_lesson_package_id', (int) $ulp->id);

        $ulp->refresh();
        $this->assertSame(1, $ulp->userTeamScheduleSlots()->count());
        $this->assertNotNull($ulp->starts_at);
        $this->assertNotNull($ulp->ends_at);
    }

    public function test_place_single_bind_rejects_already_laid_out_assignment(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeFreeSingleLessonAssignment($student);

        $this->postJson(
            route('schedule.empty-cell.place-single', $student),
            $this->placeSingleBindPayload($ulp, (int) $team->id, '2026-09-14'),
            $this->ajaxHeaders()
        )->assertOk();

        $this->postJson(
            route('schedule.empty-cell.place-single', $student),
            $this->placeSingleBindPayload($ulp, (int) $team->id, '2026-09-15'),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors']);

        $this->assertSame(1, $ulp->userTeamScheduleSlots()->count());
    }

    public function test_empty_cell_context_trial_disabled_reason_when_trial_row_exists(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->createTrialUtss($student, $team, '2026-08-01');

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-10&context_team_id='.$team->id)
            ->assertOk()
            ->assertJsonPath('trial.allowed', false)
            ->assertJsonPath(
                'trial.reason',
                'Уже есть пробное занятие 01.08.2026.'
            );
    }

    public function test_place_rejects_wrong_team_membership(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $otherTeam = \App\Models\Team::factory()->create(['partner_id' => $this->partner->id]);
        $template = $this->makeSingleLessonTemplate();

        $response = $this->postJson(
            route('schedule.empty-cell.place-single', $student),
            $this->placeSingleCreatePayload($template, (int) $otherTeam->id, '2026-09-16', 1000),
            $this->ajaxHeaders()
        );

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['team_id']]);
        $this->assertStringContainsString('групп', mb_strtolower((string) ($response->json('errors.team_id.0') ?? '')));
        $this->assertSame(0, UserLessonPackage::query()->where('user_id', $student->id)->count());
    }

    public function test_journal_index_at_flexible_limit_shows_empty_lesson_affordance_with_both_permissions(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $this->makeSingleLessonTemplate('Шаблон при лимите гибкого');
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-05',
            'lesson_occurrence_status_id' => $attendedId,
            'trainer_profile_id' => $trainer->id,
        ], $this->ajaxHeaders())->assertOk()->assertJsonPath('result.slots_remaining', 0);

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $page->assertOk()
            ->assertSee('data-flexible="1"', false)
            ->assertSee('data-flexible-remaining="0"', false)
            ->assertSee('data-empty-lesson="1"', false)
            ->assertSee('Пробное, разовое или занятие из гибкого абонемента', false)
            ->assertSee(">0/1\nГибкий<", false);
    }

    public function test_journal_index_at_flexible_limit_without_lesson_packages_keeps_direct_flexible(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-05',
            'lesson_occurrence_status_id' => $attendedId,
            'trainer_profile_id' => $trainer->id,
        ], $this->ajaxHeaders())->assertOk();

        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('lessonPackages.view'))
            ->delete();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('data-flexible="1"', false)
            ->assertSee('data-flexible-remaining="0"', false)
            ->assertDontSee('data-empty-lesson="1"', false)
            ->assertSee('Гибкий абонемент: поставить занятие', false);
    }

    public function test_empty_cell_context_includes_flexible_options_when_monthly_flexible_exists(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeSingleLessonTemplate('Разовое рядом с гибким');
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $packageName = (string) $ulp->lessonPackage?->name;

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('flexible_options.0.user_lesson_package_id', (int) $ulp->id)
            ->assertJsonPath('flexible_options.0.mode', 'flexible')
            ->assertJsonPath('flexible_options.0.team_id', (int) $team->id)
            ->assertJsonPath('flexible_options.0.slots_remaining', 2)
            ->assertJsonPath('flexible_options.0.lessons_total', 2)
            ->assertJsonPath('flexible_options.0.allowed', true)
            ->assertJsonPath('flexible_options.0.reason', null);

        $label = (string) ($response->json('flexible_options.0.label') ?? '');
        $this->assertStringContainsString('Гибкий:', $label);
        $this->assertStringContainsString($packageName, $label);
        $this->assertStringContainsString('2/2', $label);
        $this->assertNotEmpty($response->json('single_options'));
        $this->assertTrue((bool) $response->json('trial.allowed') || is_bool($response->json('trial.allowed')));
    }

    public function test_empty_cell_context_blocks_flexible_option_when_remaining_zero(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $this->makeSingleLessonTemplate('Разовое при blocked flexible');
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-05',
            'lesson_occurrence_status_id' => $attendedId,
            'trainer_profile_id' => $trainer->id,
        ], $this->ajaxHeaders())->assertOk()->assertJsonPath('result.slots_remaining', 0);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]));

        $response->assertOk()
            ->assertJsonPath('flexible_options.0.user_lesson_package_id', (int) $ulp->id)
            ->assertJsonPath('flexible_options.0.slots_remaining', 0)
            ->assertJsonPath('flexible_options.0.allowed', false)
            ->assertJsonPath(
                'flexible_options.0.reason',
                'Достигнут лимит занятий по гибкому абонементу.'
            );
    }
}
