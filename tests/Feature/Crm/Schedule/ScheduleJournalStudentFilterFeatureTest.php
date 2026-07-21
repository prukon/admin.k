<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\ScheduleUser;
use App\Models\Team;
use App\Models\User;

/**
 * Журнал /schedule: в списке и API только активные ученики с системной ролью user.
 */
final class ScheduleJournalStudentFilterFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_index_includes_only_active_students_with_system_role_user(): void
    {
        [$student] = $this->makeStudentTeamAndTrainer();

        $trainer = $this->makeTrainerProfile('ТренерЖурналФильтр');
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'name' => 'АдминЖурнал',
            'lastname' => 'Фильтр',
            'is_enabled' => 1,
        ]);
        $customRoleUser = $this->makeCustomRoleUser(
            ['name' => 'vip-student-schedule'],
            ['name' => 'КастомРоль', 'lastname' => 'Журнал'],
        );

        $disabledStudent = $this->makeStudent();
        $disabledStudent->update(['is_enabled' => 0]);

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->assertSee('data-user-id="' . $student->id . '"', false)
            ->assertDontSee($trainer->user->full_name, false)
            ->assertDontSee($admin->full_name, false)
            ->assertDontSee($customRoleUser->full_name, false)
            ->assertDontSee($disabledStudent->full_name, false)
            ->assertDontSee('data-user-id="' . $trainer->user_id . '"', false)
            ->assertDontSee('data-user-id="' . $admin->id . '"', false)
            ->assertDontSee('data-user-id="' . $customRoleUser->id . '"', false);
    }

    public function test_index_excludes_trainer_even_when_team_and_schedule_entry_exist(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('ТренерСЗаписью');

        $this->createVisitedScheduleEntry($trainer->user_id, $trainer->id, '2026-05-10');

        $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '05',
            'team' => $team->id,
        ]))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->assertDontSee($trainer->user->full_name, false);
    }

    public function test_index_team_filter_applies_after_role_filter(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаАФильтр',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаБФильтр',
        ]);

        $studentA = $this->makeStudent($teamA->id);
        $studentA->update(['name' => 'УченикА', 'lastname' => 'Группа']);

        $studentB = $this->makeStudent($teamB->id);
        $studentB->update(['name' => 'УченикБ', 'lastname' => 'Группа']);

        $this->createUserWithRole('admin', $this->partner, [
            'name' => 'АдминГруппы',
            'lastname' => 'ГруппаА',
            'team_id' => $teamA->id,
            'is_enabled' => 1,
        ]);

        $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '05',
            'team' => $teamA->id,
        ]))
            ->assertOk()
            ->assertSee($studentA->full_name, false)
            ->assertDontSee($studentB->full_name, false)
            ->assertDontSee('АдминГруппы ГруппаА', false);
    }

    public function test_index_none_team_filter_shows_only_students_without_team(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $studentWithoutTeam = $this->makeStudent(null);
        $studentWithoutTeam->update(['name' => 'БезГруппы', 'lastname' => 'Ученик']);

        $studentWithTeam = $this->makeStudent($team->id);
        $studentWithTeam->update(['name' => 'СГруппой', 'lastname' => 'Ученик']);

        $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '05',
            'team' => 'none',
        ]))
            ->assertOk()
            ->assertSee($studentWithoutTeam->full_name, false)
            ->assertDontSee($studentWithTeam->full_name, false);
    }

    public function test_index_does_not_render_schedule_cells_for_excluded_users(): void
    {
        $trainer = $this->makeTrainerProfile('ТренерЯчейка');
        $date = '2026-05-12';

        ScheduleUser::query()->create([
            'user_id' => $trainer->user_id,
            'date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ]);

        $html = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '05',
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-user-id="' . $trainer->user_id . '"', $html);
    }

    public function test_custom_role_with_name_user_but_not_system_is_excluded(): void
    {
        $student = $this->makeStudent();
        $student->update(['name' => 'Системный', 'lastname' => 'Ученик']);

        $fakeUserRole = $this->makeCustomRoleUser(
            ['name' => 'user', 'label' => 'Псевдо user'],
            ['name' => 'Псевдо', 'lastname' => 'UserРоль'],
        );

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->assertDontSee($fakeUserRole->full_name, false);
    }

    public function test_cell_context_returns_404_for_trainer_admin_custom_role_and_disabled_student(): void
    {
        $trainer = $this->makeTrainerProfile('ТренерAPI404');
        $admin = $this->createUserWithRole('admin', $this->partner, ['is_enabled' => 1]);
        $customRoleUser = $this->makeCustomRoleUser();
        $disabledStudent = $this->makeStudent();
        $disabledStudent->update(['is_enabled' => 0]);

        $date = '2026-05-15';

        foreach ([$trainer->user_id, $admin->id, $customRoleUser->id, $disabledStudent->id] as $userId) {
            $this->getJson(route('schedule.cell-context', [
                'user_id' => $userId,
                'date' => $date,
            ]))->assertNotFound();
        }
    }

    public function test_update_returns_404_for_non_student_users(): void
    {
        $trainer = $this->makeTrainerProfile('ТренерUpdate404');
        $admin = $this->createUserWithRole('admin', $this->partner, ['is_enabled' => 1]);
        $customRoleUser = $this->makeCustomRoleUser();
        $disabledStudent = $this->makeStudent();
        $disabledStudent->update(['is_enabled' => 0]);

        $payload = [
            'date' => '2026-05-15',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ];

        foreach ([$trainer->user_id, $admin->id, $customRoleUser->id, $disabledStudent->id] as $userId) {
            $this->postJson(route('schedule.update'), array_merge($payload, [
                'user_id' => $userId,
            ]))->assertNotFound();
        }
    }

    public function test_user_schedule_info_returns_404_for_non_student_users(): void
    {
        $trainer = $this->makeTrainerProfile('ТренерInfo404');
        $admin = $this->createUserWithRole('admin', $this->partner, ['is_enabled' => 1]);
        $customRoleUser = $this->makeCustomRoleUser();

        foreach ([$trainer->user, $admin, $customRoleUser] as $user) {
            $this->getJson(route('user.schedule.info', $user))->assertNotFound();
        }
    }

    public function test_set_user_group_returns_404_for_non_student_users(): void
    {
        $trainer = $this->makeTrainerProfile('ТренерGroup404');
        $admin = $this->createUserWithRole('admin', $this->partner, ['is_enabled' => 1]);
        $customRoleUser = $this->makeCustomRoleUser();

        foreach ([$trainer->user, $admin, $customRoleUser] as $user) {
            $this->postJson(route('user.set.group', $user), [
                'team_id' => null,
            ])->assertNotFound();
        }
    }

    public function test_update_user_schedule_range_returns_404_for_non_student_users(): void
    {
        $trainer = $this->makeTrainerProfile('ТренерRange404');
        $admin = $this->createUserWithRole('admin', $this->partner, ['is_enabled' => 1]);
        $customRoleUser = $this->makeCustomRoleUser();
        $disabledStudent = $this->makeStudent();
        $disabledStudent->update(['is_enabled' => 0]);

        $payload = [
            'weekdays' => [1],
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-01',
        ];

        foreach ([$trainer->user, $admin, $customRoleUser, $disabledStudent] as $user) {
            $this->postJson(route('user.update.schedule', $user), $payload)->assertNotFound();
        }
    }

    public function test_valid_student_still_works_through_all_journal_user_endpoints(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-20';

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => $date,
        ]))->assertOk();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->getJson(route('user.schedule.info', $student))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $student->id);

        $this->postJson(route('user.set.group', $student), [
            'team_id' => $team->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->postJson(route('user.update.schedule', $student), [
            'weekdays' => [1],
            'date_from' => $date,
            'date_to' => $date,
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_with_system_role_user_scope_filters_by_is_sistem_flag(): void
    {
        $student = $this->makeStudent();
        $customRoleUser = $this->makeCustomRoleUser(
            ['name' => 'user'],
            ['name' => 'НеСистемный', 'lastname' => 'User'],
        );

        $included = User::query()
            ->where('partner_id', $this->partner->id)
            ->withSystemRoleUser()
            ->pluck('id');

        $this->assertTrue($included->contains($student->id));
        $this->assertFalse($included->contains($customRoleUser->id));
    }
}
