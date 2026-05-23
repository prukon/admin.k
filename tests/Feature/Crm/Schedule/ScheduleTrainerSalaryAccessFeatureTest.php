<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

/**
 * Доступ к «ЗП тренеров»: гость, permissions, smoke.
 */
final class ScheduleTrainerSalaryAccessFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    public function test_guest_redirected_from_page_and_data_unauthorized(): void
    {
        auth()->logout();

        $this->get(route('schedule.trainer-salary'))
            ->assertRedirect();

        $this->getJson(route('schedule.trainer-salary.data'))
            ->assertUnauthorized();
    }

    public function test_forbidden_without_trainer_salary_view(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.trainerSalary.view', $this->partner);

        $this->actingAs($actor)
            ->get(route('schedule.trainer-salary'))
            ->assertForbidden();

        $this->actingAs($actor)
            ->getJson(route('schedule.trainer-salary.data'))
            ->assertForbidden();
    }

    public function test_page_ok_with_view_permission(): void
    {
        $this->grantTrainerSalaryView();
        $this->makeTrainerProfile('Тренер ЗП');

        $this->get(route('schedule.trainer-salary'))
            ->assertOk()
            ->assertSee('ЗП тренеров', false)
            ->assertSee('trainer-salary-app', false)
            ->assertSee('Тренер ЗП', false);

        $this->getJson(route('schedule.trainer-salary.data'))
            ->assertOk()
            ->assertJsonStructure(['year', 'month', 'month_label', 'table_html', 'rows']);
    }

    public function test_manage_endpoints_forbidden_without_manage_permission(): void
    {
        $this->grantTrainerSalaryView();
        $trainer = $this->makeTrainerProfile('Тренер A');

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => 100,
        ])->assertForbidden();

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertForbidden();

        $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ])->assertForbidden();
    }

    public function test_all_trainer_salary_endpoints_return_ok_with_view_and_manage(): void
    {
        $this->grantTrainerSalaryView();
        $this->grantTrainerSalaryManage();

        $trainer = $this->makeTrainerProfile('Тренер smoke');
        [$student, , $trainerWithVisits] = $this->makeStudentTeamAndTrainer('Тренер smoke');
        $this->createVisitedScheduleEntry($student->id, $trainerWithVisits->id, '2026-05-10');

        $this->get(route('schedule.trainer-salary'))
            ->assertOk()
            ->assertSee('ЗП тренеров', false)
            ->assertSee('trainer-salary-app', false)
            ->assertSee('data-data-url="' . route('schedule.trainer-salary.data'), false)
            ->assertSee('Тренер smoke', false);

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('month', 5)
            ->assertJsonStructure(['year', 'month', 'month_label', 'date_from', 'date_to', 'can_manage', 'table_html', 'rows']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => 500,
        ])
            ->assertOk()
            ->assertJsonPath('row.bonuses', '500.00');

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('snapshot.version', 1);

        $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ])
            ->assertOk()
            ->assertJsonStructure(['batch_id', 'snapshots_count', 'rows']);
    }

    public function test_view_only_user_gets_data_without_manage_actions_in_table_html(): void
    {
        $this->grantTrainerSalaryView();
        $this->makeTrainerProfile('Тренер только просмотр');

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('can_manage', false)
            ->json('table_html');

        $this->assertStringNotContainsString('trainer-salary-form-one-btn', $html);
        $this->assertStringNotContainsString('>Действие</', $html);
    }
}
