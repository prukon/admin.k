<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;

/**
 * Средняя посещаемость и строка «Итого» журнала /schedule.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalAttendanceSummaryFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_same_slot_same_date_is_one_training_and_sums_every_visit(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->forceFill(['lastname' => 'ИвановПосещ', 'name' => 'Иван'])->save();
        $other = $this->makeExtraStudent($team, 'ПетровПосещ', 'Пётр');

        $first = $this->createTrialUtss($student, $team, '2026-08-03');
        $second = $this->createTrialUtss($other, $team, '2026-08-03');
        $this->assertSame((int) $first->team_schedule_slot_id, (int) $second->team_schedule_slot_id);

        $missed = $this->createTrialUtss($student, $team, '2026-08-04');
        $july = $this->createTrialUtss($student, $team, '2026-07-20');
        $this->markUtssOccurrenceStatus($first, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($second, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($missed, $this->occurrenceStatusIdByCode('not_attended'));
        $this->markUtssOccurrenceStatus($july, (int) $this->visitedStatusId);

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);

        $this->assertStringContainsString('Средняя посещаемость:', $html);
        $this->assertStringContainsString('>2,0<', $html);
        $this->assertStringContainsString('>Итого<', $html);
        $this->assertSame('2', $this->attendanceDayText($html, '2026-08-03'));
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-04'));
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-01'));
        $this->assertStringNotContainsString('data-date="2026-07-20"', $html);
    }

    public function test_two_slots_on_one_date_are_two_trainings(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $first = $this->createTrialUtss($student, $team, '2026-08-03');
        $secondSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '21:41:00',
            'time_end' => '22:41:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);
        $second = UserTeamScheduleSlot::query()->create([
            'partner_id' => $student->partner_id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $secondSlot->id,
            'starts_at' => '2026-08-03',
            'ends_at' => '2026-08-03',
            'is_trial_lesson' => true,
            'trial_lessons_total' => 1,
            'trial_lessons_remaining' => 1,
            'created_by' => $this->user->id,
        ]);
        $this->markUtssOccurrenceStatus($first, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($second, (int) $this->visitedStatusId);

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);

        $this->assertSame('2', $this->attendanceDayText($html, '2026-08-03'));
        $this->assertStringContainsString('>1,0<', $html);
    }

    public function test_without_attended_average_is_dash_and_days_are_zero(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus(
            $utss,
            $this->occurrenceStatusIdByCode(LessonOccurrenceStatus::CODE_SCHEDULED)
        );

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);

        $this->assertStringContainsString('>—<', $html);
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-03'));
    }

    public function test_team_filter_counts_only_selected_group(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $utssA = $this->createTrialUtss($student, $teamA, '2026-08-03');
        $utssB = $this->createTrialUtss($student, $teamB, '2026-08-04');
        $this->markUtssOccurrenceStatus($utssA, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($utssB, (int) $this->visitedStatusId);

        $htmlA = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $teamA->id]);
        $this->assertSame('1', $this->attendanceDayText($htmlA, '2026-08-03'));
        $this->assertSame('', $this->attendanceDayText($htmlA, '2026-08-04'));
        $this->assertStringContainsString('>1,0<', $htmlA);

        $htmlAll = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => 'all']);
        $this->assertSame('1', $this->attendanceDayText($htmlAll, '2026-08-03'));
        $this->assertSame('1', $this->attendanceDayText($htmlAll, '2026-08-04'));
        $this->assertStringContainsString('>1,0<', $htmlAll);
    }

    public function test_name_search_does_not_change_totals(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->forceFill(['lastname' => 'ИвановПоиск', 'name' => 'Иван'])->save();
        $other = $this->makeExtraStudent($team, 'ПетровПоиск', 'Пётр');

        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($student, $team, '2026-08-03'),
            (int) $this->visitedStatusId
        );
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($other, $team, '2026-08-03'),
            (int) $this->visitedStatusId
        );

        $html = $this->journalHtml([
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
            'q' => 'ИвановПоиск',
        ]);

        $this->assertNotNull($this->journalStudentRowHtml($html, (int) $student->id));
        $this->assertNull($this->journalStudentRowHtml($html, (int) $other->id));
        $this->assertSame('2', $this->attendanceDayText($html, '2026-08-03'));
        $this->assertStringContainsString('>2,0<', $html);
    }

    public function test_two_attended_trials_on_one_day_are_two_trainings_and_show_times_two(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $date = '2026-08-03';

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, $date),
            $this->ajaxHeaders()
        )->assertOk();
        $second = $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, $date),
            $this->ajaxHeaders()
        )->assertOk()
            ->assertJsonPath('result.attendance.trainings_count', 0)
            ->assertJsonPath('result.attendance.average_label', '—');

        $rows = UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->whereDate('starts_at', $date)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $rows);
        $this->assertNotSame((int) $rows[0]->team_schedule_slot_id, (int) $rows[1]->team_schedule_slot_id);
        foreach ($rows as $row) {
            $this->markUtssOccurrenceStatus($row, (int) $this->visitedStatusId);
        }

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);

        $this->assertStringContainsString('>×2<', $html);
        $this->assertSame('2', $this->attendanceDayText($html, $date));
        $this->assertStringContainsString('>1,0<', $html);
    }

    public function test_average_of_three_visits_on_two_trainings_is_one_and_a_half(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $other = $this->makeExtraStudent($team, 'ПетровДробь', 'Пётр');
        $shared = $this->createTrialUtss($student, $team, '2026-08-03');
        $otherOnShared = $this->createTrialUtss($other, $team, '2026-08-03');
        $this->assertSame((int) $shared->team_schedule_slot_id, (int) $otherOnShared->team_schedule_slot_id);

        $secondSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '21:11:00',
            'time_end' => '22:11:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);
        $extra = UserTeamScheduleSlot::query()->create([
            'partner_id' => $student->partner_id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $secondSlot->id,
            'starts_at' => '2026-08-03',
            'ends_at' => '2026-08-03',
            'is_trial_lesson' => true,
            'trial_lessons_total' => 1,
            'trial_lessons_remaining' => 1,
            'created_by' => $this->user->id,
        ]);

        $this->markUtssOccurrenceStatus($shared, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($otherOnShared, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($extra, (int) $this->visitedStatusId);

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);

        $this->assertSame('3', $this->attendanceDayText($html, '2026-08-03'));
        $this->assertStringContainsString('>1,5<', $html);
    }

    public function test_average_of_five_visits_on_three_trainings_rounds_to_one_point_seven(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $second = $this->makeExtraStudent($team, 'ВторойДробь', 'Втор');
        $third = $this->makeExtraStudent($team, 'ТретийДробь', 'Трет');

        $slotAStudent = $this->createTrialUtss($student, $team, '2026-08-03');
        $slotASecond = $this->createTrialUtss($second, $team, '2026-08-03');
        $slotAThird = $this->createTrialUtss($third, $team, '2026-08-03');
        $this->assertSame((int) $slotAStudent->team_schedule_slot_id, (int) $slotASecond->team_schedule_slot_id);

        $slotB = $this->extraSlotUtss($student, $team, '2026-08-10', '20:12:00', '21:12:00');
        $slotC = $this->extraSlotUtss($student, $team, '2026-08-17', '19:13:00', '20:13:00');

        foreach ([$slotAStudent, $slotASecond, $slotAThird, $slotB, $slotC] as $utss) {
            $this->markUtssOccurrenceStatus($utss, (int) $this->visitedStatusId);
        }

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);

        $this->assertSame('3', $this->attendanceDayText($html, '2026-08-03'));
        $this->assertSame('1', $this->attendanceDayText($html, '2026-08-10'));
        $this->assertSame('1', $this->attendanceDayText($html, '2026-08-17'));
        $this->assertStringContainsString('>1,7<', $html);
    }

    public function test_later_not_attended_removes_the_training_from_the_average(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($utss, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($utss, $this->occurrenceStatusIdByCode('not_attended'));

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);

        $this->assertStringContainsString('>—<', $html);
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-03'));
    }

    public function test_disabled_student_visits_are_not_in_the_average(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($student, $team, '2026-08-03'),
            (int) $this->visitedStatusId
        );
        $student->forceFill(['is_enabled' => 0])->save();

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);

        $this->assertNull($this->journalStudentRowHtml($html, (int) $student->id));
        $this->assertStringContainsString('>—<', $html);
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-03'));
    }

    public function test_custom_role_user_is_not_counted_in_the_average(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $custom = $this->makeCustomRoleUser([], [
            'lastname' => 'НеУченик',
            'name' => 'Роль',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($custom, [(int) $team->id]);
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($custom, $team, '2026-08-03'),
            (int) $this->visitedStatusId
        );
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($student, $team, '2026-08-04'),
            (int) $this->visitedStatusId
        );

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);

        $this->assertNull($this->journalStudentRowHtml($html, (int) $custom->id));
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-03'));
        $this->assertSame('1', $this->attendanceDayText($html, '2026-08-04'));
        $this->assertStringContainsString('>1,0<', $html);
    }

    public function test_second_page_keeps_attendance_of_students_from_the_first_page(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->forceFill(['lastname' => 'ПерваяСтраница', 'name' => 'Ученик'])->save();
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($student, $team, '2026-08-03'),
            (int) $this->visitedStatusId
        );

        $html = $this->journalHtml([
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
            'page' => 2,
        ]);

        $this->assertNull($this->journalStudentRowHtml($html, (int) $student->id));
        $this->assertSame('1', $this->attendanceDayText($html, '2026-08-03'));
        $this->assertStringContainsString('>1,0<', $html);
    }

    public function test_student_without_group_is_counted_only_on_none_filter(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($student, $team, '2026-08-03'),
            (int) $this->visitedStatusId
        );
        DB::table('team_user')->where('user_id', $student->id)->delete();

        $none = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => 'none']);
        $this->assertNotNull($this->journalStudentRowHtml($none, (int) $student->id));
        $this->assertSame('1', $this->attendanceDayText($none, '2026-08-03'));
        $this->assertStringContainsString('>1,0<', $none);

        $byTeam = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);
        $this->assertNull($this->journalStudentRowHtml($byTeam, (int) $student->id));
        $this->assertStringContainsString('>—<', $byTeam);
        $this->assertSame('', $this->attendanceDayText($byTeam, '2026-08-03'));
    }

    public function test_member_of_a_group_is_not_forced_into_none_filter(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($student, $team, '2026-08-03'),
            (int) $this->visitedStatusId
        );

        $none = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => 'none']);

        $this->assertNull($this->journalStudentRowHtml($none, (int) $student->id));
        $this->assertStringContainsString('>—<', $none);
        $this->assertSame('', $this->attendanceDayText($none, '2026-08-03'));
    }

    public function test_several_groups_filter_sums_only_those_groups(): void
    {
        [$studentA, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamC = Team::factory()->create(['partner_id' => $this->partner->id]);
        $studentB = $this->makeExtraStudent($teamB, 'ГруппаБ', 'Ученик');
        $studentC = $this->makeExtraStudent($teamC, 'ГруппаВ', 'Ученик');

        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($studentA, $teamA, '2026-08-03'),
            (int) $this->visitedStatusId
        );
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($studentB, $teamB, '2026-08-04'),
            (int) $this->visitedStatusId
        );
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($studentC, $teamC, '2026-08-05'),
            (int) $this->visitedStatusId
        );

        $html = $this->journalHtml([
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id],
        ]);

        $this->assertNotNull($this->journalStudentRowHtml($html, (int) $studentA->id));
        $this->assertNotNull($this->journalStudentRowHtml($html, (int) $studentB->id));
        $this->assertNull($this->journalStudentRowHtml($html, (int) $studentC->id));
        $this->assertSame('1', $this->attendanceDayText($html, '2026-08-03'));
        $this->assertSame('1', $this->attendanceDayText($html, '2026-08-04'));
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-05'));
        $this->assertStringContainsString('>1,0<', $html);
    }

    public function test_other_partner_visits_are_not_counted(): void
    {
        $foreignPartner = Partner::factory()->create();
        $foreignTeam = Team::factory()->create(['partner_id' => $foreignPartner->id]);
        $foreignStudent = User::factory()->create([
            'partner_id' => $foreignPartner->id,
            'role_id' => $this->studentRoleId(),
            'is_enabled' => 1,
            'lastname' => 'ЧужойПартнер',
            'name' => 'Ученик',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($foreignStudent, [(int) $foreignTeam->id]);
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($foreignStudent, $foreignTeam, '2026-08-03'),
            (int) $this->visitedStatusId
        );

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => 'all']);

        $this->assertNull($this->journalStudentRowHtml($html, (int) $foreignStudent->id));
        $this->assertStringContainsString('>—<', $html);
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-03'));
    }

    public function test_first_open_shows_caption_dash_and_zero_day_totals(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();

        $html = $this->journalHtml(['year' => 2026, 'month' => '08', 'team' => $team->id]);

        $this->assertNotNull($this->journalStudentRowHtml($html, (int) $student->id));
        $this->assertSame(
            1,
            preg_match(
                '/<div class="schedule-attendance-average" id="schedule-attendance-average">([\s\S]*?)<\/div>/',
                $html,
                $block
            )
        );
        $this->assertStringContainsString('Средняя посещаемость:', $block[1]);
        $this->assertStringContainsString('id="schedule-attendance-average-value">—<', $block[1]);
        $this->assertDoesNotMatchRegularExpression('/<(button|select|input)\b/', $block[1]);
        $this->assertStringContainsString('class="schedule-attendance-total"', $html);
        $this->assertStringContainsString('>Итого<', $html);
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-01'));
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-31'));
    }

    public function test_update_ajax_returns_recalculated_attendance(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $other = $this->makeExtraStudent($team, 'СидоровПосещ', 'Сидор');
        $kept = $this->createTrialUtss($other, $team, '2026-08-03');
        $changing = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($kept, (int) $this->visitedStatusId);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $changing->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $team->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.attendance.attended_total', 2)
            ->assertJsonPath('result.attendance.trainings_count', 1)
            ->assertJsonPath('result.attendance.average_label', '2,0')
            ->assertJsonPath('result.attendance.by_date.2026-08-03', 2)
            ->assertJsonPath('result.attendance.by_date.2026-08-01', 0);
    }

    private function extraSlotUtss(User $student, Team $team, string $date, string $timeStart, string $timeEnd): UserTeamScheduleSlot
    {
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => (int) \Carbon\CarbonImmutable::parse($date)->isoWeekday(),
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);

        return UserTeamScheduleSlot::query()->create([
            'partner_id' => $student->partner_id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => $date,
            'ends_at' => $date,
            'is_trial_lesson' => true,
            'trial_lessons_total' => 1,
            'trial_lessons_remaining' => 1,
            'created_by' => $this->user->id,
        ]);
    }

    private function makeExtraStudent(Team $team, string $lastname, string $name): User
    {
        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $studentRoleId,
            'team_id' => $team->id,
            'lastname' => $lastname,
            'name' => $name,
            'is_enabled' => 1,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);

        return $student;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function journalHtml(array $query): string
    {
        $html = (string) $this->journalIndex( $query)->assertOk()->getContent();
        $this->assertStringNotContainsString('Whoops', $html);

        return $html;
    }

    private function attendanceDayText(string $html, string $date): string
    {
        if (! preg_match('/<tfoot\b[^>]*>[\s\S]*?<\/tfoot>/', $html, $foot)) {
            return '';
        }

        if (! preg_match(
            '/<td\b[^>]*data-date="'.preg_quote($date, '/').'"[^>]*>([\s\S]*?)<\/td>/',
            $foot[0],
            $cell
        )) {
            return '';
        }

        return trim(html_entity_decode(strip_tags($cell[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
