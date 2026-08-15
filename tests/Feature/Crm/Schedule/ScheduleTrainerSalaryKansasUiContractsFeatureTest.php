<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Models\TrainerSalaryKansasPeriodSetting;
use App\Models\TrainerSalaryPeriod;
use App\Models\TrainerSalarySnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Канзас: UX-контракты (reload всей таблицы, дефолты, разметка, safety-net, валидация по полям).
 */
final class ScheduleTrainerSalaryKansasUiContractsFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();
    }

    public function test_changing_school_x_reloads_full_table_so_every_trainer_total_updates(): void
    {
        $trainerA = $this->makeTrainerProfile('Канзас Альфа');
        $trainerB = $this->makeTrainerProfile('Канзас Бета');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Общая X']);
        $studentA = $this->makeStudent($team->id);
        $studentB = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($studentA->id, $trainerA->id, '2026-05-04');
        $this->createVisitedScheduleEntry($studentB->id, $trainerB->id, '2026-05-05');
        $this->setTrainerTypeRates($trainerA, 1000);

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $before = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        );
        $this->assertSame('1000.00', $before->firstWhere('trainer_profile_id', $trainerA->id)['total']);
        $this->assertSame('1000.00', $before->firstWhere('trainer_profile_id', $trainerB->id)['total']);

        $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainerA), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 100,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён')
            ->assertJsonPath('reload_table', true);

        $html = (string) $response->json('table_html');
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('Канзас Альфа', $html);
        $this->assertStringContainsString('Канзас Бета', $html);
        $this->assertSame(2, substr_count($html, 'trainer-salary-value--total'));
        $this->assertStringContainsString('1 100', $html);
        $this->assertStringContainsString('value="100.00"', $html);
        $this->assertStringContainsString('data-field="premium_increment"', $html);
        $this->assertSame('1100.00', $response->json('row.total'));

        $after = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        );
        $this->assertSame('1100.00', $after->firstWhere('trainer_profile_id', $trainerA->id)['total']);
        $this->assertSame('1100.00', $after->firstWhere('trainer_profile_id', $trainerB->id)['total']);
    }

    public function test_changing_shared_baseline_reloads_table_for_both_trainers(): void
    {
        $trainerA = $this->makeTrainerProfile('База А');
        $trainerB = $this->makeTrainerProfile('База Б');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа база']);
        $student = $this->makeStudent($team->id);
        $this->createScheduleStatusEntry(
            $student->id,
            (int) $this->visitedStatusId,
            '2026-05-06',
            null,
            [$trainerA->id, $trainerB->id],
        );
        $this->setTrainerTypeRates($trainerA, 1000);

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainerA), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 100,
        ])->assertOk();

        $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainerB), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 2,
        ]);

        $response->assertOk()->assertJsonPath('reload_table', true);
        $html = (string) $response->json('table_html');
        $this->assertStringContainsString('База А', $html);
        $this->assertStringContainsString('База Б', $html);
        $this->assertStringContainsString('data-team-id="'.$team->id.'"', $html);
        $this->assertStringContainsString('value="2.0"', $html);

        $rows = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        );
        $this->assertSame('2.0', $rows->firstWhere('trainer_profile_id', $trainerA->id)['groups'][0]['base_avg_students']);
        $this->assertSame('2.0', $rows->firstWhere('trainer_profile_id', $trainerB->id)['groups'][0]['base_avg_students']);
    }

    public function test_kansas_form_one_and_form_all_also_reload_full_table(): void
    {
        $trainer = $this->makeTrainerProfile('Слепок reload');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('reload_table', true)
            ->assertJsonPath('snapshot.scheme_code', 'kansas');

        $html = (string) $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('reload_table', true)
            ->json('table_html');

        $this->assertStringContainsString('Слепок reload', $html);
        $this->assertStringContainsString('Слепок v', $html);
    }

    public function test_classic_draft_save_does_not_ask_client_to_replace_whole_table(): void
    {
        $this->grantClassicScheme();
        $trainer = $this->makeTrainerProfile('Классика без reload');

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');

        $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => 50,
        ])->assertOk();

        $this->assertArrayNotHasKey('reload_table', $response->json());
        $this->assertArrayNotHasKey('table_html', $response->json());
        $response->assertJsonPath('row.bonuses', '50.00');
    }

    public function test_first_open_shows_zero_x_and_system_type_rates(): void
    {
        $trainer = $this->makeTrainerProfile('Дефолт оклад');
        $trainer->update(['default_rate_per_training_cents' => 50000]);

        $page = $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $html = $page->getContent();

        $this->assertStringContainsString('id="trainer-salary-month"', $html);
        $this->assertStringContainsString('value="2026-05"', $html);
        $this->assertStringContainsString('data-field="premium_increment"', $html);
        $this->assertStringContainsString('value="0.00"', $html);
        $this->assertStringContainsString('Дефолт оклад', $html);
        $this->assertStringNotContainsString('data-field="rate_per_training"', $html);
        $this->assertStringContainsString('Типы тренеров', $html);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertNotNull($row);
        $this->assertSame('0.00', $row['rate_per_training']);
        $this->assertSame('0.00', $row['base_premium']);
        $this->assertSame('0.00', $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->json('draft_view_data.premium_increment'));
    }

    public function test_changing_month_rebuilds_form_without_copying_x_from_another_month(): void
    {
        $trainer = $this->makeTrainerProfile('Месяц X');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 250,
        ])->assertOk();

        $june = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 6]))
            ->assertOk();
        $this->assertSame('0.00', $june->json('draft_view_data.premium_increment'));
        $this->assertStringContainsString('value="0.00"', (string) $june->json('table_html'));
        $this->assertStringNotContainsString('value="250.00"', (string) $june->json('table_html'));

        $mayAgain = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $this->assertSame('250.00', $mayAgain->json('draft_view_data.premium_increment'));
        $this->assertStringContainsString('value="250.00"', (string) $mayAgain->json('table_html'));

        $pageJune = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('value="2026-06"', $pageJune);
        $this->assertStringContainsString('value="0.00"', $pageJune);
    }

    public function test_manage_table_has_kansas_fields_in_order_with_error_hooks(): void
    {
        $trainer = $this->makeTrainerProfile('Разметка manage');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа разметка']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->json('table_html');

        $needles = [
            'Оклад',
            'Базовая',
            'Баз.',
            'Факт',
            'Разница',
            'Премия',
            'Итого',
            'Кол-во',
            'Расчет',
        ];
        $cursor = 0;
        foreach ($needles as $needle) {
            $pos = mb_strpos($html, $needle, $cursor);
            $this->assertNotFalse($pos, "В таблице Канзаса нет заголовка «{$needle}» в ожидаемом порядке");
            $cursor = $pos + mb_strlen($needle);
        }

        $this->assertStringContainsString('data-field="premium_increment"', $html);
        $this->assertStringContainsString('data-field="base_avg_students"', $html);
        $this->assertStringContainsString('data-error-for="premium_increment"', $html);
        $this->assertStringContainsString('data-error-for="base_avg_students"', $html);
        $this->assertStringNotContainsString('data-field="rate_per_training"', $html);
        $this->assertStringNotContainsString('data-field="base_premium"', $html);
        $this->assertStringNotContainsString('data-error-for="rate_per_training"', $html);
        $this->assertStringNotContainsString('data-error-for="base_premium"', $html);
        $this->assertStringContainsString('data-error-for="team_id"', $html);
        $this->assertStringContainsString('data-save-trainer-id="'.$trainer->id.'"', $html);
        $this->assertStringContainsString('data-team-id="'.$team->id.'"', $html);
        $this->assertStringContainsString('trainer-salary-form-one-btn', $html);
        $this->assertStringContainsString('>Расчет</', $html);

        $page = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="trainer-salary-form-all-btn"', $page);
        $this->assertStringContainsString('/js/trainer-salary.js', $page);
        $this->assertStringNotContainsString('resources/js/trainer-salary.js', $page);
    }

    public function test_view_only_kansas_table_hides_inputs_and_uses_readonly_x(): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('schedule.trainerSalary.manage'))
            ->delete();

        $this->makeTrainerProfile('Только смотрит');

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('can_manage', false)
            ->json('table_html');

        $this->assertStringNotContainsString('trainer-salary-input', $html);
        $this->assertStringNotContainsString('trainer-salary-form-one-btn', $html);
        $this->assertStringNotContainsString('>Расчет</', $html);
        $this->assertStringContainsString('trainer-salary-readonly', $html);

        $page = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('id="trainer-salary-form-all-btn"', $page);
        $this->assertStringContainsString('data-can-manage="0"', $page);
    }

    public function test_classic_partner_does_not_see_kansas_fields(): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('schedule.trainerSalary.scheme.kansas'))
            ->delete();
        $this->grantClassicScheme();
        $this->makeTrainerProfile('Классика UI');

        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-scheme-code="classic"', $html);
        $this->assertStringNotContainsString('базовая надбавка к премии', $html);
        $this->assertStringNotContainsString('data-field="premium_increment"', $html);
        $this->assertStringNotContainsString('data-field="base_avg_students"', $html);
        $this->assertStringContainsString('как в отчёте «Нагрузка тренеров»', $html);
    }

    public function test_month_without_snapshot_switches_to_kansas_and_discards_classic_draft(): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('schedule.trainerSalary.scheme.kansas'))
            ->delete();
        $this->grantClassicScheme();
        $trainer = $this->makeTrainerProfile('Смена схемы без слепка');

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => 500,
        ])
            ->assertOk()
            ->assertJsonPath('row.bonuses', '500.00');

        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('schedule.trainerSalary.scheme.classic'))
            ->delete();
        $this->grantKansasScheme();

        $response = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas');

        $html = (string) $response->json('table_html');
        $this->assertStringContainsString('базовая надбавка к премии', $html);
        $this->assertStringContainsString('data-field="premium_increment"', $html);
        $this->assertStringNotContainsString('как в отчёте «Нагрузка тренеров»', $html);

        $row = collect($response->json('rows'))->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('bonuses', $row);
        $this->assertSame('kansas', TrainerSalaryPeriod::query()
            ->where('partner_id', $this->partner->id)
            ->where('year', 2026)
            ->where('month', 5)
            ->value('scheme_code'));
    }

    public function test_month_with_snapshot_keeps_classic_after_kansas_is_granted(): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('schedule.trainerSalary.scheme.kansas'))
            ->delete();
        $this->grantClassicScheme();
        $trainer = $this->makeTrainerProfile('Заморозка после расчета');

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('snapshot.scheme_code', 'classic');

        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('schedule.trainerSalary.scheme.classic'))
            ->delete();
        $this->grantKansasScheme();

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas');
    }

    public function test_kansas_month_without_snapshot_switches_to_classic_and_drops_x(): void
    {
        $trainer = $this->makeTrainerProfile('Смена с канзаса');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 250,
        ])->assertOk();

        $periodId = (int) TrainerSalaryPeriod::query()
            ->where('partner_id', $this->partner->id)
            ->where('year', 2026)
            ->where('month', 5)
            ->value('id');
        $this->assertSame(1, TrainerSalaryKansasPeriodSetting::query()
            ->where('trainer_salary_period_id', $periodId)
            ->where('premium_increment_cents', 25000)
            ->count());

        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('schedule.trainerSalary.scheme.kansas'))
            ->delete();
        $this->grantClassicScheme();

        $response = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');

        $html = (string) $response->json('table_html');
        $this->assertStringContainsString('data-field="bonuses"', $html);
        $this->assertStringContainsString('Бонусы', $html);
        $this->assertStringNotContainsString('data-field="premium_increment"', $html);
        $this->assertSame(0, TrainerSalaryKansasPeriodSetting::query()
            ->where('trainer_salary_period_id', $periodId)
            ->count());
        $this->assertSame('classic', TrainerSalaryPeriod::query()->whereKey($periodId)->value('scheme_code'));
    }

    public function test_non_ajax_patch_persists_draft_and_returns_json_not_empty_200(): void
    {
        $trainer = $this->makeTrainerProfile('Non-AJAX канзас');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $response = $this->from(route('schedule.trainer-salary'))
            ->patch(route('schedule.trainer-salary.draft.update', $trainer), [
                'year' => 2026,
                'month' => 5,
                'premium_increment' => 777,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode(), 'Autosave Канзаса отвечает JSON, не redirect');
        $response->assertOk();
        $response->assertJsonPath('message', 'Черновик сохранён');
        $this->assertStringContainsString('value="777.00"', (string) $response->json('table_html'));
        $this->assertNotSame('', trim((string) $response->getContent()));

        $periodId = (int) TrainerSalaryPeriod::query()
            ->where('partner_id', $this->partner->id)
            ->where('year', 2026)
            ->where('month', 5)
            ->value('id');
        $settings = TrainerSalaryKansasPeriodSetting::query()
            ->where('trainer_salary_period_id', $periodId)
            ->first();
        $this->assertNotNull($settings);
        $this->assertSame(77700, (int) $settings->premium_increment_cents);
    }

    public function test_non_ajax_form_one_creates_snapshot_and_returns_json_not_empty_200(): void
    {
        $trainer = $this->makeTrainerProfile('Non-AJAX слепок');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $response = $this->from(route('schedule.trainer-salary'))
            ->post(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
                'year' => 2026,
                'month' => 5,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response->assertOk();
        $response->assertJsonPath('snapshot.scheme_code', 'kansas');
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertDatabaseHas('trainer_salary_snapshots', [
            'trainer_profile_id' => $trainer->id,
            'scheme_code' => 'kansas',
            'version' => 1,
        ]);
    }

    public function test_kansas_draft_patch_rejects_rate_and_premium_and_does_not_save_x(): void
    {
        $trainer = $this->makeTrainerProfile('Readonly из таблицы');
        $this->setTrainerTypeRates($trainer, 400, 50);
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'rate_per_training' => 999,
            'base_premium' => 888,
            'premium_increment' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rate_per_training', 'base_premium']);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('400.00', $row['rate_per_training']);
        $this->assertSame('50.00', $row['base_premium']);
        $this->assertSame('0.00', $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->json('draft_view_data.premium_increment'));
    }

    public function test_validation_errors_are_returned_per_field(): void
    {
        $trainer = $this->makeTrainerProfile('Поля 422');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа 422']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-08');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'rate_per_training' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rate_per_training']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'base_premium' => -5,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['base_premium']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => -10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['premium_increment']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'base_avg_students' => 16,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['base_avg_students']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'month' => 5,
            'rate_per_training' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year']);

        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'Чужая группа',
        ]);
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $foreignTeam->id,
            'base_avg_students' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_classic_bonus_field_on_kansas_patch_is_ignored_and_does_not_500(): void
    {
        $trainer = $this->makeTrainerProfile('Игнор bonuses');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => 999,
            'premium_increment' => 10,
        ]);

        $response->assertOk()->assertJsonPath('row.rate_per_training', '0.00');
        $this->assertArrayNotHasKey('bonuses', $response->json('row'));
    }

    public function test_kansas_x_does_not_leak_to_another_partner(): void
    {
        $trainer = $this->makeTrainerProfile('Школа A');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 333,
        ])->assertOk();

        foreach ([
            'schedule.trainerSalary.view',
            'schedule.trainerSalary.manage',
            'schedule.trainerSalary.scheme.kansas',
        ] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->foreignPartner->id,
                'role_id' => $this->foreignUser->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->asForeignUser();
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $foreign = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas');
        $this->assertSame('0.00', $foreign->json('draft_view_data.premium_increment'));

        $this->assertSame(1, TrainerSalaryKansasPeriodSetting::query()
            ->whereHas('period', fn ($q) => $q->where('partner_id', $this->partner->id)->where('year', 2026)->where('month', 5))
            ->where('premium_increment_cents', 33300)
            ->count());
    }

    public function test_kansas_snapshot_page_is_readonly_and_shows_groups(): void
    {
        $trainer = $this->makeTrainerProfile('Лист Канзас');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа листа']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-09');

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $snapshotId = (int) TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->max('id');

        $html = (string) $this->get(route('schedule.trainer-salary-sheets.snapshot.show', $snapshotId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Группа листа', $html);
        $this->assertStringContainsString('Лист Канзас', $html);
        $this->assertStringContainsString('trainer-salary-table--readonly', $html);
        $this->assertStringContainsString('trainer-salary-table--kansas', $html);
        $this->assertStringNotContainsString('trainer-salary-input', $html);
        $this->assertStringNotContainsString('trainer-salary-form-one-btn', $html);
    }
}
