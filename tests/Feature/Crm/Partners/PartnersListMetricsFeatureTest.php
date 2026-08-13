<?php

namespace Tests\Feature\Crm\Partners;

use App\Models\Contract;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserTableSetting;
use App\Support\PartnerListMetrics;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Метрики списка /admin/partners: активные ученики, подписанные договоры, оборот.
 *
 * @see /docs/documentation/account-partner-organization.html §3.1.1
 */
final class PartnersListMetricsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-13 21:00:00', 'Europe/Moscow'));

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPartnerView();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // --- HTTP / доступ ---

    public function test_guest_cannot_open_partners_metrics_page_or_data(): void
    {
        Auth::logout();

        $this->get(route('admin.partner.index'))->assertRedirect();
        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertUnauthorized();
        $this->getJson(route('admin.partner.columns-settings.get'))->assertUnauthorized();
        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => ['turnover_all' => true],
        ])->assertUnauthorized();
    }

    public function test_manager_without_partner_view_gets_403_on_metrics_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.partner.index'))->assertForbidden();
        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertForbidden();
        $this->getJson(route('admin.partner.columns-settings.get'))->assertForbidden();
        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => ['active_users_count' => true],
        ])->assertForbidden();
    }

    public function test_admin_with_partner_view_gets_metrics_json_structure(): void
    {
        $json = $this->getJson(route('admin.partner.data', [
            'draw' => 7,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
            ])
            ->json();

        $this->assertSame(7, $json['draw']);
        $this->assertNotSame('', trim((string) json_encode($json['data'])));

        if ($json['data'] !== []) {
            $row = $json['data'][0];
            foreach (PartnerListMetrics::JSON_KEYS as $key) {
                $this->assertArrayHasKey($key, $row);
            }
        }
    }

    public function test_non_ajax_get_data_still_returns_json_metrics_not_empty_html(): void
    {
        $response = $this->get(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $json = $response->json();
        $this->assertIsArray($json['data'] ?? null);
        $this->assertArrayHasKey('recordsTotal', $json);
    }

    public function test_invalid_status_filter_returns_422_with_status_field_error(): void
    {
        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'status' => 'unknown',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Недопустимое значение фильтра статуса.');
    }

    public function test_too_long_search_returns_422_with_title_field_error(): void
    {
        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'title' => str_repeat('а', 256),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonPath('errors.title.0', 'Поиск по названию не должен превышать 255 символов.');
    }

    public function test_invalid_page_length_returns_422_with_length_field_error(): void
    {
        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'length' => 0,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['length']);
    }

    public function test_columns_settings_reject_empty_payload_with_columns_field_error(): void
    {
        $this->postJson(route('admin.partner.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_columns_settings_save_and_reload_metric_keys(): void
    {
        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => [
                'title' => true,
                'active_users_count' => false,
                'signed_contracts_count' => true,
                'turnover_all' => 'true',
                'turnover_month_0' => 0,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('admin.partner.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('active_users_count', false)
            ->assertJsonPath('signed_contracts_count', true)
            ->assertJsonPath('turnover_all', true)
            ->assertJsonPath('turnover_month_0', false);
    }

    public function test_non_ajax_columns_settings_save_returns_json_not_empty_200(): void
    {
        $this->post(route('admin.partner.columns-settings.save'), [
            'columns' => ['turnover_all' => true, 'actions' => true],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // --- Blade / UX ---

    public function test_index_renders_metric_columns_with_dynamic_month_headers(): void
    {
        $html = $this->get(route('admin.partner.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Кол-во активных пользователей', $html);
        $this->assertStringContainsString('Кол-во договоров', $html);
        $this->assertStringContainsString('Оборот за всё время', $html);
        $this->assertStringContainsString('Оборот за август', $html);
        $this->assertStringContainsString('Оборот за июль', $html);
        $this->assertStringContainsString('Оборот за июнь', $html);
        $this->assertStringContainsString("key: 'active_users_count', type: 'count'", $html);
        $this->assertStringContainsString("key: 'signed_contracts_count', type: 'count'", $html);
        $this->assertStringContainsString("key: 'turnover_all', type: 'money'", $html);
        $this->assertStringContainsString("key: 'turnover_month_0', type: 'money'", $html);
        $this->assertStringContainsString('data-column-key="active_users_count"', $html);
        $this->assertStringContainsString('option value="active" selected', $html);
    }

    public function test_index_shows_metric_columns_after_status_and_before_actions(): void
    {
        $html = $this->get(route('admin.partner.index'))->assertOk()->getContent();

        $tablePos = strpos($html, 'id="partners-table"');
        $this->assertNotFalse($tablePos);
        $theadSnippet = substr($html, $tablePos, 2500);

        $statusPos = strpos($theadSnippet, '<th>Статус</th>');
        $usersPos = strpos($theadSnippet, '<th>Кол-во активных пользователей</th>');
        $contractsPos = strpos($theadSnippet, '<th>Кол-во договоров</th>');
        $allPos = strpos($theadSnippet, '<th>Оборот за всё время</th>');
        $augPos = strpos($theadSnippet, '<th>Оборот за август</th>');
        $julPos = strpos($theadSnippet, '<th>Оборот за июль</th>');
        $junPos = strpos($theadSnippet, '<th>Оборот за июнь</th>');
        $actionsPos = strpos($theadSnippet, '<th>Действия</th>');

        $this->assertNotFalse($statusPos);
        $this->assertNotFalse($usersPos);
        $this->assertNotFalse($contractsPos);
        $this->assertNotFalse($allPos);
        $this->assertNotFalse($augPos);
        $this->assertNotFalse($julPos);
        $this->assertNotFalse($junPos);
        $this->assertNotFalse($actionsPos);
        $this->assertLessThan($usersPos, $statusPos);
        $this->assertLessThan($contractsPos, $usersPos);
        $this->assertLessThan($allPos, $contractsPos);
        $this->assertLessThan($augPos, $allPos);
        $this->assertLessThan($julPos, $augPos);
        $this->assertLessThan($junPos, $julPos);
        $this->assertLessThan($actionsPos, $junPos);

        preg_match('/<thead>(.*?)<\/thead>/s', $theadSnippet, $theadMatch);
        $this->assertNotEmpty($theadMatch[1] ?? null);
        preg_match_all('/<th\b/i', $theadMatch[1], $thMatch);
        $this->assertCount(13, $thMatch[0], 'thead и JS columns должны совпадать по числу колонок');
    }

    public function test_index_metric_column_toggles_are_checked_by_default(): void
    {
        $html = $this->get(route('admin.partner.index'))->assertOk()->getContent();

        foreach ([
            'active_users_count',
            'signed_contracts_count',
            'turnover_all',
            'turnover_month_0',
            'turnover_month_1',
            'turnover_month_2',
        ] as $key) {
            $this->assertMatchesRegularExpression(
                '/data-column-key="'.preg_quote($key, '/').'"[\s\S]{0,120}?checked/',
                $html,
                "Тумблер колонки {$key} должен быть включён при первом открытии"
            );
        }
    }

    public function test_index_january_headers_are_january_december_november_not_august(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-05 10:00:00', 'Europe/Moscow'));

        $html = $this->get(route('admin.partner.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Оборот за январь', $html);
        $this->assertStringContainsString('Оборот за декабрь', $html);
        $this->assertStringContainsString('Оборот за ноябрь', $html);
        $this->assertStringNotContainsString('Оборот за август', $html);
        $this->assertStringNotContainsString('Оборот за июль', $html);
        $this->assertStringNotContainsString('Оборот за июнь', $html);
        $this->assertStringContainsString("key: 'turnover_month_0'", $html);
    }

    public function test_old_saved_column_settings_without_metric_keys_leave_defaults_to_client(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'partners_index'],
            ['columns' => ['title' => true, 'email' => false, 'actions' => true]]
        );

        $saved = $this->getJson(route('admin.partner.columns-settings.get'))
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('active_users_count', $saved);
        $this->assertArrayNotHasKey('turnover_all', $saved);

        $html = $this->get(route('admin.partner.index'))->assertOk()->getContent();
        $this->assertStringContainsString('active_users_count: true', $html);
        $this->assertStringContainsString('turnover_all: true', $html);

        $dtJs = (string) file_get_contents(resource_path('js/kids-datatable.js'));
        $this->assertStringContainsString('Object.prototype.hasOwnProperty.call(response, key)', $dtJs);
        $this->assertStringContainsString('defaultColumnsVisibility[key]', $dtJs);
    }

    public function test_index_js_keeps_filter_reset_on_active_not_all_partners(): void
    {
        $html = $this->get(route('admin.partner.index'))->assertOk()->getContent();

        $this->assertStringContainsString("const defaultFilterStatus = 'active';", $html);
        $resetPos = strpos($html, "$('#filter-reset').on('click'");
        $this->assertNotFalse($resetPos);
        $resetChunk = substr($html, $resetPos, 350);
        $this->assertStringContainsString('defaultFilterStatus', $resetChunk);
        $this->assertStringNotContainsString("$('#filter-status').val('');", $resetChunk);
        $this->assertStringContainsString("e.preventDefault();", $html);
    }

    // --- Данные / границы ---

    public function test_data_returns_zero_metrics_when_partner_has_no_students_contracts_or_payments(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Metrics empty partner',
            'is_enabled' => true,
        ]);

        $row = $this->fetchPartnerRow($partner);

        $this->assertSame(0, $row['active_users_count']);
        $this->assertSame(0, $row['signed_contracts_count']);
        $this->assertEquals(0, $row['turnover_all']);
        $this->assertEquals(0, $row['turnover_month_0']);
        $this->assertEquals(0, $row['turnover_month_1']);
        $this->assertEquals(0, $row['turnover_month_2']);
    }

    public function test_data_counts_only_enabled_students_and_signed_contracts(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Metrics students partner',
            'is_enabled' => true,
        ]);

        $studentRoleId = $this->roleId('user');
        $activeA = User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $studentRoleId,
            'is_enabled' => true,
        ]);
        User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $studentRoleId,
            'is_enabled' => true,
        ]);
        User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $studentRoleId,
            'is_enabled' => false,
        ]);
        User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId('admin'),
            'is_enabled' => true,
        ]);
        User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId('trainer'),
            'is_enabled' => true,
        ]);
        $deleted = User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $studentRoleId,
            'is_enabled' => true,
        ]);
        $deleted->delete();

        $this->createContract($partner, $activeA, Contract::STATUS_SIGNED);
        $this->createContract($partner, $activeA, Contract::STATUS_SIGNED);
        $this->createContract($partner, $activeA, Contract::STATUS_DRAFT);

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $studentRoleId,
            'is_enabled' => true,
        ]);
        $this->createContract($this->foreignPartner, $foreignStudent, Contract::STATUS_SIGNED);

        $row = $this->fetchPartnerRow($partner);

        $this->assertSame(2, $row['active_users_count']);
        $this->assertSame(2, $row['signed_contracts_count']);
    }

    public function test_unsigned_and_revoked_contracts_are_not_counted_as_signed(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Metrics unsigned contracts',
            'is_enabled' => true,
        ]);
        $student = User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
        ]);

        $this->createContract($partner, $student, Contract::STATUS_SENT);
        $this->createContract($partner, $student, Contract::STATUS_OPENED);
        $this->createContract($partner, $student, Contract::STATUS_REVOKED);
        $this->createContract($partner, $student, Contract::STATUS_EXPIRED);
        $this->createContract($partner, $student, Contract::STATUS_SIGNED);

        $row = $this->fetchPartnerRow($partner);
        $this->assertSame(1, $row['signed_contracts_count']);
    }

    public function test_inactive_student_payments_count_in_turnover_but_not_in_active_users(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Metrics inactive student pay',
            'is_enabled' => true,
        ]);
        $inactive = User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => false,
        ]);

        Payment::factory()->forUser($inactive)->create([
            'summ_cents' => 150000,
            'operation_date' => '2026-08-10 12:00:00',
        ]);

        $row = $this->fetchPartnerRow($partner);
        $this->assertSame(0, $row['active_users_count']);
        $this->assertEquals(1500, $row['turnover_all']);
        $this->assertEquals(1500, $row['turnover_month_0']);
    }

    public function test_data_turnover_uses_operation_date_and_ignores_foreign_partner(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Metrics turnover partner',
            'is_enabled' => true,
        ]);

        $student = User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ_cents' => 100000,
            'operation_date' => '2026-08-10 12:00:00',
            'payment_month' => '2026-07-01',
        ]);
        Payment::factory()->forUser($student)->create([
            'summ_cents' => 200000,
            'operation_date' => '2026-07-20 09:00:00',
            'payment_month' => '2026-08-01',
        ]);
        Payment::factory()->forUser($student)->create([
            'summ_cents' => 300000,
            'operation_date' => '2026-06-02 00:00:00',
            'payment_month' => '2026-06-01',
        ]);
        Payment::factory()->forUser($student)->create([
            'summ_cents' => 400000,
            'operation_date' => '2026-05-15 11:00:00',
            'payment_month' => '2026-05-01',
        ]);
        Payment::factory()->forUser($student)->create([
            'summ_cents' => 0,
            'operation_date' => '2026-08-11 10:00:00',
        ]);

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
        ]);
        Payment::factory()->forUser($foreignStudent)->create([
            'summ_cents' => 9990000,
            'operation_date' => '2026-08-05 08:00:00',
        ]);

        $row = $this->fetchPartnerRow($partner);

        $this->assertEquals(10000, $row['turnover_all']);
        $this->assertEquals(1000, $row['turnover_month_0']);
        $this->assertEquals(2000, $row['turnover_month_1']);
        $this->assertEquals(3000, $row['turnover_month_2']);
    }

    public function test_payment_on_month_boundary_belongs_to_correct_month(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Metrics boundary partner',
            'is_enabled' => true,
        ]);
        $student = User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ_cents' => 10000,
            'operation_date' => '2026-08-01 00:00:00',
        ]);
        Payment::factory()->forUser($student)->create([
            'summ_cents' => 20000,
            'operation_date' => '2026-08-31 23:59:59',
        ]);
        Payment::factory()->forUser($student)->create([
            'summ_cents' => 40000,
            'operation_date' => '2026-09-01 00:00:00',
        ]);
        Payment::factory()->forUser($student)->create([
            'summ_cents' => 80000,
            'operation_date' => '2026-07-31 23:59:59',
        ]);

        $row = $this->fetchPartnerRow($partner);
        $this->assertEquals(300, $row['turnover_month_0']);
        $this->assertEquals(800, $row['turnover_month_1']);
        $this->assertEquals(1500, $row['turnover_all']);
    }

    public function test_kopecks_are_returned_as_ruble_fraction_not_truncated(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Metrics kopecks partner',
            'is_enabled' => true,
        ]);
        $student = User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ_cents' => 1050,
            'operation_date' => '2026-08-10 12:00:00',
        ]);

        $row = $this->fetchPartnerRow($partner);
        $this->assertEquals(10.5, $row['turnover_all']);
        $this->assertEquals(10.5, $row['turnover_month_0']);
    }

    public function test_partner_row_is_not_duplicated_when_many_payments_exist(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Metrics no-dup partner',
            'is_enabled' => true,
        ]);
        $student = User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
        ]);

        for ($i = 0; $i < 12; $i++) {
            Payment::factory()->forUser($student)->create([
                'summ_cents' => 10000,
                'operation_date' => '2026-08-10 12:00:00',
            ]);
        }

        $json = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => 'Metrics no-dup partner',
        ]))->assertOk()->json();

        $ids = array_column($json['data'], 'id');
        $this->assertSame(1, count(array_keys($ids, $partner->id, true)));
        $this->assertSame(1, $json['recordsFiltered']);
        $row = collect($json['data'])->firstWhere('id', $partner->id);
        $this->assertEquals(1200, $row['turnover_all']);
    }

    public function test_inactive_partner_metrics_are_visible_when_filter_is_inactive(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Metrics inactive club',
            'is_enabled' => false,
        ]);
        $student = User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
        ]);
        Payment::factory()->forUser($student)->create([
            'summ_cents' => 250000,
            'operation_date' => '2026-08-10 12:00:00',
        ]);

        $activeJson = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => 'Metrics inactive club',
        ]))->assertOk()->json();
        $this->assertNull(collect($activeJson['data'])->firstWhere('id', $partner->id));

        $row = $this->fetchPartnerRow($partner, 'inactive');
        $this->assertSame(1, $row['active_users_count']);
        $this->assertEquals(2500, $row['turnover_month_0']);
    }

    public function test_data_sort_by_turnover_all_desc(): void
    {
        $low = Partner::factory()->create([
            'title' => 'Metrics sort low',
            'is_enabled' => true,
            'order_by' => 1,
        ]);
        $high = Partner::factory()->create([
            'title' => 'Metrics sort high',
            'is_enabled' => true,
            'order_by' => 2,
        ]);

        $studentRoleId = $this->roleId('user');
        $lowStudent = User::factory()->create([
            'partner_id' => $low->id,
            'role_id' => $studentRoleId,
            'is_enabled' => true,
        ]);
        $highStudent = User::factory()->create([
            'partner_id' => $high->id,
            'role_id' => $studentRoleId,
            'is_enabled' => true,
        ]);

        Payment::factory()->forUser($lowStudent)->create([
            'summ_cents' => 10000,
            'operation_date' => '2026-08-01 00:00:00',
        ]);
        Payment::factory()->forUser($highStudent)->create([
            'summ_cents' => 50000,
            'operation_date' => '2026-08-01 00:00:00',
        ]);

        $response = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => 'Metrics sort',
            'order' => [['column' => 8, 'dir' => 'desc']],
            'columns' => $this->metricColumnsLayout(),
        ]));

        $response->assertOk();
        $titles = array_column($response->json('data'), 'title');
        $this->assertSame(['Metrics sort high', 'Metrics sort low'], $titles);
    }

    public function test_data_sort_by_active_users_desc(): void
    {
        $few = Partner::factory()->create([
            'title' => 'Metrics users few',
            'is_enabled' => true,
        ]);
        $many = Partner::factory()->create([
            'title' => 'Metrics users many',
            'is_enabled' => true,
        ]);

        $studentRoleId = $this->roleId('user');
        User::factory()->create([
            'partner_id' => $few->id,
            'role_id' => $studentRoleId,
            'is_enabled' => true,
        ]);
        User::factory()->count(3)->create([
            'partner_id' => $many->id,
            'role_id' => $studentRoleId,
            'is_enabled' => true,
        ]);

        $response = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => 'Metrics users',
            'order' => [['column' => 6, 'dir' => 'desc']],
            'columns' => $this->metricColumnsLayout(),
        ]));

        $response->assertOk();
        $titles = array_column($response->json('data'), 'title');
        $this->assertSame(['Metrics users many', 'Metrics users few'], $titles);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPartnerRow(Partner $partner, string $status = 'active'): array
    {
        $json = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => $status,
            'title' => $partner->title,
        ]))
            ->assertOk()
            ->json();

        $row = collect($json['data'])->firstWhere('id', $partner->id);
        $this->assertNotNull($row);

        return $row;
    }

    private function createContract(Partner $partner, User $user, string $status): Contract
    {
        return Contract::create([
            'school_id'       => $partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/contract-' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'status'          => $status,
        ]);
    }

    /**
     * @return list<array{name: string}>
     */
    private function metricColumnsLayout(): array
    {
        return [
            ['name' => 'rownum'],
            ['name' => 'order_by'],
            ['name' => 'title'],
            ['name' => 'email'],
            ['name' => 'phone'],
            ['name' => 'status_label'],
            ['name' => PartnerListMetrics::COLUMN_ACTIVE_USERS],
            ['name' => PartnerListMetrics::COLUMN_SIGNED_CONTRACTS],
            ['name' => PartnerListMetrics::COLUMN_TURNOVER_ALL],
            ['name' => PartnerListMetrics::COLUMN_TURNOVER_MONTH_0],
            ['name' => PartnerListMetrics::COLUMN_TURNOVER_MONTH_1],
            ['name' => PartnerListMetrics::COLUMN_TURNOVER_MONTH_2],
            ['name' => 'actions'],
        ];
    }

    private function grantPartnerView(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('partner.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
