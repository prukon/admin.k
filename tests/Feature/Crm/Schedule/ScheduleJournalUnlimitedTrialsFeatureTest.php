<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\LessonPackages\UserLessonPackageAutoProlongGuard;
use Illuminate\Support\Facades\Auth;

/**
 * Несколько пробных в журнале /schedule: пункт «Пробное» на другой пустой день
 * остаётся доступным, счётчик ученика считает строки, а не «уже было».
 *
 * @see ScheduleJournalEmptyCellPlacementFeatureTest
 */
final class ScheduleJournalUnlimitedTrialsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
        $this->grantLessonPackagesView();
    }

    public function test_other_empty_day_stays_clickable_and_accepts_another_trial_after_the_first(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $firstDate = '2026-09-10';
        $secondDate = '2026-09-11';

        $before = $this->journalPage($team->id);
        $this->assertJournalCell($before, $firstDate, 0, '1');
        $this->assertJournalCell($before, $secondDate, 0, '1');
        $before->assertSee('id="emptyCellPlaceForm" novalidate', false);
        $before->assertSee('id="empty-cell-choice-options"', false);

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, $firstDate),
            $this->ajaxHeaders()
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.is_trial_lesson', true)
            ->assertJsonPath('result.occurrence_date', $firstDate);

        $student->refresh();
        $this->assertSame(1, (int) $student->school_schedule_trial_lessons_count);

        $afterFirst = $this->journalPage($team->id);
        $this->assertJournalCell($afterFirst, $firstDate, 1, '0');
        $this->assertJournalCell($afterFirst, $secondDate, 0, '1');

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => $secondDate,
                'context_team_id' => $team->id,
            ]))
            ->assertOk()
            ->assertJsonPath('trial.allowed', true)
            ->assertJsonPath('trial.reason', null)
            ->assertJsonPath('trial.label', 'Пробное (бесплатное)');

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, $secondDate),
            $this->ajaxHeaders()
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.occurrence_date', $secondDate);

        $student->refresh();
        $this->assertSame(2, (int) $student->school_schedule_trial_lessons_count);

        $afterSecond = $this->journalPage($team->id);
        $this->assertJournalCell($afterSecond, $firstDate, 1, '0');
        $this->assertJournalCell($afterSecond, $secondDate, 1, '0');
        $this->assertSame(
            2,
            UserTeamScheduleSlot::query()
                ->where('user_id', $student->id)
                ->where('is_trial_lesson', true)
                ->whereNull('user_lesson_package_id')
                ->count()
        );
    }

    public function test_second_trial_on_the_same_day_is_visible_as_two_occurrences(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $date = '2026-09-10';

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, $date),
            $this->ajaxHeaders()
        )->assertOk();

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, $date),
            $this->ajaxHeaders()
        )->assertOk()
            ->assertJsonPath('result.is_trial_lesson', true)
            ->assertJsonPath('result.occurrence_date', $date);

        $student->refresh();
        $this->assertSame(2, (int) $student->school_schedule_trial_lessons_count);

        $page = $this->journalPage($team->id);
        $this->assertJournalCell($page, $date, 2, '0');
        $page->assertSee('>×2<', false);
    }

    public function test_deleting_one_trial_decrements_count_and_leaves_the_other(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $first = $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-10'),
            $this->ajaxHeaders()
        )->assertOk();
        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-11'),
            $this->ajaxHeaders()
        )->assertOk();

        $utssId = (int) $first->json('result.utss_id');
        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utssId), [
                'occurrence_date' => '2026-09-10',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.deleted', true);

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utssId]);
        $student->refresh();
        $this->assertSame(1, (int) $student->school_schedule_trial_lessons_count);
        $this->assertSame(
            1,
            UserTeamScheduleSlot::query()->where('user_id', $student->id)->where('is_trial_lesson', true)->count()
        );

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-12',
                'context_team_id' => $team->id,
            ]))
            ->assertOk()
            ->assertJsonPath('trial.allowed', true)
            ->assertJsonPath('trial.reason', null);
    }

    public function test_visited_trial_does_not_lower_the_student_trial_count(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-10', [
                'lesson_occurrence_status_id' => $attendedId,
            ]),
            $this->ajaxHeaders()
        )->assertOk();

        $student->refresh();
        $this->assertSame(1, (int) $student->school_schedule_trial_lessons_count);
        $row = UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('is_trial_lesson', true)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->trial_lessons_remaining);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-11&context_team_id='.$team->id)
            ->assertOk()
            ->assertJsonPath('trial.allowed', true);
    }

    public function test_second_trial_without_ajax_redirects_and_creates_the_row(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-10'),
            $this->ajaxHeaders()
        )->assertOk();

        $response = $this->from(route('schedule.index'))
            ->post(
                route('schedule.empty-cell.place-trial', $student),
                array_merge($this->placeTrialPayload((int) $team->id, '2026-09-11'), ['_token' => csrf_token()])
            );

        $response->assertStatus(302)
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHas('status', 'Пробное занятие записано в журнал.');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));

        $student->refresh();
        $this->assertSame(2, (int) $student->school_schedule_trial_lessons_count);
    }

    public function test_trial_validation_errors_are_shown_under_fields(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            ['team_id' => $team->id],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonPath('errors.occurrence_date.0', 'Укажите дату занятия.')
            ->assertJsonPath('errors.lesson_occurrence_status_id.0', 'Выберите статус.');

        $this->assertSame(
            0,
            UserTeamScheduleSlot::query()->where('user_id', $student->id)->where('is_trial_lesson', true)->count()
        );
    }

    public function test_auto_prolong_blocks_trial_under_the_date_field(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate('Автопролонг блокирует пробное');
        UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $template->id,
            'team_id' => $team->id,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount_cents' => 10000,
            'is_paid' => false,
            'auto_prolong_enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-10'),
            $this->ajaxHeaders()
        );

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.occurrence_date.0', UserLessonPackageAutoProlongGuard::BLOCK_REASON);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-10&context_team_id='.$team->id)
            ->assertOk()
            ->assertJsonPath('trial.allowed', false)
            ->assertJsonPath('trial.reason', UserLessonPackageAutoProlongGuard::BLOCK_REASON);

        $student->refresh();
        $this->assertSame(0, (int) $student->school_schedule_trial_lessons_count);
    }

    public function test_student_not_in_team_blocks_trial_under_the_team_field(): void
    {
        [$student] = $this->makeStudentWithTeam();
        $otherTeam = \App\Models\Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $otherTeam->id, '2026-09-10'),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['team_id']]);

        $this->assertSame(0, (int) $student->fresh()->school_schedule_trial_lessons_count);
    }

    public function test_guest_cannot_open_empty_cell_or_place_trial(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        Auth::logout();

        $this->get(route('schedule.index'))->assertRedirect();
        $this->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-10')
            ->assertUnauthorized();
        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-10')
        )->assertUnauthorized();
    }

    public function test_manager_without_lesson_packages_view_cannot_place_another_trial(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-10'),
            $this->ajaxHeaders()
        )->assertOk();

        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];
        \Illuminate\Support\Facades\DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-11')
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(
                route('schedule.empty-cell.place-trial', $student),
                $this->placeTrialPayload((int) $team->id, '2026-09-11')
            )
            ->assertForbidden();

        $this->assertSame(1, (int) $student->fresh()->school_schedule_trial_lessons_count);
    }

    private function journalPage(int $teamId): \Illuminate\Testing\TestResponse
    {
        $page = $this->journalIndex( [
            'year' => 2026,
            'month' => '09',
            'team' => $teamId,
        ]);
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));

        return $page;
    }

    private function assertJournalCell(\Illuminate\Testing\TestResponse $page, string $date, int $count, string $emptyLesson): void
    {
        $this->assertMatchesRegularExpression(
            '/data-date="'.preg_quote($date, '/').'"[^>]*data-occurrence-count="'.$count.'"[^>]*data-empty-lesson="'.$emptyLesson.'"/',
            (string) $page->getContent()
        );
    }
}
