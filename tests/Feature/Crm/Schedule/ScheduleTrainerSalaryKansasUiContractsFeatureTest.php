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
        $this->assertStringNotContainsString('data-field="premium_increment"', $html);

        $settingsHtml = (string) $response->json('month_settings_html');
        $this->assertStringContainsString('value="100"', $settingsHtml);
        $this->assertStringNotContainsString('value="100.00"', $settingsHtml);
        $this->assertStringContainsString('title="100.00"', $settingsHtml);
        $this->assertStringContainsString('data-field="premium_increment"', $settingsHtml);
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
        $this->assertStringContainsString('title="2.0"', $html);
        $this->assertStringContainsString('aria-label="2.0">2</span>', $html);
        $this->assertStringNotContainsString('data-field="base_avg_students"', $html);

        $settingsHtml = (string) $response->json('month_settings_html');
        $this->assertStringContainsString('value="2"', $settingsHtml);
        $this->assertStringContainsString('title="2.0"', $settingsHtml);
        $this->assertStringNotContainsString('value="2.0"', $settingsHtml);
        $this->assertStringContainsString('data-field="base_avg_students"', $settingsHtml);
        $this->assertStringContainsString('data-team-id="'.$team->id.'"', $settingsHtml);

        $rows = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        );
        $this->assertSame('2.0', $rows->firstWhere('trainer_profile_id', $trainerA->id)['groups'][0]['base_avg_students']);
        $this->assertSame('2.0', $rows->firstWhere('trainer_profile_id', $trainerB->id)['groups'][0]['base_avg_students']);
    }

    public function test_kansas_form_one_and_form_all_also_reload_full_table(): void
    {
        $trainer = $this->makeTrainerProfile('Слепок reload');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа reload']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-08');
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
        $this->assertStringContainsString('value="0"', $html);
        $this->assertStringNotContainsString('value="0.00"', $html);
        $this->assertStringContainsString('Настройки месяца', $html);
        $this->assertStringContainsString('Настройки базовых значений за', $html);
        $this->assertStringContainsString('Нет тренеров с тренировками в этом месяце', $html);
        $this->assertStringNotContainsString('Дефолт оклад', $html);
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
        $this->assertStringContainsString('value="0"', (string) $june->json('month_settings_html'));
        $this->assertStringNotContainsString('value="0.00"', (string) $june->json('month_settings_html'));
        $this->assertStringNotContainsString('value="250"', (string) $june->json('month_settings_html'));
        $this->assertStringNotContainsString('data-field="premium_increment"', (string) $june->json('table_html'));

        $mayAgain = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $this->assertSame('250.00', $mayAgain->json('draft_view_data.premium_increment'));
        $this->assertStringContainsString('value="250"', (string) $mayAgain->json('month_settings_html'));
        $this->assertStringContainsString('title="250.00"', (string) $mayAgain->json('month_settings_html'));

        $pageJune = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('value="2026-06"', $pageJune);
        $this->assertStringContainsString('Настройки базовых значений за', $pageJune);
        $this->assertStringContainsString('title="0.00"', $pageJune);
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

        $this->assertStringNotContainsString('data-field="premium_increment"', $html);
        $this->assertStringNotContainsString('data-field="base_avg_students"', $html);
        $this->assertStringNotContainsString('data-field="rate_per_training"', $html);
        $this->assertStringNotContainsString('data-field="base_premium"', $html);
        $this->assertStringNotContainsString('data-error-for="rate_per_training"', $html);
        $this->assertStringNotContainsString('data-error-for="base_premium"', $html);
        $this->assertStringContainsString('data-team-id="'.$team->id.'"', $html);
        $this->assertStringContainsString('trainer-salary-form-one-btn', $html);
        $this->assertStringContainsString('>Расчет</', $html);

        $settingsHtml = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->json('month_settings_html');
        $this->assertStringContainsString('data-field="premium_increment"', $settingsHtml);
        $this->assertStringContainsString('data-field="base_avg_students"', $settingsHtml);
        $this->assertStringContainsString('data-error-for="premium_increment"', $settingsHtml);
        $this->assertStringContainsString('data-error-for="base_avg_students"', $settingsHtml);
        $this->assertStringContainsString('data-error-for="team_id"', $settingsHtml);
        $this->assertStringContainsString('data-save-trainer-id="'.$trainer->id.'"', $settingsHtml);
        $this->assertStringContainsString('data-team-id="'.$team->id.'"', $settingsHtml);

        $page = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="trainer-salary-form-all-btn"', $page);
        $this->assertStringContainsString('Настройки месяца', $page);
        $this->assertStringContainsString('id="trainerSalaryKansasMonthSettingsModal"', $page);
        $this->assertStringContainsString('schedule-modal-content cell-edit-modal', $page);
        $this->assertStringContainsString('cell-edit-modal__footer', $page);
        $this->assertStringContainsString('aria-label="Закрыть"', $page);
        $this->assertStringContainsString('cell-edit-section__label', $page);
        $this->assertStringContainsString('/js/trainer-salary.js', $page);
        $this->assertStringNotContainsString('resources/js/trainer-salary.js', $page);
    }

    public function test_group_row_shows_type_money_integer_averages_and_fraction_hover(): void
    {
        $trainer = $this->makeTrainerProfile('Целые средние');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа целые']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');
        $this->setTrainerTypeRates($trainer, 400, 50);

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => '16.5',
        ])->assertOk();

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->json('table_html');

        $this->assertStringContainsString('trainer-salary-kansas-type-money', $html);
        $this->assertStringContainsString('trainer-salary-kansas-head-bar', $html);
        $this->assertStringNotContainsString('colspan="8"', $html);
        $this->assertStringNotContainsString('fa-info-circle', $html);
        $this->assertStringContainsString('data-kids-tooltip-hint', $html);
        $this->assertStringContainsString('title="16.5"', $html);
        $this->assertStringContainsString('title="1.0"', $html);
        $this->assertStringContainsString('aria-label="1.0">1</span>', $html);
        $this->assertStringNotContainsString('aria-label="1.0">1.0</span>', $html);
        $this->assertStringNotContainsString('aria-label="16.5">16.5</span>', $html);
        $this->assertStringContainsString('aria-label="16.5">16</span>', $html);
        $this->assertStringNotContainsString('value="16"', $html);
        $this->assertStringNotContainsString('value="16.5"', $html);
        $this->assertStringContainsString('400', $html);
        $this->assertStringContainsString('50', $html);

        $headPos = mb_strpos($html, 'trainer-salary-kansas-head');
        $groupPos = mb_strpos($html, 'trainer-salary-kansas-group');
        $typeMoneyPos = mb_strpos($html, 'trainer-salary-kansas-type-money');
        $this->assertNotFalse($headPos);
        $this->assertNotFalse($groupPos);
        $this->assertNotFalse($typeMoneyPos);
        $this->assertTrue($typeMoneyPos > $groupPos, 'Оклад и базовая премия должны быть в строке группы, не тренера');

        $footPos = mb_strpos($html, 'trainer-salary-kansas-foot');
        $this->assertNotFalse($footPos);
        $this->assertTrue($footPos > $groupPos, 'Итого тренера должно быть под строками групп');
        $this->assertStringContainsString('trainer-salary-kansas-foot-caption', $html);
        $this->assertStringContainsString('Итого:', $html);
        $this->assertStringContainsString('(Главный тренер)', $html);
        $this->assertStringContainsString('trainer-salary-kansas-head-type', $html);
        $this->assertStringNotContainsString('trainer-salary-kansas-foot-slash', $html);
        $btnPos = mb_strpos($html, 'trainer-salary-form-one-btn');
        $this->assertNotFalse($btnPos);
        $this->assertTrue($btnPos > $footPos, 'Кнопка «Расчет» должна быть в строке итога, не в шапке блока');

        $settingsHtml = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->json('month_settings_html');
        $this->assertStringContainsString('value="16"', $settingsHtml);
        $this->assertStringContainsString('title="16.5"', $settingsHtml);
        $this->assertStringNotContainsString('value="16.5"', $settingsHtml);
        $this->assertStringContainsString('data-field="base_avg_students"', $settingsHtml);
    }

    public function test_empty_trainer_is_not_rendered_in_kansas_table(): void
    {
        $empty = $this->makeTrainerProfile('Пустой тренер');
        $busy = $this->makeTrainerProfile('С нагрузкой');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа нагрузка']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $busy->id, '2026-05-07');

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->json('table_html');

        $this->assertStringNotContainsString('Пустой тренер', $html);
        $this->assertStringContainsString('С нагрузкой', $html);
        $this->assertStringNotContainsString('Нет тренировок с визитами', $html);
        $this->assertStringContainsString('trainer-salary-kansas-foot', $html);

        $rows = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->json('rows')
        );
        $this->assertNotNull($rows->firstWhere('trainer_profile_id', $empty->id));
        $this->assertSame([], $rows->firstWhere('trainer_profile_id', $empty->id)['groups']);
    }

    public function test_month_settings_list_all_active_teams_including_without_visits(): void
    {
        $trainer = $this->makeTrainerProfile('Настройки групп');
        $withVisits = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Активная с визитами',
            'is_enabled' => true,
        ]);
        $withoutVisits = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Активная без визитов',
            'is_enabled' => true,
        ]);
        $disabled = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Выключенная группа',
            'is_enabled' => false,
        ]);
        $student = $this->makeStudent($withVisits->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');

        $data = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();

        $html = (string) $data->json('table_html');
        $this->assertStringContainsString('Активная с визитами', $html);
        $this->assertStringNotContainsString('Активная без визитов', $html);
        $this->assertStringNotContainsString('Выключенная группа', $html);
        $this->assertStringNotContainsString('data-field="base_avg_students"', $html);
        $this->assertStringNotContainsString('data-field="premium_increment"', $html);

        $settingsHtml = (string) $data->json('month_settings_html');
        $this->assertStringContainsString('Активная с визитами', $settingsHtml);
        $this->assertStringContainsString('Активная без визитов', $settingsHtml);
        $this->assertStringNotContainsString('Выключенная группа', $settingsHtml);
        $this->assertStringContainsString('data-team-id="'.$withVisits->id.'"', $settingsHtml);
        $this->assertStringContainsString('data-team-id="'.$withoutVisits->id.'"', $settingsHtml);
        $this->assertStringNotContainsString('data-team-id="'.$disabled->id.'"', $settingsHtml);

        $groups = collect($data->json('draft_view_data.month_groups'));
        $this->assertNotNull($groups->firstWhere('team_id', $withVisits->id));
        $this->assertNotNull($groups->firstWhere('team_id', $withoutVisits->id));
        $this->assertNull($groups->firstWhere('team_id', $disabled->id));
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
        $this->assertStringContainsString('Базовая надбавка к премии', $html);
        $this->assertSame('', (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->json('month_settings_html'));

        $page = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('id="trainer-salary-form-all-btn"', $page);
        $this->assertStringNotContainsString('Настройки месяца', $page);
        $this->assertStringNotContainsString('id="trainerSalaryKansasMonthSettingsModal"', $page);
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
        $this->assertStringNotContainsString('Базовая надбавка к премии', $html);
        $this->assertStringNotContainsString('data-field="premium_increment"', $html);
        $this->assertStringNotContainsString('data-field="base_avg_students"', $html);
        $this->assertStringNotContainsString('Настройки месяца', $html);
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
        $this->assertStringNotContainsString('как в отчёте «Нагрузка тренеров»', $html);
        $settingsHtml = (string) $response->json('month_settings_html');
        $this->assertStringContainsString('Базовая надбавка к премии', $settingsHtml);
        $this->assertStringContainsString('data-field="premium_increment"', $settingsHtml);

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
        $this->assertStringContainsString('value="777"', (string) $response->json('month_settings_html'));
        $this->assertStringContainsString('title="777.00"', (string) $response->json('month_settings_html'));
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

        $tooPrecise = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => '16.55',
        ]);
        $tooPrecise->assertStatus(422)
            ->assertJsonValidationErrors(['base_avg_students']);
        $this->assertIsArray($tooPrecise->json('errors.base_avg_students'));
        $this->assertNotSame('', (string) $tooPrecise->json('errors.base_avg_students.0'));
        $this->assertSame(
            '0.0',
            collect($this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows'))
                ->firstWhere('trainer_profile_id', $trainer->id)['groups'][0]['base_avg_students']
        );
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
        $this->assertStringContainsString('(Главный тренер)', $html);
        $this->assertStringContainsString('Итого:', $html);
        $this->assertStringContainsString('trainer-salary-table--readonly', $html);
        $this->assertStringContainsString('trainer-salary-table--kansas', $html);
        $this->assertStringNotContainsString('trainer-salary-input', $html);
        $this->assertStringNotContainsString('trainer-salary-form-one-btn', $html);
    }

    public function test_kansas_page_hides_draft_subtitle_while_classic_keeps_workload_hint(): void
    {
        $this->makeTrainerProfile('Без подзаголовка');

        $page = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('trainer-salary-subtitle', $page);
        $this->assertStringNotContainsString('Черновик за календарный месяц', $page);
        $this->assertStringNotContainsString(
            'Тренировка — занятие (слот + дата) с хотя бы одним «Посетил»',
            $page
        );
        $this->assertStringNotContainsString('Средние до десятых входят в расчёт.', $page);

        $data = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $this->assertSame('', (string) $data->json('draft_subtitle'));
        $this->assertStringNotContainsString('Черновик за календарный месяц', (string) $data->json('table_html'));

        $this->useClassicSchemeOnly();

        $classicPage = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('trainer-salary-subtitle', $classicPage);
        $this->assertStringContainsString('как в отчёте «Нагрузка тренеров»', $classicPage);

        $classicData = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'classic');
        $this->assertStringContainsString('как в отчёте «Нагрузка тренеров»', (string) $classicData->json('draft_subtitle'));
    }

    public function test_calculation_stays_in_footer_left_of_total_and_header_shows_type_only(): void
    {
        $trainer = $this->makeTrainerProfile('Подвал расчет');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа подвал']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->json('table_html');

        $headStart = mb_strpos($html, 'trainer-salary-kansas-head');
        $groupStart = mb_strpos($html, 'trainer-salary-kansas-group');
        $footStart = mb_strpos($html, 'trainer-salary-kansas-foot');
        $this->assertNotFalse($headStart);
        $this->assertNotFalse($groupStart);
        $this->assertNotFalse($footStart);

        $head = mb_substr($html, $headStart, $groupStart - $headStart);
        $foot = mb_substr($html, $footStart);

        $this->assertStringContainsString('Подвал расчет', $head);
        $this->assertStringContainsString('(Главный тренер)', $head);
        $this->assertStringContainsString('trainer-salary-kansas-head-type', $head);
        $this->assertStringNotContainsString('trainer-salary-form-one-btn', $head);
        $this->assertStringNotContainsString('>Расчет</', $head);
        $this->assertStringNotContainsString('Итого:', $head);

        $this->assertStringContainsString('trainer-salary-kansas-foot-bar', $foot);
        $btnPos = mb_strpos($foot, 'trainer-salary-form-one-btn');
        $captionPos = mb_strpos($foot, 'trainer-salary-kansas-foot-caption');
        $totalPos = mb_strpos($foot, 'trainer-salary-value--total');
        $this->assertNotFalse($btnPos);
        $this->assertNotFalse($captionPos);
        $this->assertNotFalse($totalPos);
        $this->assertTrue($btnPos < $captionPos, '«Расчет» должен стоять слева от подписи «Итого:»');
        $this->assertTrue($captionPos < $totalPos, 'Подпись «Итого:» должна стоять слева от суммы');
        $this->assertStringContainsString('Итого:', $foot);
        $this->assertStringContainsString('>Расчет</', $foot);
    }

    public function test_month_settings_title_follows_selected_month_and_modal_stays_standard_width(): void
    {
        $this->makeTrainerProfile('Заголовок месяца');

        $page = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="trainerSalaryKansasMonthSettingsModal"', $page);
        $this->assertStringContainsString('Настройки базовых значений за Май 2026', $page);
        $this->assertStringContainsString('data-modal-title="Настройки базовых значений за Май 2026"', $page);
        $this->assertStringContainsString('modal-dialog modal-dialog-scrollable', $page);
        $this->assertStringNotContainsString('modal-dialog modal-xl', $page);
        $this->assertStringNotContainsString('modal-fullscreen', $page);
        $this->assertStringNotContainsString('modal-dialog-centered modal-fullscreen', $page);

        $may = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $this->assertSame(
            'Настройки базовых значений за Май 2026',
            $may->json('draft_view_data.month_settings_title')
        );
        $this->assertStringContainsString(
            'data-modal-title="Настройки базовых значений за Май 2026"',
            (string) $may->json('month_settings_html')
        );

        $june = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 6]))
            ->assertOk();
        $this->assertSame(
            'Настройки базовых значений за Июнь 2026',
            $june->json('draft_view_data.month_settings_title')
        );
        $this->assertStringContainsString(
            'data-modal-title="Настройки базовых значений за Июнь 2026"',
            (string) $june->json('month_settings_html')
        );
        $this->assertStringNotContainsString('за Май 2026', (string) $june->json('month_settings_html'));
    }

    public function test_get_data_returns_kansas_payload_and_rejects_invalid_period_per_field(): void
    {
        $trainer = $this->makeTrainerProfile('JSON канзас');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа JSON']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');

        $response = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas')
            ->assertJsonPath('can_manage', true)
            ->assertJsonPath('draft_subtitle', '')
            ->assertJsonStructure([
                'year',
                'month',
                'month_label',
                'date_from',
                'date_to',
                'scheme_code',
                'draft_subtitle',
                'draft_view_data' => [
                    'premium_increment',
                    'premium_increment_int',
                    'month_groups',
                    'save_trainer_id',
                    'month_settings_title',
                ],
                'table_view',
                'can_manage',
                'table_html',
                'month_settings_html',
                'rows' => [
                    [
                        'trainer_profile_id',
                        'trainer_name',
                        'trainer_type_name',
                        'groups',
                    ],
                ],
            ]);

        $this->assertNotSame('', trim((string) $response->json('table_html')));
        $this->assertNotSame('', trim((string) $response->json('month_settings_html')));
        $this->assertSame($trainer->id, (int) $response->json('draft_view_data.save_trainer_id'));
        $this->assertSame('Главный тренер', $response->json('rows.0.trainer_type_name'));

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
        $this->makeTrainerProfile('GET data без AJAX');

        $response = $this->from(route('schedule.trainer-salary'))
            ->get(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response->assertOk();
        $response->assertJsonPath('scheme_code', 'kansas');
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertIsString($response->json('table_html'));
        $this->assertIsString($response->json('month_settings_html'));
    }

    public function test_ajax_month_settings_patch_returns_json_and_keeps_modal_html_in_payload(): void
    {
        $trainer = $this->makeTrainerProfile('PATCH из модалки');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа модалка']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $xResponse = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => '100.50',
        ]);
        $xResponse->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён')
            ->assertJsonPath('reload_table', true);
        $this->assertIsString($xResponse->json('table_html'));
        $this->assertIsString($xResponse->json('month_settings_html'));
        $this->assertStringContainsString('value="100"', (string) $xResponse->json('month_settings_html'));
        $this->assertStringContainsString('title="100.50"', (string) $xResponse->json('month_settings_html'));
        $this->assertStringNotContainsString('data-field="premium_increment"', (string) $xResponse->json('table_html'));

        $baseResponse = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => '16.5',
        ]);
        $baseResponse->assertOk()->assertJsonPath('reload_table', true);
        $this->assertStringContainsString('value="16"', (string) $baseResponse->json('month_settings_html'));
        $this->assertStringContainsString('title="16.5"', (string) $baseResponse->json('month_settings_html'));
        $this->assertStringContainsString('data-team-id="'.$team->id.'"', (string) $baseResponse->json('month_settings_html'));
        $this->assertStringNotContainsString('data-field="base_avg_students"', (string) $baseResponse->json('table_html'));
    }

    public function test_baseline_without_visits_saves_but_does_not_create_table_row(): void
    {
        $trainer = $this->makeTrainerProfile('База без визитов');
        $withVisits = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа с визитами',
            'is_enabled' => true,
        ]);
        $withoutVisits = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа без визитов',
            'is_enabled' => true,
        ]);
        $student = $this->makeStudent($withVisits->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $response = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $withoutVisits->id,
            'base_avg_students' => 12,
        ]);
        $response->assertOk()->assertJsonPath('reload_table', true);

        $tableHtml = (string) $response->json('table_html');
        $this->assertStringContainsString('Группа с визитами', $tableHtml);
        $this->assertStringNotContainsString('Группа без визитов', $tableHtml);

        $settingsHtml = (string) $response->json('month_settings_html');
        $this->assertStringContainsString('Группа без визитов', $settingsHtml);
        $this->assertStringContainsString('data-team-id="'.$withoutVisits->id.'"', $settingsHtml);
        $this->assertStringContainsString('value="12"', $settingsHtml);

        $this->assertDatabaseHas('trainer_salary_kansas_group_baselines', [
            'team_id' => $withoutVisits->id,
            'base_avg_students_tenths' => 120,
        ]);
    }

    public function test_month_settings_inputs_are_disabled_when_partner_has_no_trainers(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа без тренеров',
            'is_enabled' => true,
        ]);

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('draft_view_data.save_trainer_id', 0)
            ->json('month_settings_html');

        $this->assertStringContainsString('Нет активных тренеров — сохранение недоступно.', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('Группа без тренеров', $html);
        $this->assertStringNotContainsString('data-save-trainer-id="', $html);
    }

    public function test_non_ajax_form_all_creates_snapshots_and_returns_json_not_empty_200(): void
    {
        $trainer = $this->makeTrainerProfile('Non-AJAX всех');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа всех']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-08');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $response = $this->from(route('schedule.trainer-salary'))
            ->post(route('schedule.trainer-salary.snapshots.form-all'), [
                'year' => 2026,
                'month' => 5,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode(), 'Сформировать всех отвечает JSON, не redirect');
        $response->assertOk();
        $response->assertJsonPath('reload_table', true);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertGreaterThan(0, (int) $response->json('snapshots_count'));
        $this->assertDatabaseHas('trainer_salary_snapshots', [
            'trainer_profile_id' => $trainer->id,
            'scheme_code' => 'kansas',
        ]);
    }

    public function test_viewer_cannot_save_month_settings_fields(): void
    {
        $this->revokePermission('schedule.trainerSalary.manage');
        $trainer = $this->makeTrainerProfile('Зритель настроек');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа зритель']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('can_manage', false)
            ->assertJsonPath('month_settings_html', '');

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 50,
        ])->assertForbidden();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 10,
        ])->assertForbidden();
    }
}
