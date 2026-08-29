<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\TrainerSalarySalesDraftTrainer;
use App\Models\TrainerSalarySnapshot;
use App\Models\UserPrice;
use Illuminate\Support\Facades\DB;

/**
 * Схема sales: UX-контракты (дефолт %, разметка, live-row без reload_table, safety-net, валидация по полям).
 */
final class ScheduleTrainerSalarySalesUiContractsFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantTrainerSalaryViewSales();
        $this->grantTrainerSalaryManage();
    }

    public function test_first_open_shows_zero_percent_copied_salary_and_sales_subtitle(): void
    {
        $trainer = $this->makeTrainerProfile('Дефолт sales');
        $trainer->update(['default_base_salary_cents' => 1200000]);

        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="trainer-salary-month"', $html);
        $this->assertStringContainsString('value="2026-05"', $html);
        $this->assertStringContainsString('data-scheme-code="sales"', $html);
        $this->assertStringContainsString('trainer-salary-subtitle', $html);
        $this->assertStringContainsString(
            '% — от оплаченных месяцев этого периода и абонементов по дате оплаты',
            $html
        );
        $this->assertStringContainsString('data-field="sales_percent"', $html);
        $this->assertStringContainsString('data-error-for="sales_percent"', $html);
        $this->assertStringContainsString('Дефолт sales', $html);
        $this->assertStringNotContainsString('Настройки месяца', $html);
        $this->assertStringNotContainsString('id="trainerSalaryKansasMonthSettingsModal"', $html);
        $this->assertStringNotContainsString('data-field="premium_increment"', $html);
        $this->assertStringNotContainsString('data-field="rate_per_training"', $html);
        $this->assertStringNotContainsString('как в отчёте «Нагрузка тренеров»', $html);
        $this->assertStringNotContainsString('name="sales_percent"', $html);

        $percentInput = $this->salesPercentInputHtml($html);
        $this->assertStringContainsString('step="1"', $percentInput);
        $this->assertStringContainsString('min="0"', $percentInput);
        $this->assertStringContainsString('max="100"', $percentInput);
        $this->assertStringContainsString('value="0"', $percentInput);
        $this->assertStringNotContainsString('value="0.00"', $percentInput);

        $data = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'sales')
            ->assertJsonPath('show_trainer_types', false);
        $this->assertStringContainsString('абонементов по дате оплаты', (string) $data->json('draft_subtitle'));
        $this->assertSame('', (string) $data->json('month_settings_html'));

        $row = collect($data->json('rows'))->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertNotNull($row);
        $this->assertSame('12000.00', $row['base_salary']);
        $this->assertSame(0, $row['sales_percent']);
        $this->assertSame('0.00', $row['commission']);
        $this->assertSame('12000.00', $row['total']);
    }

    public function test_changing_month_rebuilds_form_without_copying_percent_from_another_month(): void
    {
        $trainer = $this->makeTrainerProfile('Месяц %');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 25,
        ])->assertOk();

        $june = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'sales');
        $juneRow = collect($june->json('rows'))->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertSame(0, $juneRow['sales_percent']);
        $junePercent = $this->salesPercentInputHtml((string) $june->json('table_html'));
        $this->assertStringContainsString('value="0"', $junePercent);
        $this->assertStringNotContainsString('value="25"', $junePercent);

        $pageJune = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('value="2026-06"', $pageJune);
        $this->assertStringContainsString('value="0"', $this->salesPercentInputHtml($pageJune));
        $this->assertStringNotContainsString('value="25"', $this->salesPercentInputHtml($pageJune));

        $mayAgain = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $mayRow = collect($mayAgain->json('rows'))->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertSame(25, $mayRow['sales_percent']);
        $this->assertStringContainsString('value="25"', $this->salesPercentInputHtml((string) $mayAgain->json('table_html')));
    }

    public function test_manage_table_has_sales_columns_in_order_with_error_hooks(): void
    {
        $this->makeTrainerProfile('Разметка sales');

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->json('table_html');

        $needles = [
            'Тренер',
            'Оклад',
            '%',
            'Оплаченные',
            'Абонементы',
            'База',
            '% от',
            'Бонусы',
            'Вычеты',
            'Коммент.',
            'Итого',
            'Расчет',
        ];
        $cursor = 0;
        foreach ($needles as $needle) {
            $pos = mb_strpos($html, $needle, $cursor);
            $this->assertNotFalse($pos, "В таблице sales нет заголовка «{$needle}» в ожидаемом порядке");
            $cursor = $pos + mb_strlen($needle);
        }

        $this->assertStringContainsString('data-field="base_salary"', $html);
        $this->assertStringContainsString('data-field="sales_percent"', $html);
        $this->assertStringContainsString('data-field="bonuses"', $html);
        $this->assertStringContainsString('data-field="deductions"', $html);
        $this->assertStringContainsString('data-field="comment"', $html);
        $this->assertStringContainsString('data-error-for="sales_percent"', $html);
        $this->assertStringContainsString('data-error-for="base_salary"', $html);
        $this->assertStringContainsString('trainer-salary-paid-months', $html);
        $this->assertStringContainsString('trainer-salary-paid-packages', $html);
        $this->assertStringContainsString('trainer-salary-sales-base', $html);
        $this->assertStringContainsString('trainer-salary-commission', $html);
        $this->assertStringContainsString('trainer-salary-form-one-btn', $html);
        $this->assertStringContainsString('>Расчет</', $html);
        $this->assertStringNotContainsString('data-field="rate_per_training"', $html);
        $this->assertStringNotContainsString('data-field="premium_increment"', $html);
        $this->assertStringNotContainsString('data-field="base_avg_students"', $html);
        $this->assertStringNotContainsString('data-field="base_premium"', $html);
    }

    public function test_active_trainer_without_sales_still_appears_in_table(): void
    {
        $trainer = $this->makeTrainerProfile('Без оплат');

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->json('table_html');

        $this->assertStringContainsString('Без оплат', $html);
        $this->assertStringNotContainsString('Нет активных тренеров', $html);
        $this->assertStringContainsString('data-trainer-id="'.$trainer->id.'"', $html);
    }

    public function test_empty_partner_shows_no_trainers_placeholder(): void
    {
        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->json('table_html');

        $this->assertStringContainsString('Нет активных тренеров', $html);
    }

    public function test_view_only_sales_table_hides_inputs_and_form_buttons(): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('schedule.trainerSalary.manage'))
            ->delete();

        $this->makeTrainerProfile('Только смотрит sales');

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('can_manage', false)
            ->json('table_html');

        $this->assertStringNotContainsString('trainer-salary-input', $html);
        $this->assertStringNotContainsString('trainer-salary-form-one-btn', $html);
        $this->assertStringNotContainsString('>Расчет</', $html);
        $this->assertStringNotContainsString('data-field="sales_percent"', $html);
        $this->assertStringContainsString('trainer-salary-readonly', $html);
        $this->assertStringContainsString('Только смотрит sales', $html);
        $this->assertSame('', (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->json('month_settings_html'));

        $page = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('id="trainer-salary-form-all-btn"', $page);
        $this->assertStringNotContainsString('Настройки месяца', $page);
        $this->assertStringContainsString('data-can-manage="0"', $page);
        $this->assertStringContainsString('trainer-salary-subtitle', $page);
    }

    public function test_classic_partner_does_not_see_sales_fields(): void
    {
        $this->useClassicSchemeOnly();
        $this->makeTrainerProfile('Классика UI sales');

        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-scheme-code="classic"', $html);
        $this->assertStringNotContainsString('data-field="sales_percent"', $html);
        $this->assertStringNotContainsString('trainer-salary-paid-months', $html);
        $this->assertStringNotContainsString('trainer-salary-commission', $html);
        $this->assertStringContainsString('как в отчёте «Нагрузка тренеров»', $html);
        $this->assertStringContainsString('data-field="rate_per_training"', $html);
    }

    public function test_kansas_partner_does_not_see_sales_fields(): void
    {
        $this->useKansasSchemeOnly();
        $this->makeTrainerProfile('Канзас UI sales');

        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-scheme-code="kansas"', $html);
        $this->assertStringNotContainsString('data-field="sales_percent"', $html);
        $this->assertStringNotContainsString('trainer-salary-commission', $html);
        $this->assertStringContainsString('Настройки месяца', $html);
    }

    public function test_saving_percent_returns_row_for_live_update_and_does_not_reload_table(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Live row');
        $trainer->update(['default_base_salary_cents' => 0]);
        UserPrice::factory()->forUserAndMonth((int) $student->id, '2026-05-01', 800000, true, (int) $team->id)->create();

        $opened = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $openedRow = collect($opened->json('rows'))->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertSame('8000.00', $openedRow['paid_months']);
        $this->assertSame('0.00', $openedRow['commission']);
        $this->assertStringContainsString('trainer-salary-paid-months', (string) $opened->json('table_html'));

        $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён')
            ->assertJsonPath('row.sales_percent', 10)
            ->assertJsonPath('row.paid_months', '8000.00')
            ->assertJsonPath('row.paid_packages', '0.00')
            ->assertJsonPath('row.sales_base', '8000.00')
            ->assertJsonPath('row.commission', '800.00')
            ->assertJsonPath('row.total', '800.00');
        $this->assertArrayNotHasKey('reload_table', $response->json());
        $this->assertArrayNotHasKey('table_html', $response->json());
        $this->assertArrayNotHasKey('month_settings_html', $response->json());
        $this->assertSame($trainer->id, (int) $response->json('row.trainer_profile_id'));
    }

    public function test_form_one_and_form_all_also_skip_full_table_reload(): void
    {
        $trainer = $this->makeTrainerProfile('Слепок без reload');

        $one = $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ]);
        $one->assertOk()
            ->assertJsonPath('snapshot.scheme_code', 'sales')
            ->assertJsonPath('row.sales_percent', 0);
        $this->assertArrayNotHasKey('reload_table', $one->json());
        $this->assertArrayNotHasKey('table_html', $one->json());

        $all = $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ]);
        $all->assertOk()
            ->assertJsonStructure(['message', 'batch_id', 'snapshots_count', 'rows']);
        $this->assertArrayNotHasKey('reload_table', $all->json());
        $this->assertArrayNotHasKey('table_html', $all->json());
        $this->assertGreaterThan(0, (int) $all->json('snapshots_count'));
    }

    public function test_non_ajax_patch_persists_draft_and_returns_json_not_empty_200(): void
    {
        $trainer = $this->makeTrainerProfile('Non-AJAX sales');

        $response = $this->from(route('schedule.trainer-salary'))
            ->patch(route('schedule.trainer-salary.draft.update', $trainer), [
                'year' => 2026,
                'month' => 5,
                'sales_percent' => 12,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode(), 'Autosave sales отвечает JSON, не redirect');
        $response->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён')
            ->assertJsonPath('row.sales_percent', 12);
        $this->assertArrayNotHasKey('reload_table', $response->json());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertDatabaseHas('trainer_salary_sales_draft_trainers', [
            'sales_percent' => 12,
        ]);
    }

    public function test_non_ajax_form_one_creates_snapshot_and_returns_json_not_empty_200(): void
    {
        $trainer = $this->makeTrainerProfile('Non-AJAX слепок sales');

        $response = $this->from(route('schedule.trainer-salary'))
            ->post(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
                'year' => 2026,
                'month' => 5,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response->assertOk();
        $response->assertJsonPath('snapshot.scheme_code', 'sales');
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertDatabaseHas('trainer_salary_snapshots', [
            'trainer_profile_id' => $trainer->id,
            'scheme_code' => 'sales',
            'version' => 1,
        ]);
    }

    public function test_non_ajax_form_all_creates_snapshots_and_returns_json_not_empty_200(): void
    {
        $trainer = $this->makeTrainerProfile('Non-AJAX всех sales');

        $response = $this->from(route('schedule.trainer-salary'))
            ->post(route('schedule.trainer-salary.snapshots.form-all'), [
                'year' => 2026,
                'month' => 5,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode(), 'Сформировать всех отвечает JSON, не redirect');
        $response->assertOk();
        $this->assertArrayNotHasKey('reload_table', $response->json());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertGreaterThan(0, (int) $response->json('snapshots_count'));
        $this->assertDatabaseHas('trainer_salary_snapshots', [
            'trainer_profile_id' => $trainer->id,
            'scheme_code' => 'sales',
        ]);
    }

    public function test_non_ajax_fractional_percent_redirects_with_field_error_and_does_not_save(): void
    {
        $trainer = $this->makeTrainerProfile('Non-AJAX дробь %');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $response = $this->from(route('schedule.trainer-salary'))
            ->patch(route('schedule.trainer-salary.draft.update', $trainer), [
                'year' => 2026,
                'month' => 5,
                'sales_percent' => '10.5',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect();
        $response->assertSessionHasErrors(['sales_percent']);
        $this->assertNotSame('', (string) session('errors')->first('sales_percent'));

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertSame(0, $row['sales_percent']);
    }

    public function test_validation_errors_are_returned_per_field(): void
    {
        $trainer = $this->makeTrainerProfile('Поля 422 sales');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $fraction = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => '10.5',
        ]);
        $fraction->assertStatus(422)
            ->assertJsonValidationErrors(['sales_percent']);
        $this->assertIsArray($fraction->json('errors.sales_percent'));
        $this->assertNotSame('', (string) $fraction->json('errors.sales_percent.0'));

        $over = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 101,
        ]);
        $over->assertStatus(422)
            ->assertJsonValidationErrors(['sales_percent']);
        $this->assertNotSame('', (string) $over->json('errors.sales_percent.0'));

        $negative = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => -1,
        ]);
        $negative->assertStatus(422)
            ->assertJsonValidationErrors(['sales_percent']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'base_salary' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['base_salary']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => -5,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bonuses']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'deductions' => -5,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['deductions']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'month' => 5,
            'sales_percent' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'sales_percent' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);

        $this->assertSame(
            0,
            collect($this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows'))
                ->firstWhere('trainer_profile_id', $trainer->id)['sales_percent']
        );
    }

    public function test_kansas_and_classic_fields_on_sales_patch_are_ignored_and_do_not_500(): void
    {
        $trainer = $this->makeTrainerProfile('Игнор чужих полей');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 8,
            'rate_per_training' => 999,
            'premium_increment' => 10,
            'base_avg_students' => 16.5,
            'base_premium' => 50,
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk()
            ->assertJsonPath('row.sales_percent', 8);
        $this->assertArrayNotHasKey('rate_per_training', $response->json('row') ?? []);
        $this->assertArrayNotHasKey('groups', $response->json('row') ?? []);
        $this->assertArrayNotHasKey('premium_increment', $response->json('row') ?? []);
    }

    public function test_classic_scheme_ignores_sales_percent_and_does_not_impose_integer_rule(): void
    {
        $this->useClassicSchemeOnly();
        $trainer = $this->makeTrainerProfile('Classic %');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');

        $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => '10.5',
            'rate_per_training' => '10.50',
        ]);
        $response->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён');
        $this->assertSame('10.50', $response->json('row.rate_per_training'));
        $this->assertArrayNotHasKey('sales_percent', $response->json('row') ?? []);
        $this->assertSame(0, TrainerSalarySalesDraftTrainer::query()->count());

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->json('table_html');
        $this->assertStringNotContainsString('data-field="sales_percent"', $html);
        $this->assertStringContainsString('step="0.01"', $html);
    }

    public function test_get_data_returns_sales_payload_and_rejects_invalid_period_per_field(): void
    {
        $trainer = $this->makeTrainerProfile('JSON sales');

        $response = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'sales')
            ->assertJsonPath('can_manage', true)
            ->assertJsonPath('show_trainer_types', false)
            ->assertJsonStructure([
                'year',
                'month',
                'month_label',
                'date_from',
                'date_to',
                'scheme_code',
                'draft_subtitle',
                'draft_view_data',
                'table_view',
                'can_manage',
                'table_html',
                'month_settings_html',
                'rows' => [
                    [
                        'trainer_profile_id',
                        'trainer_name',
                        'base_salary',
                        'sales_percent',
                        'paid_months',
                        'paid_packages',
                        'sales_base',
                        'commission',
                        'total',
                    ],
                ],
            ]);

        $this->assertNotSame('', trim((string) $response->json('table_html')));
        $this->assertSame('', (string) $response->json('month_settings_html'));
        $this->assertSame($trainer->id, (int) $response->json('rows.0.trainer_profile_id'));
        $this->assertStringContainsString('абонементов по дате оплаты', (string) $response->json('draft_subtitle'));

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 1999, 'month' => 5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year']);

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 13]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);

        $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'month' => 5,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year']);
    }

    public function test_get_data_without_ajax_header_still_returns_json_not_empty_200(): void
    {
        $this->makeTrainerProfile('GET data без AJAX sales');

        $response = $this->from(route('schedule.trainer-salary'))
            ->get(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response->assertOk();
        $response->assertJsonPath('scheme_code', 'sales');
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertIsString($response->json('table_html'));
        $this->assertSame('', (string) $response->json('month_settings_html'));
    }

    public function test_sales_snapshot_page_is_readonly_and_shows_sales_columns(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Лист sales');
        $trainer->update(['default_base_salary_cents' => 0]);
        UserPrice::factory()->forUserAndMonth((int) $student->id, '2026-05-01', 500000, true, (int) $team->id)->create();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 10,
        ])->assertOk();
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

        $this->assertStringContainsString('Лист sales', $html);
        $this->assertStringContainsString('Оплаченные', $html);
        $this->assertStringContainsString('Абонементы', $html);
        $this->assertStringContainsString('База', $html);
        $this->assertStringContainsString('% от', $html);
        $this->assertStringContainsString('trainer-salary-table--readonly', $html);
        $this->assertStringNotContainsString('trainer-salary-input', $html);
        $this->assertStringNotContainsString('trainer-salary-form-one-btn', $html);
        $this->assertStringNotContainsString('data-field="sales_percent"', $html);
    }

    public function test_draft_edit_after_snapshot_does_not_change_frozen_sheet(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Заморозка sales');
        $trainer->update(['default_base_salary_cents' => 0]);
        UserPrice::factory()->forUserAndMonth((int) $student->id, '2026-05-01', 1000000, true, (int) $team->id)->create();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 10,
        ])->assertOk();
        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $snapshotId = (int) TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->max('id');

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 50,
        ])->assertOk();

        $this->assertDatabaseHas('trainer_salary_sales_snapshot_trainers', [
            'trainer_salary_snapshot_id' => $snapshotId,
            'sales_percent' => 10,
            'commission_cents' => 100000,
        ]);

        $html = (string) $this->get(route('schedule.trainer-salary-sheets.snapshot.show', $snapshotId))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('<span class="trainer-salary-count">10</span>', $html);
        $this->assertStringNotContainsString('<span class="trainer-salary-count">50</span>', $html);

        $draftRow = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertSame(50, $draftRow['sales_percent']);
        $this->assertSame('5000.00', $draftRow['commission']);
    }

    public function test_sales_percent_does_not_appear_on_trainer_card(): void
    {
        $this->grantPermission('trainers.view');
        $this->makeTrainerProfile('Карточка без %');

        $html = (string) $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="sales_percent"', $html);
        $this->assertStringNotContainsString('data-field="sales_percent"', $html);
        $this->assertStringNotContainsString('процент от продаж', mb_strtolower($html));
    }

    public function test_sales_percent_does_not_leak_to_another_partner(): void
    {
        $trainer = $this->makeTrainerProfile('Школа A sales');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 33,
        ])->assertOk();

        foreach ([
            'schedule.trainerSalary.view',
            'schedule.trainerSalary.manage',
            'schedule.trainerSalary.scheme.sales',
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
            ->assertJsonPath('scheme_code', 'sales');
        $foreignRows = collect($foreign->json('rows'));
        $this->assertNull($foreignRows->firstWhere('trainer_profile_id', $trainer->id));
        $this->assertStringNotContainsString('value="33"', (string) $foreign->json('table_html'));

        $this->assertSame(1, TrainerSalarySalesDraftTrainer::query()
            ->where('sales_percent', 33)
            ->count());
    }

    private function salesPercentInputHtml(string $html): string
    {
        $this->assertTrue(
            (bool) preg_match('/<input[^>]*data-field="sales_percent"[^>]*>/s', $html, $match),
            'В разметке нет поля sales_percent'
        );

        return $match[0];
    }
}
