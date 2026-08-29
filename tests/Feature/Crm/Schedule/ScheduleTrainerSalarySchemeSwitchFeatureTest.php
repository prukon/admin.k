<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Models\TrainerSalaryKansasPeriodSetting;
use App\Models\TrainerSalaryPeriod;

/**
 * Смена схемы месяца без слепка: страница, autosave, фильтр месяца, заморозка после «Расчет».
 */
final class ScheduleTrainerSalarySchemeSwitchFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();
    }

    public function test_refreshing_salary_page_after_granting_kansas_shows_kansas_table_not_classic(): void
    {
        $this->useClassicSchemeOnly();
        $trainer = $this->makeTrainerProfile('Схема страница');
        $this->setTrainerTypeRates($trainer, 500);

        $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('data-scheme-code="classic"', false);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'rate_per_training' => 999,
            'bonuses' => 40,
        ])->assertOk();

        $this->useKansasSchemeOnly();

        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('data-scheme-code="kansas"', false)
            ->assertSee('Базовая надбавка к премии', false)
            ->assertSee('data-field="premium_increment"', false)
            ->assertSee('Настройки месяца', false)
            ->assertSee('Настройки базовых значений за', false)
            ->assertDontSee('как в отчёте «Нагрузка тренеров»', false)
            ->assertDontSee('data-field="bonuses"', false)
            ->getContent();

        $this->assertStringContainsString('value="0"', $html);
        $this->assertStringNotContainsString('value="0.00"', $html);
        $this->assertStringNotContainsString('value="999.00"', $html);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertNotNull($row);
        $this->assertSame('500.00', $row['rate_per_training']);
        $this->assertSame('kansas', $this->periodScheme(2026, 5));
    }

    public function test_switching_unlocked_month_from_classic_to_sales_resets_draft_fields(): void
    {
        $this->useClassicSchemeOnly();
        $trainer = $this->makeTrainerProfile('Схема sales');
        $trainer->update(['default_base_salary_cents' => 400000]);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => 80,
            'rate_per_training' => 50,
        ])->assertOk();

        $this->useSalesSchemeOnly();

        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('data-scheme-code="sales"', false)
            ->assertSee('data-field="sales_percent"', false)
            ->assertSee('Оплаченные', false)
            ->assertDontSee('как в отчёте «Нагрузка тренеров»', false)
            ->getContent();

        $this->assertStringNotContainsString('value="80.00"', $html);
        $this->assertStringNotContainsString('data-field="rate_per_training"', $html);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->assertJsonPath('scheme_code', 'sales')
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertNotNull($row);
        $this->assertSame('4000.00', $row['base_salary']);
        $this->assertSame(0, $row['sales_percent']);
        $this->assertSame('0.00', $row['bonuses']);
        $this->assertSame('sales', $this->periodScheme(2026, 5));
    }

    public function test_visits_from_classic_month_appear_as_kansas_group_rows_after_switch(): void
    {
        $this->useClassicSchemeOnly();
        $trainer = $this->makeTrainerProfile('Группы после смены');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа Истока']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-12');

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');

        $this->useKansasSchemeOnly();

        $response = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas');

        $html = (string) $response->json('table_html');
        $this->assertStringContainsString('Группа Истока', $html);
        $this->assertStringContainsString('data-team-id="'.$team->id.'"', $html);
        $this->assertStringNotContainsString('data-field="base_avg_students"', $html);
        $this->assertStringNotContainsString('Нет тренировок с визитами', $html);
        $this->assertStringContainsString('data-field="base_avg_students"', (string) $response->json('month_settings_html'));

        $row = collect($response->json('rows'))->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['trainings_count']);
        $this->assertSame('Группа Истока', $row['groups'][0]['team_title'] ?? null);
    }

    public function test_autosave_after_scheme_change_uses_kansas_rules_without_opening_page_first(): void
    {
        $this->useClassicSchemeOnly();
        $trainer = $this->makeTrainerProfile('Патч без GET');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->useKansasSchemeOnly();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['premium_increment']);

        $ok = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 80,
            'bonuses' => 999,
        ]);
        $ok->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён')
            ->assertJsonPath('reload_table', true);
        $this->assertArrayNotHasKey('bonuses', $ok->json('row'));
        $this->assertStringContainsString('value="80"', (string) $ok->json('month_settings_html'));
        $this->assertStringContainsString('title="80.00"', (string) $ok->json('month_settings_html'));
        $this->assertSame('kansas', $this->periodScheme(2026, 5));
    }

    public function test_non_ajax_patch_after_scheme_change_persists_kansas_x_not_empty_200(): void
    {
        $this->useClassicSchemeOnly();
        $trainer = $this->makeTrainerProfile('Non-AJAX смена');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->useKansasSchemeOnly();

        $response = $this->from(route('schedule.trainer-salary'))
            ->patch(route('schedule.trainer-salary.draft.update', $trainer), [
                'year' => 2026,
                'month' => 5,
                'premium_increment' => 120,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response->assertOk();
        $response->assertJsonPath('message', 'Черновик сохранён');
        $this->assertNotSame('', trim((string) $response->getContent()));

        $periodId = (int) TrainerSalaryPeriod::query()
            ->where('partner_id', $this->partner->id)
            ->where('year', 2026)
            ->where('month', 5)
            ->value('id');
        $this->assertDatabaseHas('trainer_salary_kansas_period_settings', [
            'trainer_salary_period_id' => $periodId,
            'premium_increment_cents' => 12000,
        ]);
    }

    public function test_reopening_and_changing_month_after_switch_does_not_reset_kansas_x(): void
    {
        $this->useClassicSchemeOnly();
        $trainer = $this->makeTrainerProfile('X после смены');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->useKansasSchemeOnly();

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas')
            ->assertJsonPath('draft_view_data.premium_increment', '0.00');

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 90,
        ])->assertOk();

        $again = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas')
            ->assertJsonPath('draft_view_data.premium_increment', '90.00');
        $this->assertStringContainsString('value="90"', (string) $again->json('month_settings_html'));
        $this->assertStringContainsString('title="90.00"', (string) $again->json('month_settings_html'));

        $june = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas');
        $this->assertSame('0.00', $june->json('draft_view_data.premium_increment'));

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('draft_view_data.premium_increment', '90.00');
    }

    public function test_snapshot_keeps_classic_on_page_and_does_not_impose_kansas_defaults(): void
    {
        $this->useClassicSchemeOnly();
        $trainer = $this->makeTrainerProfile('Заморозка UI');

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => 70,
        ])->assertOk();
        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $this->useKansasSchemeOnly();

        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('data-scheme-code="classic"', false)
            ->assertDontSee('Базовая надбавка к премии', false)
            ->getContent();
        $this->assertStringContainsString('data-field="bonuses"', $html);
        $this->assertStringNotContainsString('data-field="premium_increment"', $html);

        $patch = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 50,
            'bonuses' => 71,
        ]);
        $patch->assertOk()->assertJsonPath('row.bonuses', '71.00');
        $this->assertArrayNotHasKey('reload_table', $patch->json());

        $this->assertSame('classic', $this->periodScheme(2026, 5));
        $this->assertSame(0, TrainerSalaryKansasPeriodSetting::query()->count());
    }

    public function test_form_all_snapshot_also_freezes_month_scheme(): void
    {
        $this->useClassicSchemeOnly();
        $this->makeTrainerProfile('Пакет заморозка');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $this->useKansasSchemeOnly();

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');
        $this->assertSame('classic', $this->periodScheme(2026, 5));
    }

    public function test_viewer_opening_page_after_scheme_change_switches_unlocked_month(): void
    {
        $this->useClassicSchemeOnly();
        $this->makeTrainerProfile('Смена от просмотра');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');

        $this->revokePermission('schedule.trainerSalary.manage');
        $this->useKansasSchemeOnly();

        $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('data-scheme-code="kansas"', false)
            ->assertSee('data-can-manage="0"', false)
            ->assertDontSee('trainer-salary-input', false);

        $this->assertSame('kansas', $this->periodScheme(2026, 5));
    }

    public function test_both_classic_and_sales_granted_unlocked_month_stays_classic(): void
    {
        $this->useSalesSchemeOnly();
        $this->makeTrainerProfile('Sales затем classic');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'sales');

        $this->grantClassicScheme();

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');
        $this->assertSame('classic', $this->periodScheme(2026, 5));
        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('data-field="sales_percent"', $html);
    }

    public function test_both_kansas_and_sales_granted_unlocked_month_stays_kansas(): void
    {
        $this->useSalesSchemeOnly();
        $this->makeTrainerProfile('Sales затем kansas');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'sales');

        $this->grantKansasScheme();

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas');
        $this->assertSame('kansas', $this->periodScheme(2026, 5));
        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('data-field="sales_percent"', $html);
        $this->assertStringContainsString('Настройки месяца', $html);
    }

    public function test_both_schemes_granted_unlocked_kansas_month_becomes_classic(): void
    {
        $this->makeTrainerProfile('Обе схемы');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas');

        $this->grantClassicScheme();

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');
        $this->assertSame('classic', $this->periodScheme(2026, 5));
    }

    public function test_guest_cannot_switch_unlocked_month_scheme(): void
    {
        $this->useClassicSchemeOnly();
        $this->makeTrainerProfile('Гость не меняет');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->assertSame('classic', $this->periodScheme(2026, 5));

        $this->useKansasSchemeOnly();
        auth()->logout();

        $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))->assertRedirect();
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertUnauthorized();

        $this->assertSame('classic', $this->periodScheme(2026, 5));
    }

    public function test_staff_without_scheme_still_gets_403_on_unlocked_classic_month(): void
    {
        $this->useClassicSchemeOnly();
        $trainer = $this->makeTrainerProfile('Без схемы 403');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->revokePermission('schedule.trainerSalary.scheme.classic');
        $this->revokePermission('schedule.trainerSalary.scheme.kansas');
        $this->revokePermission('schedule.trainerSalary.scheme.sales');

        $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertForbidden();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'rate_per_training' => 1,
        ])->assertForbidden();

        $this->assertSame('classic', $this->periodScheme(2026, 5));
    }

    private function periodScheme(int $year, int $month): string
    {
        return (string) TrainerSalaryPeriod::query()
            ->where('partner_id', $this->partner->id)
            ->where('year', $year)
            ->where('month', $month)
            ->value('scheme_code');
    }
}
