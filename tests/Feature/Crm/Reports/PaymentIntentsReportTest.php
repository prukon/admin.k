<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Partner;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\UserTableSetting;
use Tests\Feature\Crm\CrmTestCase;

class PaymentIntentsReportTest extends CrmTestCase
{
    /**
     * [P0] Контроль доступа по праву can:reports.payment.intents.view (страница, data, total, columns, search).
     */
    public function test_payment_intents_routes_require_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.payment.intents.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.payment-intents.index'))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', ['draw' => 1]))
            ->assertForbidden();

        $this->get(route('reports.payment-intents.total'))->assertForbidden();

        $this->get('/admin/reports/payment-intents/columns-settings')->assertForbidden();
        $this->postJson('/admin/reports/payment-intents/columns-settings', ['columns' => ['id' => true]])->assertForbidden();

        $this->get(route('reports.payment-intents.partners.search', ['q' => 'test']))->assertForbidden();
        $this->get(route('reports.payment-intents.users.search', ['q' => 'test']))->assertForbidden();
    }

    /**
     * [P0] Для пользователя с правом все основные endpoints отчёта доступны (200).
     */
    public function test_payment_intents_endpoints_return_200_for_authorized_user(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.payment-intents.index'))->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', ['draw' => 1]))
            ->assertOk();

        $this->get(route('reports.payment-intents.total'))->assertOk();

        $this->get('/admin/reports/payment-intents/columns-settings')->assertOk();
        $this->postJson('/admin/reports/payment-intents/columns-settings', ['columns' => ['id' => true]])->assertOk();

        $this->get(route('reports.payment-intents.partners.search', ['q' => '']))->assertOk();
        $this->get(route('reports.payment-intents.users.search', ['q' => '']))->assertOk();
    }

    /**
     * [P0] /admin/reports/payment-intents/total возвращает сумму out_sum по фильтрам.
     */
    public function test_payment_intents_total_endpoint_returns_correct_sum(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'out_sum' => 1000,
        ]);
        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'out_sum' => 2000,
        ]);
        PaymentIntent::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'out_sum' => 9999,
        ]);

        $expectedRaw = 3000.0;
        $expectedFormatted = number_format($expectedRaw, 0, '', ' ');

        $this->get(route('reports.payment-intents.total'))
            ->assertOk()
            ->assertJson([
                'total_formatted' => $expectedFormatted,
                'total_raw' => $expectedRaw,
            ]);
    }

    /**
     * [P1] columns-settings сохраняются с table_key=reports_payment_intents.
     */
    public function test_payment_intents_columns_settings_saved_and_loaded(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get('/admin/reports/payment-intents/columns-settings')
            ->assertOk()
            ->assertExactJson([]);

        $payload = [
            'columns' => [
                'id' => true,
                'meta' => false,
            ],
        ];

        $this->postJson('/admin/reports/payment-intents/columns-settings', $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_payment_intents')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($payload['columns'], $setting->columns);

        $this->get('/admin/reports/payment-intents/columns-settings')
            ->assertOk()
            ->assertExactJson($payload['columns']);
    }

    /**
     * [P1] Select2 partners/users search возвращают results[].
     */
    public function test_payment_intents_select2_search_endpoints_return_results(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $p = Partner::factory()->create(['title' => 'AAA Partner']);
        $u = User::factory()->create([
            'partner_id' => $p->id,
            'lastname' => 'Иванов',
            'name' => 'Иван',
        ]);

        $partnersJson = $this->get(route('reports.payment-intents.partners.search', ['q' => 'AAA']))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('results', $partnersJson);
        $this->assertNotEmpty($partnersJson['results']);

        $usersJson = $this->get(route('reports.payment-intents.users.search', ['q' => 'Иван', 'partner_id' => $p->id]))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('results', $usersJson);
        $ids = collect($usersJson['results'])->pluck('id')->all();
        $this->assertContains($u->id, $ids);
    }

    /**
     * [P1] DataTables JSON содержит поля клиентского контекста для отчёта и SQL-джойнов.
     */
    public function test_payment_intents_datatable_includes_client_context_fields(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $intent = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'provider' => 'tbank',
            'payment_date' => '2026-03-15',
            'client_device_type' => 'mobile',
            'client_os_family' => 'iOS',
            'client_os_version' => '17.0',
            'client_browser_family' => 'Safari',
            'client_browser_version' => '17',
            'client_user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
            'client_ip' => '192.0.2.10',
            'client_referrer' => 'https://kidscrm.example/pay',
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', ['draw' => 1]))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $json);
        $row = collect($json['data'])->firstWhere('id', $intent->id);
        $this->assertIsArray($row);
        $this->assertSame('mobile', $row['client_device_type']);
        $this->assertSame('iOS', $row['client_os_family']);
        $this->assertSame('iOS 17.0', $row['client_os']);
        $this->assertSame('Safari', $row['client_browser_family']);
        $this->assertSame('Safari 17', $row['client_browser']);
        $this->assertStringContainsString('iPhone', (string) $row['client_user_agent']);
        $this->assertSame('192.0.2.10', $row['client_ip']);
        $this->assertStringContainsString('kidscrm.example', (string) $row['client_referrer']);
        $this->assertSame('март 2026', $row['payment_date']);
    }

    /**
     * [P1] payment_date в JSON отчёта: дата → «месяц год», текстовые метки без изменений.
     */
    public function test_payment_intents_datatable_formats_payment_date_for_display(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $monthly = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'payment_date' => '2026-06-01',
        ]);
        $legacyDatetime = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'payment_date' => '2026-06-06 13:17:07',
        ]);
        $clubFee = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'payment_date' => 'Клубный взнос',
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', ['draw' => 1]))
            ->assertOk()
            ->json();

        $rows = collect($json['data'])->keyBy('id');

        $this->assertSame('июнь 2026', $rows[$monthly->id]['payment_date']);
        $this->assertSame('июнь 2026', $rows[$legacyDatetime->id]['payment_date']);
        $this->assertSame('Клубный взнос', $rows[$clubFee->id]['payment_date']);
    }

    /**
     * [P1] Страница: KidsCrmDataTable, типы колонок (datetime, inline-actions), без custom.
     */
    public function test_payment_intents_page_renders_kidscrm_datatable_column_types(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.payment-intents.index'))
            ->assertOk()
            ->assertViewHas('activeTab', 'payment-intents')
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee("type: 'datetime'", false)
            ->assertSee("type: 'inline-actions'", false)
            ->assertSee("type: 'money'", false)
            ->assertSee("type: 'badge'", false)
            ->assertSee("data-column-key=\"client_user_agent\"", false)
            ->assertSee("data-column-key=\"meta\"", false)
            ->assertSee('fixedColumns: {leftColumns: 2}', false)
            ->assertSee('id="paymentIntentMetaModal"', false)
            ->assertSee("inlineActions: { modalTitle: 'User-Agent' }", false)
            ->assertSee("inlineActions: { modalTitle: 'Мета', format: 'meta-pretty' }", false);
    }

    /**
     * [P1] JSON: partner_title и user_name из связей, без сырых ID в этих полях.
     */
    public function test_payment_intents_datatable_returns_partner_title_and_user_name(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->partner->update(['title' => 'Тестовый партнёр PI']);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Петров',
            'name' => 'Пётр',
        ]);

        $intent = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', ['draw' => 1]))
            ->assertOk()
            ->json();

        $row = collect($json['data'])->firstWhere('id', $intent->id);
        $this->assertIsArray($row);
        $this->assertSame('Тестовый партнёр PI', $row['partner_title']);
        $this->assertStringContainsString('Петров', (string) $row['user_name']);
        $this->assertStringContainsString('Пётр', (string) $row['user_name']);
    }

    /**
     * [P1] JSON: локализованные метки способа оплаты (payment_method_webhook).
     */
    public function test_payment_intents_datatable_payment_method_webhook_labels(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $card = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'payment_method_webhook' => 'card',
        ]);
        $sbp = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'payment_method_webhook' => 'sbp_qr',
        ]);
        $tpay = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'payment_method_webhook' => 'tpay',
        ]);
        $unknown = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'payment_method_webhook' => 'apple_pay',
        ]);

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.payment-intents.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->keyBy('id');

        $this->assertSame('Карта', $rows[$card->id]['payment_method_webhook_label']);
        $this->assertSame('QR (СБП)', $rows[$sbp->id]['payment_method_webhook_label']);
        $this->assertSame('T‑Pay', $rows[$tpay->id]['payment_method_webhook_label']);
        $this->assertSame('apple_pay', $rows[$unknown->id]['payment_method_webhook_label']);
    }

    /**
     * [P1] JSON: client_os / client_browser при частично заполненных полях.
     */
    public function test_payment_intents_datatable_client_context_partial_values(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $familyOnly = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'client_os_family' => 'Android',
            'client_os_version' => null,
            'client_browser_family' => null,
            'client_browser_version' => '120',
        ]);
        $empty = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'client_os_family' => null,
            'client_os_version' => null,
            'client_browser_family' => null,
            'client_browser_version' => null,
        ]);

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.payment-intents.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->keyBy('id');

        $this->assertSame('Android', $rows[$familyOnly->id]['client_os']);
        $this->assertSame('120', $rows[$familyOnly->id]['client_browser']);
        $this->assertSame('', $rows[$empty->id]['client_os']);
        $this->assertSame('', $rows[$empty->id]['client_browser']);
    }

    /**
     * [P1] JSON: created_at и paid_at в формате Y-m-d H:i:s.
     */
    public function test_payment_intents_datatable_formats_timestamps(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $createdAt = now()->setDate(2026, 3, 10)->setTime(14, 30, 0);
        $paidAt = now()->setDate(2026, 3, 11)->setTime(9, 15, 30);

        $intent = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'created_at' => $createdAt,
            'paid_at' => $paidAt,
            'status' => 'paid',
        ]);

        $row = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.payment-intents.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $intent->id);

        $this->assertIsArray($row);
        $this->assertSame('2026-03-10 14:30:00', $row['created_at']);
        $this->assertSame('2026-03-11 09:15:30', $row['paid_at']);
    }

    /**
     * [P1] JSON: поле meta отдаётся в ответе DataTables.
     */
    public function test_payment_intents_datatable_includes_meta_field(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $metaJson = '{"order":"42","source":"test"}';
        $intent = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'meta' => $metaJson,
        ]);

        $row = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.payment-intents.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $intent->id);

        $this->assertIsArray($row);
        $this->assertArrayHasKey('meta', $row);
        $this->assertStringContainsString('order', (string) $row['meta']);
    }

    /**
     * [P1] payment_date: пустое значение, ISO с T, невалидный месяц — без падения.
     */
    public function test_payment_intents_datatable_payment_date_edge_cases(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $empty = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'payment_date' => null,
        ]);
        $isoT = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'payment_date' => '2025-12-25T10:00:00',
        ]);
        $invalidMonth = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'payment_date' => '2026-13-01',
        ]);

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.payment-intents.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->keyBy('id');

        $this->assertSame('', $rows[$empty->id]['payment_date']);
        $this->assertSame('декабрь 2025', $rows[$isoT->id]['payment_date']);
        $this->assertSame('2026-13-01', $rows[$invalidMonth->id]['payment_date']);
    }

    /**
     * [P1] Фильтры status и provider сужают выборку data и total.
     */
    public function test_payment_intents_filters_by_status_and_provider(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'status' => 'paid',
            'provider' => 'tbank',
            'out_sum' => 1000,
        ]);
        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'status' => 'pending',
            'provider' => 'robokassa',
            'out_sum' => 5000,
        ]);

        $params = [
            'draw' => 1,
            'status' => 'paid',
            'provider' => 'tbank',
        ];

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', $params))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) $json['recordsFiltered']);
        $this->assertSame('paid', $json['data'][0]['status']);
        $this->assertSame('tbank', $json['data'][0]['provider']);

        $this->get(route('reports.payment-intents.total', [
            'status' => 'paid',
            'provider' => 'tbank',
        ]))
            ->assertOk()
            ->assertJson([
                'total_raw' => 1000.0,
            ]);
    }

    /**
     * [P1] Фильтр inv_id ищет по id и provider_inv_id.
     */
    public function test_payment_intents_filters_by_inv_id(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $byId = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'provider_inv_id' => 111222,
        ]);
        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'provider_inv_id' => 999888,
        ]);

        $byProviderInv = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'provider_inv_id' => 555666,
        ]);

        $jsonById = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', ['draw' => 1, 'inv_id' => (string) $byId->id]))
            ->assertOk()
            ->json();
        $this->assertSame(1, (int) $jsonById['recordsFiltered']);
        $this->assertSame($byId->id, $jsonById['data'][0]['id']);

        $jsonByProvider = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', ['draw' => 1, 'inv_id' => '555666']))
            ->assertOk()
            ->json();
        $this->assertSame(1, (int) $jsonByProvider['recordsFiltered']);
        $this->assertSame($byProviderInv->id, $jsonByProvider['data'][0]['id']);
    }

    /**
     * [P1] В контексте партнёра чужие payment_intents не попадают в data.
     */
    public function test_payment_intents_partner_scope_isolation(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $own = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'out_sum' => 700,
        ]);
        PaymentIntent::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'out_sum' => 9000,
        ]);

        $ids = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.payment-intents.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains(
            PaymentIntent::query()->where('partner_id', $this->foreignPartner->id)->value('id'),
            $ids
        );

        $this->get(route('reports.payment-intents.total'))
            ->assertOk()
            ->assertJson(['total_raw' => 700.0]);
    }

    /**
     * [P1] columns-settings: без columns — 422.
     */
    public function test_payment_intents_columns_settings_validation_requires_columns(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->postJson('/admin/reports/payment-intents/columns-settings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    /**
     * [P1] users-search: невалидный partner_id — 422.
     */
    public function test_payment_intents_users_search_validation_rejects_invalid_partner_id(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson(route('reports.payment-intents.users.search', ['partner_id' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id']);
    }

    /**
     * [P1] Страница с partner_id/user_id в query подставляет опции Select2.
     */
    public function test_payment_intents_index_with_filter_query_params_renders_select2_options(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $partner = Partner::factory()->create(['title' => 'Query Partner PI']);
        $student = User::factory()->create([
            'partner_id' => $partner->id,
            'lastname' => 'Сидоров',
            'name' => 'Сидор',
        ]);

        $this->get(route('reports.payment-intents.index', [
            'partner_id' => $partner->id,
            'user_id' => $student->id,
        ]))
            ->assertOk()
            ->assertSee('Query Partner PI', false)
            ->assertSee('Сидоров', false);
    }
}
