<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\TrainerSalaryKansasDraftGroup;
use App\Models\TrainerSalaryKansasGroupBaseline;
use App\Models\TrainerSalaryKansasSnapshotGroup;
use App\Models\TrainerSalarySnapshot;
use App\Models\User;

/**
 * Канзас: целые средние (факт вверх после десятой, база только int, без ховера).
 * UX-баг до фикса: 15.1 в ячейке как 15, в деньгах как 15.1, ховер с десятой.
 */
final class ScheduleTrainerSalaryKansasIntegerAveragesFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();
    }

    public function test_fact_average_fifteen_point_one_is_shown_and_paid_as_sixteen(): void
    {
        [$trainer, $team] = $this->seedFactAverageVisits(10, 15, 1);

        $this->setTrainerTypeRates($trainer, 1000, 800);
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 100,
        ])->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 15,
        ])->assertOk();

        $data = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $row = collect($data->json('rows'))->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertNotNull($row);
        $group = $row['groups'][0];

        $this->assertSame(10, $group['trainings_count']);
        $this->assertSame('16', $group['fact_avg_students']);
        $this->assertSame('16', $group['fact_avg_students_int']);
        $this->assertSame('15', $group['base_avg_students']);
        $this->assertSame('1', $group['diff_students']);
        $this->assertSame('1', $group['diff_students_int']);
        $this->assertSame('900.00', $group['premium']);
        $this->assertSame('1900.00', $group['pay_per_training']);
        $this->assertSame('19000.00', $group['group_total']);
        $this->assertSame('19000.00', $row['total']);

        $html = (string) $data->json('table_html');
        $this->assertKansasTableAveragesHaveNoHover($html);
        $this->assertStringNotContainsString('15.1', $html);
        $this->assertStringContainsString('>16</span>', $html);
        $this->assertStringContainsString('>15</span>', $html);
        $this->assertStringContainsString('>1</span>', $html);

        $page = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('15.1', $page);
        $this->assertStringContainsString('>16</span>', $page);
        $this->assertMonthSettingsHasIntegerBaseAndXHover($page);

        $draftGroup = TrainerSalaryKansasDraftGroup::query()
            ->where('team_id', $team->id)
            ->first();
        $this->assertNotNull($draftGroup);
        $this->assertSame(160, (int) $draftGroup->fact_avg_tenths);
        $this->assertSame(150, (int) $draftGroup->base_avg_tenths);
        $this->assertSame(10, (int) $draftGroup->diff_tenths);
        $this->assertSame(90000, (int) $draftGroup->premium_cents);
    }

    public function test_exact_whole_fact_average_is_not_rounded_up(): void
    {
        [$trainer] = $this->seedFactAverageVisits(1, 15, 0);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('15', $row['groups'][0]['fact_avg_students']);
        $this->assertSame('15', $row['groups'][0]['fact_avg_students_int']);
        $this->assertSame(150, (int) TrainerSalaryKansasDraftGroup::query()
            ->where('team_id', $row['groups'][0]['team_id'])
            ->value('fact_avg_tenths'));
    }

    public function test_fractional_base_average_is_rejected_with_field_error_and_not_saved(): void
    {
        $trainer = $this->makeTrainerProfile('База дробь');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа дробь']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        foreach (['16.5', '16.55', '16.0', '16,5'] as $invalid) {
            $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
                'year' => 2026,
                'month' => 5,
                'team_id' => $team->id,
                'base_avg_students' => $invalid,
            ]);
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['base_avg_students']);
            $this->assertIsArray($response->json('errors.base_avg_students'));
            $this->assertNotSame('', (string) $response->json('errors.base_avg_students.0'));
        }

        $negative = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => -1,
        ]);
        $negative->assertStatus(422)->assertJsonValidationErrors(['base_avg_students']);

        $tooBig = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 1000,
        ]);
        $tooBig->assertStatus(422)->assertJsonValidationErrors(['base_avg_students']);

        $this->assertSame(
            '0',
            collect($this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows'))
                ->firstWhere('trainer_profile_id', $trainer->id)['groups'][0]['base_avg_students']
        );
        $this->assertSame(0, TrainerSalaryKansasGroupBaseline::query()->where('team_id', $team->id)->count());
    }

    public function test_integer_base_average_saves_and_table_shows_it_without_hover(): void
    {
        $trainer = $this->makeTrainerProfile('База целое');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа целое']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 16,
        ]);
        $response->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён')
            ->assertJsonPath('reload_table', true);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertKansasTableAveragesHaveNoHover((string) $response->json('table_html'));
        $this->assertMonthSettingsHasIntegerBaseAndXHover((string) $response->json('month_settings_html'));
        $this->assertStringContainsString('value="16"', (string) $response->json('month_settings_html'));

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id)['groups'][0];
        $this->assertSame('16', $row['base_avg_students']);
        $this->assertSame('16', $row['base_avg_students_int']);

        $this->assertDatabaseHas('trainer_salary_kansas_group_baselines', [
            'team_id' => $team->id,
            'base_avg_students_tenths' => 160,
        ]);
    }

    public function test_month_settings_first_open_and_month_change_keep_integer_base_defaults(): void
    {
        $trainer = $this->makeTrainerProfile('Дефолт базы');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа дефолт']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');

        $page = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertMonthSettingsHasIntegerBaseAndXHover($page);
        $this->assertStringContainsString('value="0"', $page);

        $may = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $this->assertSame(
            '0',
            collect($may->json('draft_view_data.month_groups'))->firstWhere('team_id', $team->id)['base_avg_students']
        );
        $this->assertSame(
            '0',
            collect($may->json('draft_view_data.month_groups'))->firstWhere('team_id', $team->id)['base_avg_students_int']
        );
        $this->assertMonthSettingsHasIntegerBaseAndXHover((string) $may->json('month_settings_html'));

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 16,
        ])->assertOk();

        $june = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 6]))
            ->assertOk();
        $this->assertMonthSettingsHasIntegerBaseAndXHover((string) $june->json('month_settings_html'));
        $this->assertSame(
            '0',
            collect($june->json('draft_view_data.month_groups'))->firstWhere('team_id', $team->id)['base_avg_students']
        );
        $this->assertStringNotContainsString('value="16"', (string) $june->json('month_settings_html'));

        $mayAgain = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $this->assertSame(
            '16',
            collect($mayAgain->json('draft_view_data.month_groups'))->firstWhere('team_id', $team->id)['base_avg_students']
        );
        $this->assertMonthSettingsHasIntegerBaseAndXHover((string) $mayAgain->json('month_settings_html'));
        $this->assertStringContainsString('value="16"', (string) $mayAgain->json('month_settings_html'));

        $pageJune = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->getContent();
        $this->assertMonthSettingsHasIntegerBaseAndXHover($pageJune);
        $this->assertStringNotContainsString('value="16"', $pageJune);
    }

    public function test_reloading_data_does_not_bring_back_tenth_hover_on_averages(): void
    {
        $trainer = $this->makeTrainerProfile('Повтор открытие');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа повтор']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => '100.50',
        ])->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 16,
        ])->assertOk();

        $again = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $this->assertKansasTableAveragesHaveNoHover((string) $again->json('table_html'));
        $this->assertMonthSettingsHasIntegerBaseAndXHover((string) $again->json('month_settings_html'));
        $this->assertStringContainsString('title="100.50"', (string) $again->json('month_settings_html'));
        $this->assertStringNotContainsString('title="16.0"', (string) $again->json('table_html'));
        $this->assertStringNotContainsString('title="16.0"', (string) $again->json('month_settings_html'));
        $this->assertStringNotContainsString('title="1.0"', (string) $again->json('table_html'));
    }

    public function test_guest_and_staff_without_rights_cannot_patch_base_average(): void
    {
        $trainer = $this->makeTrainerProfile('База доступ');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа доступ']);
        $payload = [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 16,
        ];

        auth()->logout();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), $payload)
            ->assertUnauthorized();
        $this->from(route('schedule.trainer-salary'))
            ->patch(route('schedule.trainer-salary.draft.update', $trainer), $payload)
            ->assertRedirect();

        $noView = $this->createUserWithoutPermission('schedule.trainerSalary.view', $this->partner);
        $this->actingAs($noView)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), $payload)
            ->assertForbidden();

        $this->actingAs($this->user)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->revokePermission('schedule.trainerSalary.manage');
        $this->grantTrainerSalaryViewKansas();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), $payload)
            ->assertForbidden();

        $this->assertSame(0, TrainerSalaryKansasGroupBaseline::query()->where('team_id', $team->id)->count());
    }

    public function test_non_ajax_integer_base_average_persists_json_not_empty_200(): void
    {
        $trainer = $this->makeTrainerProfile('Non-AJAX база');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа non-ajax']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $response = $this->from(route('schedule.trainer-salary'))
            ->patch(route('schedule.trainer-salary.draft.update', $trainer), [
                'year' => 2026,
                'month' => 5,
                'team_id' => $team->id,
                'base_avg_students' => 12,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode(), 'Autosave Канзаса отвечает JSON, не redirect');
        $response->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён')
            ->assertJsonPath('reload_table', true);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertMonthSettingsHasIntegerBaseAndXHover((string) $response->json('month_settings_html'));
        $this->assertStringContainsString('value="12"', (string) $response->json('month_settings_html'));

        $this->assertDatabaseHas('trainer_salary_kansas_group_baselines', [
            'team_id' => $team->id,
            'base_avg_students_tenths' => 120,
        ]);
    }

    public function test_non_ajax_fractional_base_average_redirects_with_field_error(): void
    {
        $trainer = $this->makeTrainerProfile('Non-AJAX дробь');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа non-ajax дробь']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $response = $this->from(route('schedule.trainer-salary'))
            ->patch(route('schedule.trainer-salary.draft.update', $trainer), [
                'year' => 2026,
                'month' => 5,
                'team_id' => $team->id,
                'base_avg_students' => '16.5',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect();
        $response->assertSessionHasErrors(['base_avg_students']);
        $this->assertNotSame('', (string) session('errors')->first('base_avg_students'));
        $this->assertSame(0, TrainerSalaryKansasGroupBaseline::query()->where('team_id', $team->id)->count());
    }

    public function test_snapshot_freezes_ceiled_fact_and_sheet_has_no_average_hover(): void
    {
        [$trainer, $team] = $this->seedFactAverageVisits(10, 15, 1);

        $this->setTrainerTypeRates($trainer, 1000, 800);
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 100,
        ])->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 15,
        ])->assertOk();

        $form = $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ]);
        $form->assertOk()
            ->assertJsonPath('snapshot.scheme_code', 'kansas')
            ->assertJsonPath('reload_table', true);
        $this->assertNotSame(500, $form->getStatusCode());
        $this->assertNotSame('', trim((string) $form->getContent()));

        $snapshotId = (int) TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->max('id');
        $this->assertDatabaseHas('trainer_salary_kansas_snapshot_groups', [
            'trainer_salary_snapshot_id' => $snapshotId,
            'team_id' => $team->id,
            'fact_avg_tenths' => 160,
            'base_avg_tenths' => 150,
            'premium_cents' => 90000,
        ]);

        $sheet = (string) $this->get(route('schedule.trainer-salary-sheets.snapshot.show', $snapshotId))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('trainer-salary-avg-cell', $sheet);
        $this->assertStringContainsString('>16</span>', $sheet);
        $this->assertStringNotContainsString('15.1', $sheet);
        $this->assertStringNotContainsString('title="16.0"', $sheet);
        $this->assertStringNotContainsString('trainer-salary-input', $sheet);

        $changer = $this->makeStudent($team->id);
        for ($d = 1; $d <= 10; $d++) {
            $this->createVisitedScheduleEntry($changer->id, $trainer->id, sprintf('2026-05-%02d', $d));
        }

        $draftAfter = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id)['groups'][0];
        $this->assertSame('17', $draftAfter['fact_avg_students']);

        $sheetAfter = (string) $this->get(route('schedule.trainer-salary-sheets.snapshot.show', $snapshotId))
            ->assertOk()
            ->getContent();
        $frozen = TrainerSalaryKansasSnapshotGroup::query()
            ->where('trainer_salary_snapshot_id', $snapshotId)
            ->first();
        $this->assertSame(160, (int) $frozen->fact_avg_tenths);
        $this->assertSame(90000, (int) $frozen->premium_cents);
        $this->assertStringContainsString('>16</span>', $sheetAfter);
    }

    public function test_classic_scheme_ignores_kansas_base_average_and_does_not_impose_integer_rule(): void
    {
        $this->useClassicSchemeOnly();
        $trainer = $this->makeTrainerProfile('Classic база');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');

        $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'base_avg_students' => '16.5',
            'rate_per_training' => '10.50',
        ]);
        $response->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён');
        $this->assertSame('10.50', $response->json('row.rate_per_training'));
        $this->assertArrayNotHasKey('groups', $response->json('row') ?? []);
        $this->assertSame(0, TrainerSalaryKansasGroupBaseline::query()->count());

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->json('table_html');
        $this->assertStringNotContainsString('data-field="base_avg_students"', $html);
        $this->assertStringContainsString('step="0.01"', $html);
    }

    /**
     * @return array{0: TrainerProfile, 1: Team, 2: User}
     */
    private function seedFactAverageVisits(int $trainings, int $regularStudents, int $extraOnFirstDay): array
    {
        $trainer = $this->makeTrainerProfile('Факт среднее');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа факт']);

        $regular = [];
        for ($i = 0; $i < $regularStudents; $i++) {
            $regular[] = $this->makeStudent($team->id);
        }

        for ($d = 1; $d <= $trainings; $d++) {
            $date = sprintf('2026-05-%02d', $d);
            foreach ($regular as $student) {
                $this->createVisitedScheduleEntry($student->id, $trainer->id, $date);
            }
        }

        $extra = $this->makeStudent($team->id);
        if ($extraOnFirstDay > 0) {
            $this->createVisitedScheduleEntry($extra->id, $trainer->id, '2026-05-01');
        }

        return [$trainer, $team, $extra];
    }

    private function assertKansasTableAveragesHaveNoHover(string $html): void
    {
        $this->assertStringNotContainsString('data-kids-tooltip-hint', $html);
        $this->assertStringNotContainsString('fa-info-circle', $html);
    }

    private function assertMonthSettingsHasIntegerBaseAndXHover(string $html): void
    {
        $this->assertTrue(
            (bool) preg_match('/<input[^>]*data-field="base_avg_students"[^>]*>/s', $html, $baseMatch),
            'В настройках месяца нет поля базового среднего'
        );
        $this->assertStringContainsString('step="1"', $baseMatch[0]);
        $this->assertStringNotContainsString('step="0.1"', $baseMatch[0]);
        $this->assertStringNotContainsString('data-kids-tooltip-hint', $baseMatch[0]);

        $this->assertTrue(
            (bool) preg_match('/<input[^>]*data-field="premium_increment"[^>]*>/s', $html, $xMatch),
            'В настройках месяца нет поля надбавки X'
        );
        $this->assertStringContainsString('data-kids-tooltip-hint', $xMatch[0]);
        $this->assertStringContainsString('step="0.01"', $xMatch[0]);
    }
}
