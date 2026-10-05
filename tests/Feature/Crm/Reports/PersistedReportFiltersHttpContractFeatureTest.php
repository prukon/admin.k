<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контракт сохранённых фильтров отчётов: HTTP, права, разметка формы и правила по умолчанию.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PersistedReportFiltersHttpContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_guest_web_post_redirects_and_does_not_save_filters(): void
    {
        auth()->logout();

        $this->post(route('reports.payments.filters.save'), [
            'payment_month' => '2026-03',
        ])->assertStatus(302);

        $this->postJson(route('reports.ltv.filters.save'), [
            'payment_provider' => 'tbank',
        ])->assertStatus(401);

        $this->assertDatabaseMissing('user_table_settings', [
            'table_key' => 'reports_payments',
        ]);
        $this->assertDatabaseMissing('user_table_settings', [
            'table_key' => 'reports_ltv',
        ]);
    }

    public function test_manager_without_report_permission_gets_403_on_page_and_save(): void
    {
        $noReports = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($noReports);

        foreach ([
            'payments',
            'reports.payments.monthly',
            'reports.ltv',
            'debts',
        ] as $page) {
            $this->get(route($page))->assertForbidden();
        }

        foreach ([
            'reports.payments.filters.save',
            'reports.payments.monthly.filters.save',
            'reports.ltv.filters.save',
            'reports.debts.filters.save',
        ] as $save) {
            $this->postJson(route($save), ['status' => 'inactive'])->assertForbidden();
        }

        $noTeams = $this->createUserWithoutPermission('reports.ltv.teams.view', $this->partner);
        $this->actingAs($noTeams);
        $this->get(route('reports.ltv.teams'))->assertForbidden();
        $this->postJson(route('reports.ltv.teams.filters.save'), [
            'payment_provider' => 'tbank',
        ])->assertForbidden();

        $noLocations = $this->createUserWithoutPermission('reports.ltv.locations.view', $this->partner);
        $this->actingAs($noLocations);
        $this->get(route('reports.ltv.locations'))->assertForbidden();
        $this->postJson(route('reports.ltv.locations.filters.save'), [
            'payment_provider' => 'tbank',
        ])->assertForbidden();
    }

    public function test_admin_ajax_save_returns_json_and_plain_post_redirects_to_the_report(): void
    {
        $cases = [
            ['reports.payments.filters.save', 'payments', 'reports_payments', ['payment_month' => '2026-03', 'status' => 'inactive']],
            ['reports.payments.monthly.filters.save', 'reports.payments.monthly', 'reports_payments_monthly', ['payment_provider' => 'tbank', 'mode' => 'operation']],
            ['reports.ltv.filters.save', 'reports.ltv', 'reports_ltv', ['operation_date_from' => '2026-02-01']],
            ['reports.debts.filters.save', 'debts', 'reports_debts', ['debt_month' => '2026-06']],
            ['reports.ltv.teams.filters.save', 'reports.ltv.teams', 'reports_ltv_teams', ['payment_month' => '2026-07', 'mode' => 'subscription']],
            ['reports.ltv.locations.filters.save', 'reports.ltv.locations', 'reports_ltv_locations', ['payment_provider' => 'robokassa', 'mode' => 'operation']],
        ];

        foreach ($cases as [$save, $page, $tableKey, $payload]) {
            $this->postJson(route($save), $payload, [
                'X-Requested-With' => 'XMLHttpRequest',
            ])->assertOk()
                ->assertJson(['success' => true])
                ->assertJsonStructure(['success']);

            $row = $this->filtersRow($tableKey);
            foreach ($payload as $key => $value) {
                $this->assertSame($value, $row->filters[$key], $tableKey.' '.$key);
            }

            UserTableSetting::query()->where('user_id', $this->user->id)->where('table_key', $tableKey)->delete();

            $this->from(route($page))
                ->post(route($save), $payload, ['HTTP_ACCEPT' => 'text/html'])
                ->assertStatus(302)
                ->assertRedirect(route($page));

            $this->assertNotNull($this->filtersRow($tableKey));
        }
    }

    public function test_plain_post_with_invalid_field_redirects_back_and_ajax_returns_422(): void
    {
        $this->from(route('payments'))
            ->post(route('reports.payments.filters.save'), [
                'payment_provider' => 'paypal',
                'operation_date_from' => '2026-05-10',
                'operation_date_to' => '2026-05-01',
            ], ['HTTP_ACCEPT' => 'text/html'])
            ->assertStatus(302)
            ->assertRedirect(route('payments'))
            ->assertSessionHasErrors(['payment_provider', 'operation_date_to']);

        $this->assertDatabaseMissing('user_table_settings', [
            'user_id' => $this->user->id,
            'table_key' => 'reports_payments',
        ]);

        $this->postJson(route('reports.payments.filters.save'), [
            'payment_provider' => 'paypal',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'payment_provider' => 'Выберите провайдера: T-Bank или Robokassa.',
            ]);

        $this->postJson(route('reports.debts.filters.save'), [
            'debt_month' => '2026-13',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'debt_month' => 'Укажите месяц задолженности в формате ГГГГ-ММ.',
            ]);

        $this->postJson(route('reports.ltv.teams.filters.save'), [
            'payment_month' => 'март',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['payment_month']);
    }

    public function test_filters_url_rejects_get_put_patch_and_delete(): void
    {
        foreach ([
            'reports.payments.filters.save',
            'reports.payments.monthly.filters.save',
            'reports.ltv.filters.save',
            'reports.debts.filters.save',
            'reports.ltv.teams.filters.save',
            'reports.ltv.locations.filters.save',
        ] as $name) {
            foreach (['get', 'put', 'patch', 'delete'] as $method) {
                $response = $this->{$method}(route($name));
                $this->assertNotContains($response->status(), [200, 500], $name.' '.$method);
            }
        }
    }

    public function test_array_query_does_not_crash_and_drops_the_bad_value(): void
    {
        $this->postJson(route('reports.payments.filters.save'), [
            'payment_month' => '2026-04',
            'status' => 'inactive',
        ])->assertOk();

        $this->get(route('payments', ['user_name' => ['Иван']]))->assertOk();

        $payments = $this->filtersRow('reports_payments');
        $this->assertSame('', $payments->filters['user_name']);
        $this->assertSame('', $payments->filters['payment_month']);
        $this->assertSame('active', $payments->filters['status']);

        $this->postJson(route('reports.ltv.filters.save'), [
            'payment_month' => '2026-04',
        ])->assertOk();

        $this->get(route('reports.ltv', [
            'filter_team_id' => [['id' => '1']],
        ]))->assertOk();

        $ltv = $this->filtersRow('reports_ltv');
        $this->assertSame([], $ltv->filters['filter_team_id']);
        $this->assertSame('', $ltv->filters['payment_month']);

        $this->get(route('reports.payments.monthly', [
            'payment_month' => ['2026-03'],
        ]))->assertOk();
        $this->assertSame('', $this->filtersRow('reports_payments_monthly')->filters['payment_month']);
    }

    public function test_first_visit_selects_active_students_and_keeps_the_panel_closed(): void
    {
        $html = $this->get(route('payments'))->assertOk()->getContent();

        $this->assertFiltersPanelOpen($html, 'paymentsReportFiltersCollapse', false);
        $status = $this->selectHtml($html, 'pay-filter-user-status');
        $this->assertStringContainsString('value="active" selected', $status);
        $this->assertStringNotContainsString('value="inactive" selected', $status);

        $ltv = $this->get(route('reports.ltv'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($ltv, 'ltvReportFiltersCollapse', false);
        $this->assertStringContainsString(
            'value="active" selected',
            $this->selectHtml($ltv, 'pay-ltv-filter-user-status')
        );
    }

    public function test_saved_empty_lists_do_not_open_the_filters_panel(): void
    {
        foreach ([
            ['reports.ltv.filters.save', 'reports.ltv', 'ltvReportFiltersCollapse', 'pay-ltv-filter-user-status'],
            ['reports.debts.filters.save', 'debts', 'debtReportFiltersCollapse', 'pay-debt-filter-user-status'],
            ['reports.ltv.teams.filters.save', 'reports.ltv.teams', 'ltvTeamsReportFiltersCollapse', 'pay-ltv-teams-filter-user-status'],
            ['reports.ltv.locations.filters.save', 'reports.ltv.locations', 'ltvLocationsReportFiltersCollapse', 'pay-ltv-locations-filter-user-status'],
            ['reports.payments.filters.save', 'payments', 'paymentsReportFiltersCollapse', 'pay-filter-user-status'],
        ] as [$save, $page, $collapseId, $statusId]) {
            $this->postJson(route($save), ['reset' => 1])->assertOk();
            $html = $this->get(route($page))->assertOk()->getContent();
            $this->assertFiltersPanelOpen($html, $collapseId, false);
            $this->assertStringContainsString('value="active" selected', $this->selectHtml($html, $statusId));
        }
    }

    public function test_saved_inactive_status_is_selected_and_opens_the_panel(): void
    {
        $this->postJson(route('reports.payments.filters.save'), [
            'status' => 'inactive',
        ])->assertOk();

        $html = $this->get(route('payments'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($html, 'paymentsReportFiltersCollapse', true);
        $status = $this->selectHtml($html, 'pay-filter-user-status');
        $this->assertStringContainsString('value="inactive" selected', $status);
        $this->assertStringNotContainsString('value="active" selected', $status);
    }

    public function test_empty_status_means_all_students_and_does_not_force_active(): void
    {
        $this->postJson(route('reports.debts.filters.save'), [
            'status' => 'inactive',
        ])->assertOk();

        $this->get(route('debts').'?status=')->assertOk();

        $row = $this->filtersRow('reports_debts');
        $this->assertSame('', $row->filters['status']);

        $html = $this->get(route('debts'))->assertOk()->getContent();
        $status = $this->selectHtml($html, 'pay-debt-filter-user-status');
        $this->assertStringNotContainsString('value="active" selected', $status);
        $this->assertStringNotContainsString('value="inactive" selected', $status);
        $this->assertFiltersPanelOpen($html, 'debtReportFiltersCollapse', true);
    }

    public function test_saved_team_student_and_location_are_selected_in_field_order(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'title' => 'Группа фильтра',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'name' => 'Петя',
            'lastname' => 'Фильтров',
        ]);
        $location = Location::factory()->forPartner($this->partner->id)->create([
            'name' => 'Зал фильтра',
        ]);

        $this->postJson(route('reports.payments.filters.save'), [
            'filter_user_id' => $student->id,
            'filter_team_id' => [$team->id],
            'filter_location_id' => ['none', $location->id],
            'payment_method' => 'sbp_qr',
            'email_newsletter' => '0',
        ])->assertOk();

        $html = $this->get(route('payments'))->assertOk()->getContent();
        $form = $this->formHtml($html, 'payments-report-filters');

        $this->assertLessThan(strpos($form, 'Группа'), strpos($form, 'Ученик'));
        $this->assertLessThan(strpos($form, 'Оплаченный месяц'), strpos($form, 'Группа'));
        $this->assertLessThan(strpos($form, 'Провайдер'), strpos($form, 'Оплаченный месяц'));
        $this->assertLessThan(strpos($form, 'Способ оплаты'), strpos($form, 'Провайдер'));
        $this->assertLessThan(strpos($form, 'Email рассылка'), strpos($form, 'Способ оплаты'));
        $this->assertLessThan(strpos($form, 'Активность ученика'), strpos($form, 'Email рассылка'));

        $this->assertStringContainsString('value="'.$student->id.'" selected', $form);
        $this->assertMatchesRegularExpression(
            '/<option value="'.$team->id.'" selected>Группа фильтра<\/option>/',
            $form
        );
        $this->assertMatchesRegularExpression(
            '/<option value="none" selected>Без объекта<\/option>/',
            $form
        );
        $this->assertMatchesRegularExpression(
            '/<option value="'.$location->id.'" selected>\s*Зал фильтра\s*<\/option>/',
            $form
        );
        $this->assertStringContainsString('value="sbp_qr" selected', $form);
        $this->assertStringContainsString('value="0" selected', $form);
        $this->assertFiltersPanelOpen($html, 'paymentsReportFiltersCollapse', true);
    }

    public function test_hidden_permission_fields_are_not_shown_and_smuggled_values_are_dropped(): void
    {
        $html = $this->get(route('payments'))->assertOk()->getContent();
        $this->assertStringContainsString('id="pay-filter-trainer"', $html);
        $this->assertStringContainsString('id="pay-filter-location"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<input\b[^>]*\bname="bank_commission_acquiring_min"/',
            $html
        );

        $this->forgetPermission('trainers.view');
        $this->forgetPermission('locations.view');

        $trainerHtml = $this->get(route('reports.ltv'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="pay-ltv-filter-trainer"', $trainerHtml);
        $this->assertStringNotContainsString('id="pay-ltv-filter-location"', $trainerHtml);
        $this->assertStringContainsString('id="pay-ltv-filter-team"', $trainerHtml);

        $this->postJson(route('reports.ltv.filters.save'), [
            'payment_provider' => 'tbank',
            'filter_trainer_profile_id' => [999],
            'filter_location_id' => [999],
        ])->assertOk();

        $ltv = $this->filtersRow('reports_ltv');
        $this->assertSame('tbank', $ltv->filters['payment_provider']);
        $this->assertSame([], $ltv->filters['filter_trainer_profile_id']);
        $this->assertSame([], $ltv->filters['filter_location_id']);

        $this->postJson(route('reports.payments.filters.save'), [
            'bank_commission_acquiring_min' => '10',
            'bank_commission_acquiring_max' => '1',
        ])->assertOk();

        $payments = $this->filtersRow('reports_payments');
        $this->assertSame('', $payments->filters['bank_commission_acquiring_min']);
        $this->assertSame('', $payments->filters['bank_commission_acquiring_max']);
    }

    public function test_commission_fields_validate_when_the_admin_can_see_them(): void
    {
        $this->grantPermission('reports.additional.value.view');

        $html = $this->get(route('payments'))->assertOk()->getContent();
        $form = $this->formHtml($html, 'payments-report-filters');
        $this->assertLessThan(
            strpos($form, 'name="bank_commission_acquiring_max"'),
            strpos($form, 'name="bank_commission_acquiring_min"')
        );
        $this->assertLessThan(
            strpos($form, 'Активность ученика'),
            strpos($form, 'Комиссия выплаты до')
        );

        $this->postJson(route('reports.payments.filters.save'), [
            'bank_commission_acquiring_min' => '10',
            'bank_commission_acquiring_max' => '2',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'bank_commission_acquiring_max' => 'Комиссия оплаты «до» не меньше «от».',
            ]);

        $this->postJson(route('reports.payments.filters.save'), [
            'bank_commission_payout_min' => '1.234',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'bank_commission_payout_min' => 'Комиссия выплаты «от»: не больше двух знаков после запятой.',
            ]);

        $this->postJson(route('reports.payments.filters.save'), [
            'bank_commission_acquiring_min' => '1.50',
            'bank_commission_acquiring_max' => '3.25',
        ])->assertOk();

        $shown = $this->get(route('payments'))->assertOk()->getContent();
        $this->assertStringContainsString('name="bank_commission_acquiring_min"', $shown);
        $this->assertStringContainsString('value="1.50"', $shown);
        $this->assertStringContainsString('value="3.25"', $shown);
    }

    public function test_foreign_student_and_disabled_team_return_field_errors(): void
    {
        $disabled = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => false,
        ]);

        $this->postJson(route('reports.payments.filters.save'), [
            'filter_user_id' => $this->foreignUser->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'filter_user_id' => 'Выберите ученика из списка.',
            ]);

        $this->postJson(route('reports.ltv.filters.save'), [
            'filter_team_id' => [$disabled->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'filter_team_id.0' => 'Выберите группу из списка.',
            ]);

        $foreignLocation = Location::factory()->forPartner($this->foreignPartner->id)->create();
        $this->postJson(route('reports.payments.monthly.filters.save'), [
            'filter_location_id' => $foreignLocation->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'filter_location_id' => 'Выберите объект из списка.',
            ]);
    }

    public function test_reset_replaces_the_snapshot_even_when_the_request_also_sends_a_month(): void
    {
        $this->postJson(route('reports.payments.filters.save'), [
            'payment_month' => '2026-04',
            'status' => '',
        ])->assertOk();

        $this->postJson(route('reports.payments.filters.save'), [
            'reset' => 1,
            'payment_month' => '2026-09',
            'status' => 'inactive',
        ])->assertOk()->assertJson(['success' => true]);

        $row = $this->filtersRow('reports_payments');
        $this->assertSame('', $row->filters['payment_month']);
        $this->assertSame('active', $row->filters['status']);
        $this->assertSame([], $row->filters['filter_team_id']);
    }

    public function test_legacy_name_on_ltv_overwrites_filters_and_is_not_stored(): void
    {
        $this->postJson(route('reports.ltv.filters.save'), [
            'payment_month' => '2026-04',
            'status' => 'inactive',
        ])->assertOk();

        $this->get(route('reports.ltv', ['user_name' => 'Иван']))->assertOk();

        $row = $this->filtersRow('reports_ltv');
        $this->assertArrayNotHasKey('user_name', $row->filters);
        $this->assertArrayNotHasKey('team_title', $row->filters);
        $this->assertSame('', $row->filters['payment_month']);
        $this->assertSame('active', $row->filters['status']);
    }

    public function test_period_query_does_not_replace_saved_filters_or_force_the_current_tab(): void
    {
        $this->postJson(route('reports.ltv.teams.filters.save'), [
            'payment_month' => '2026-07',
            'period' => 'all',
            'mode' => 'subscription',
        ])->assertOk();

        $stored = $this->filtersRow('reports_ltv_teams');
        $this->assertSame('2026-07', $stored->filters['payment_month']);
        $this->assertSame('subscription', $stored->filters['mode']);
        $this->assertArrayNotHasKey('period', $stored->filters);

        $html = $this->get(route('reports.ltv.teams'))->assertOk()->getContent();
        $this->assertStringContainsString('value="2026-07"', $html);
        $this->assertButtonActive($html, 'ltv-teams-period-btn-current', true);
        $this->assertButtonActive($html, 'ltv-teams-period-btn-all', false);
        $this->assertButtonActive($html, 'ltv-teams-group-mode-btn-subscription', true);
        $this->assertButtonActive($html, 'ltv-teams-group-mode-btn-operation', false);

        $withPeriod = $this->get(route('reports.ltv.teams', ['period' => 'all']))->assertOk()->getContent();
        $this->assertStringContainsString('value="2026-07"', $withPeriod);
        $this->assertButtonActive($withPeriod, 'ltv-teams-period-btn-all', true);
        $this->assertButtonActive($withPeriod, 'ltv-teams-period-btn-current', false);
        $this->assertSame('2026-07', $this->filtersRow('reports_ltv_teams')->filters['payment_month']);
        $this->assertSame('subscription', $this->filtersRow('reports_ltv_teams')->filters['mode']);

        $withMode = $this->get(route('reports.ltv.teams', ['mode' => 'operation']))->assertOk()->getContent();
        $this->assertButtonActive($withMode, 'ltv-teams-group-mode-btn-operation', true);
        $this->assertButtonActive($withMode, 'ltv-teams-group-mode-btn-subscription', false);
        $this->assertSame('subscription', $this->filtersRow('reports_ltv_teams')->filters['mode']);
        $this->assertSame('2026-07', $this->filtersRow('reports_ltv_teams')->filters['payment_month']);
    }

    public function test_monthly_mode_query_does_not_replace_saved_filters(): void
    {
        $this->postJson(route('reports.payments.monthly.filters.save'), [
            'payment_provider' => 'tbank',
            'mode' => 'operation',
        ])->assertOk();

        $stored = $this->filtersRow('reports_payments_monthly');
        $this->assertSame('tbank', $stored->filters['payment_provider']);
        $this->assertSame('operation', $stored->filters['mode']);

        $html = $this->get(route('reports.payments.monthly'))->assertOk()->getContent();
        $this->assertStringContainsString('value="tbank" selected', $html);
        $this->assertModeButtonActive($html, 'operation', true);
        $this->assertModeButtonActive($html, 'subscription', false);

        $subscription = $this->get(route('reports.payments.monthly', ['mode' => 'subscription']))->assertOk()->getContent();
        $this->assertStringContainsString('value="tbank" selected', $subscription);
        $this->assertModeButtonActive($subscription, 'subscription', true);
        $this->assertModeButtonActive($subscription, 'operation', false);
        $this->assertSame('tbank', $this->filtersRow('reports_payments_monthly')->filters['payment_provider']);
        $this->assertSame('operation', $this->filtersRow('reports_payments_monthly')->filters['mode']);

        $this->get(route('reports.payments.monthly', ['payment_month' => '2026-03']))->assertOk();
        $afterLink = $this->filtersRow('reports_payments_monthly');
        $this->assertSame('2026-03', $afterLink->filters['payment_month']);
        $this->assertSame('', $afterLink->filters['payment_provider']);
        $this->assertSame('operation', $afterLink->filters['mode']);

        $this->postJson(route('reports.payments.monthly.filters.save'), [
            'mode' => 'year',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'mode' => 'Выберите группировку: по месяцу абонемента или по дате платежа.',
        ]);
        $this->assertSame('operation', $this->filtersRow('reports_payments_monthly')->filters['mode']);

        $this->postJson(route('reports.payments.monthly.filters.save'), [
            'reset' => 1,
        ])->assertOk();
        $this->assertSame('subscription', $this->filtersRow('reports_payments_monthly')->filters['mode']);
    }

    public function test_null_filters_keep_the_active_default_and_the_saved_page_length(): void
    {
        UserTableSetting::query()->create([
            'user_id' => $this->user->id,
            'table_key' => 'reports_ltv',
            'columns' => ['summ' => false],
            'page_length' => 20,
            'filters' => null,
        ]);

        $html = $this->get(route('reports.ltv'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($html, 'ltvReportFiltersCollapse', false);
        $this->assertStringContainsString(
            'value="active" selected',
            $this->selectHtml($html, 'pay-ltv-filter-user-status')
        );
        $this->assertStringContainsString('pageLength: 20', $html);

        $this->postJson(route('reports.ltv.filters.save'), [
            'payment_provider' => 'robokassa',
        ])->assertOk();

        $row = $this->filtersRow('reports_ltv');
        $this->assertSame(20, (int) $row->page_length);
        $this->assertFalse($row->columns['summ']);
        $this->assertSame('robokassa', $row->filters['payment_provider']);
    }

    public function test_scalar_team_id_on_ltv_is_stored_as_a_list_and_selected(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'title' => 'Одна группа',
        ]);

        $this->postJson(route('reports.ltv.filters.save'), [
            'filter_team_id' => (string) $team->id,
        ])->assertOk();

        $this->assertSame([(string) $team->id], $this->filtersRow('reports_ltv')->filters['filter_team_id']);

        $html = $this->get(route('reports.ltv'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<option value="'.$team->id.'" selected>Одна группа<\/option>/',
            $html
        );
        $this->assertFiltersPanelOpen($html, 'ltvReportFiltersCollapse', true);
    }

    private function filtersRow(string $tableKey): UserTableSetting
    {
        return UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', $tableKey)
            ->firstOrFail();
    }

    private function grantPermission(string $permissionName): void
    {
        $now = now();
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permissionName),
            ],
            ['created_at' => $now, 'updated_at' => $now]
        );
        $this->user->unsetRelation('role');
    }

    private function forgetPermission(string $permissionName): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
        $this->user->unsetRelation('role');
    }

    private function assertFiltersPanelOpen(string $html, string $id, bool $open): void
    {
        $this->assertSame(
            1,
            preg_match('/<div class="([^"]*)" id="'.preg_quote($id, '/').'">/', $html, $matches),
            'Не найден блок фильтров '.$id
        );
        $isOpen = preg_match('/(?:^|\s)show(?:\s|$)/', $matches[1]) === 1;
        $this->assertSame($open, $isOpen, $matches[0]);
    }

    private function selectHtml(string $html, string $id): string
    {
        $this->assertSame(
            1,
            preg_match('/<select\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>.*?<\/select>/s', $html, $matches),
            'Не найден select #'.$id
        );

        return $matches[0];
    }

    private function formHtml(string $html, string $id): string
    {
        $this->assertSame(
            1,
            preg_match('/<form\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>.*?<\/form>/s', $html, $matches),
            'Не найдена форма #'.$id
        );

        return $matches[0];
    }

    private function assertButtonActive(string $html, string $id, bool $active): void
    {
        $this->assertSame(
            1,
            preg_match('/<button\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>/', $html, $matches),
            'Не найдена кнопка #'.$id
        );
        $this->assertSame($active, preg_match('/\bactive\b/', $matches[0]) === 1, $matches[0]);
    }

    private function assertModeButtonActive(string $html, string $mode, bool $active): void
    {
        $this->assertSame(
            1,
            preg_match('/<button\b[^>]*\bdata-mode="'.preg_quote($mode, '/').'"[^>]*>/', $html, $matches),
            'Не найдена кнопка режима '.$mode
        );
        $this->assertSame($active, preg_match('/\bactive\b/', $matches[0]) === 1, $matches[0]);
    }
}
