<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Глобальный поиск DataTables в «Платежи по месяцам» и «Платежные запросы»:
 * ФИО/группа/месяц (monthly) и ФИО/партнёр/ID (intents), без суммы и дат.
 */
final class ReportsMonthlyIntentsDatatableSearchFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->flushHeaders();
        $this->asAdmin();
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @return list<array<string, string>>
     */
    private function monthlyBrowserColumns(): array
    {
        $col = static function (string $data, string $name, bool $searchable = true, bool $orderable = true): array {
            return [
                'data' => $data,
                'name' => $name,
                'searchable' => $searchable ? 'true' : 'false',
                'orderable' => $orderable ? 'true' : 'false',
            ];
        };

        return [
            $col('', '', false, false),
            $col('month_title', 'month_title'),
            $col('payments_count', 'payments_count'),
            $col('total_sum', 'total_sum'),
            $col('month_key', 'month_key', false),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function monthlyDetailBrowserColumns(): array
    {
        $col = static function (string $data, string $name, bool $searchable = true, bool $orderable = true): array {
            return [
                'data' => $data,
                'name' => $name,
                'searchable' => $searchable ? 'true' : 'false',
                'orderable' => $orderable ? 'true' : 'false',
            ];
        };

        return [
            $col('operation_date', 'operation_date'),
            $col('user_name', 'user_name'),
            $col('team_title', 'team_title'),
            $col('summ', 'summ'),
            $col('payment_month', 'payment_month'),
            $col('payment_provider', 'payment_provider', false, false),
        ];
    }

    /**
     * Колонки как в живой таблице intents: сумма/даты searchable — до фикса autoFilter давал 500.
     *
     * @return list<array<string, string>>
     */
    private function intentsBrowserColumns(): array
    {
        $col = static function (string $data, string $name, bool $searchable = true, bool $orderable = true): array {
            return [
                'data' => $data,
                'name' => $name,
                'searchable' => $searchable ? 'true' : 'false',
                'orderable' => $orderable ? 'true' : 'false',
            ];
        };

        return [
            $col('id', 'id'),
            $col('provider_inv_id', 'provider_inv_id'),
            $col('partner_title', 'partner_title'),
            $col('user_name', 'user_name'),
            $col('provider', 'provider'),
            $col('payment_method_webhook_label', 'payment_method_webhook_label', false, false),
            $col('status', 'status'),
            $col('out_sum', 'out_sum'),
            $col('payment_date', 'payment_date'),
            $col('created_at', 'created_at'),
            $col('paid_at', 'paid_at'),
            $col('client_device_type', 'client_device_type'),
            $col('client_os', 'client_os'),
            $col('client_browser', 'client_browser'),
            $col('client_user_agent', 'client_user_agent'),
            $col('client_ip', 'client_ip'),
            $col('client_referrer', 'client_referrer'),
            $col('meta', 'meta'),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function monthlySearchQuery(string $needle, array $extra = []): array
    {
        return array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'mode' => 'subscription',
            'columns' => $this->monthlyBrowserColumns(),
            'search' => ['value' => $needle],
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function monthlyDetailSearchQuery(string $needle, array $extra = []): array
    {
        return array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'mode' => 'subscription',
            'columns' => $this->monthlyDetailBrowserColumns(),
            'search' => ['value' => $needle],
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function intentsSearchQuery(string $needle, array $extra = []): array
    {
        return array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'columns' => $this->intentsBrowserColumns(),
            'search' => ['value' => $needle],
        ], $extra);
    }

    /**
     * @return list<string>
     */
    private function monthlyMonthKeysFromAjax(array $query): array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.data', $query))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        return collect($json['data'] ?? [])->pluck('month_key')->map(fn ($v) => (string) $v)->all();
    }

    /**
     * @return list<int>
     */
    private function monthlyPaymentIdsFromAjax(string $yearMonth, array $query): array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', array_merge(
                ['yearMonth' => $yearMonth],
                $query
            )))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        return collect($json['data'] ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @return list<int>
     */
    private function intentIdsFromAjax(array $query): array
    {
        $this->asSuperadmin();

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payment-intents.data', $query))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        return collect($json['data'] ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @return list<array{method: string, url: string, headers?: array<string, string>}>
     */
    private function searchEndpointsPayload(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('reports.payments.monthly'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payments.monthly.data', $this->monthlySearchQuery('тест')),
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.data', $this->intentsSearchQuery('тест')),
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
        ];
    }

    public function test_guest_cannot_open_or_search_monthly_and_intents(): void
    {
        Auth::logout();

        foreach ($this->searchEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_permission_cannot_search_monthly_or_intents(): void
    {
        $monthlyActor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($monthlyActor);

        $monthlyResponse = $this->call(
            'GET',
            route('reports.payments.monthly.data', $this->monthlySearchQuery('тест')),
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]
        );
        $this->assertSame(403, $monthlyResponse->getStatusCode());

        $intentsActor = $this->createUserWithoutPermission('reports.payment.intents.view', $this->partner);
        $this->actingAs($intentsActor);

        $intentsResponse = $this->call(
            'GET',
            route('reports.payment-intents.data', $this->intentsSearchQuery('тест')),
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]
        );
        $this->assertSame(403, $intentsResponse->getStatusCode());
    }

    public function test_admin_gets_datatables_json_when_searching_monthly(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.data', $this->monthlySearchQuery('')))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_superadmin_gets_datatables_json_when_searching_intents(): void
    {
        $this->asSuperadmin();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payment-intents.data', $this->intentsSearchQuery('')))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_monthly_search_without_ajax_header_is_not_found(): void
    {
        $this->get(route('reports.payments.monthly.data', $this->monthlySearchQuery('Иванов')))
            ->assertNotFound();
    }

    public function test_unsupported_methods_on_search_endpoints_do_not_return_500(): void
    {
        $this->asSuperadmin();

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            foreach ([
                route('reports.payments.monthly.data', $this->monthlySearchQuery('x')),
                route('reports.payment-intents.data', $this->intentsSearchQuery('x')),
            ] as $url) {
                $response = $this->call(
                    $method,
                    $url,
                    [],
                    [],
                    [],
                    [
                        'HTTP_ACCEPT' => 'application/json',
                        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                    ]
                );

                $this->assertContains(
                    $response->getStatusCode(),
                    [404, 405, 419],
                    "{$method} {$url} → {$response->getStatusCode()}"
                );
                $this->assertNotSame(200, $response->getStatusCode());
                $this->assertNotSame(500, $response->getStatusCode());
            }
        }
    }

    public function test_monthly_search_by_lastname_finds_month_when_snapshot_name_is_empty(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ХармУникDtMon',
            'name' => 'Иван',
            'is_enabled' => 1,
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ДругойDtMon',
            'name' => 'Пётр',
            'is_enabled' => 1,
        ]);

        Payment::factory()->create([
            'user_id' => $hit->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-03-01',
            'operation_date' => '2026-03-10 12:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $miss->id,
            'partner_id' => $this->partner->id,
            'user_name' => 'СовсемДругоеИмя',
            'summ_cents' => 20000,
            'payment_month' => '2026-04-01',
            'operation_date' => '2026-04-10 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('ХармУникDtMon'));

        $this->assertContains('2026-03', $keys);
        $this->assertNotContains('2026-04', $keys);
    }

    public function test_monthly_search_by_firstname_finds_month(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'СидоровDtMon',
            'name' => 'УникИмяDtMon',
            'is_enabled' => 1,
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'СидоровDtMon',
            'name' => 'Пётр',
            'is_enabled' => 1,
        ]);

        Payment::factory()->create([
            'user_id' => $hit->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-05-01',
            'operation_date' => '2026-05-10 12:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $miss->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 20000,
            'payment_month' => '2026-06-01',
            'operation_date' => '2026-06-10 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('УникИмяDtMon'));

        $this->assertContains('2026-05', $keys);
        $this->assertNotContains('2026-06', $keys);
    }

    public function test_monthly_search_by_team_title_finds_month(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаХармDtMon',
        ]);
        $other = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ДругаяГруппаDtMon',
        ]);
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезСовпаденияDtMon',
            'is_enabled' => 1,
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ТожеБезDtMon',
            'is_enabled' => 1,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($hit, [(int) $team->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($miss, [(int) $other->id]);

        Payment::factory()->create([
            'user_id' => $hit->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => $team->title,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-07-01',
            'operation_date' => '2026-07-10 12:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $miss->id,
            'partner_id' => $this->partner->id,
            'team_id' => $other->id,
            'team_title' => $other->title,
            'user_name' => null,
            'summ_cents' => 20000,
            'payment_month' => '2026-08-01',
            'operation_date' => '2026-08-10 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('ГруппаХармDtMon'));

        $this->assertContains('2026-07', $keys);
        $this->assertNotContains('2026-08', $keys);
    }

    public function test_monthly_search_by_russian_month_title_and_year(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезЦифрDtMonTitle',
            'name' => 'Студент',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-01-01',
            'operation_date' => '2026-01-15 12:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 20000,
            'payment_month' => '2025-01-01',
            'operation_date' => '2025-01-15 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('Январь 2026'));

        $this->assertContains('2026-01', $keys);
        $this->assertNotContains('2025-01', $keys);
    }

    public function test_monthly_search_does_not_match_amount(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезЦифрВФамилииDtMon',
            'name' => 'Студент',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 888800,
            'payment_month' => '2026-09-01',
            'operation_date' => '2026-09-15 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('8888'));

        $this->assertNotContains('2026-09', $keys);
    }

    public function test_monthly_search_does_not_treat_percent_as_like_wildcard(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПроцентDtMon',
            'name' => 'Иван',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-10-01',
            'operation_date' => '2026-10-15 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('%'));

        $this->assertNotContains('2026-10', $keys);
    }

    public function test_monthly_search_does_not_show_other_partner_months(): void
    {
        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname' => 'ЧужойПартнёрDtMon',
            'name' => 'Иван',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $foreign->id,
            'partner_id' => $this->foreignPartner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-11-01',
            'operation_date' => '2026-11-15 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('ЧужойПартнёрDtMon'));

        $this->assertNotContains('2026-11', $keys);
    }

    public function test_monthly_detail_search_by_lastname_finds_payment(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ХармУникDtMonDet',
            'name' => 'Иван',
            'is_enabled' => 1,
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ДругойDtMonDet',
            'name' => 'Пётр',
            'is_enabled' => 1,
        ]);

        $pHit = Payment::factory()->create([
            'user_id' => $hit->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-02-01',
            'operation_date' => '2026-02-10 12:00:00',
        ]);
        $pMiss = Payment::factory()->create([
            'user_id' => $miss->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 20000,
            'payment_month' => '2026-02-01',
            'operation_date' => '2026-02-11 12:00:00',
        ]);

        $ids = $this->monthlyPaymentIdsFromAjax('2026-02', $this->monthlyDetailSearchQuery('ХармУникDtMonDet'));

        $this->assertContains($pHit->id, $ids);
        $this->assertNotContains($pMiss->id, $ids);
    }

    public function test_monthly_page_marks_count_and_sum_not_searchable(): void
    {
        $html = $this->get(route('reports.payments.monthly'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create(\'#payments-monthly-table\'', false)
            ->getContent();

        $this->assertMatchesRegularExpression("/name:\\s*'month_title'/", $html);
        $this->assertDoesNotMatchRegularExpression(
            "/name:\\s*'month_title'\\s*,\\s*searchable:\\s*false/",
            $html
        );
        $this->assertMatchesRegularExpression("/name:\\s*'payments_count'[^\\n]*searchable:\\s*false/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'total_sum'[^\\n]*searchable:\\s*false/", $html);
    }

    public function test_intents_search_by_lastname_finds_row(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ХармУникDtPi',
            'name' => 'Иван',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ДругойDtPi',
            'name' => 'Пётр',
        ]);

        $iHit = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $hit->id,
            'provider_inv_id' => 811000001,
            'out_sum_cents' => 10000,
        ]);
        $iMiss = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $miss->id,
            'provider_inv_id' => 811000002,
            'out_sum_cents' => 20000,
        ]);

        $ids = $this->intentIdsFromAjax($this->intentsSearchQuery('ХармУникDtPi'));

        $this->assertContains($iHit->id, $ids);
        $this->assertNotContains($iMiss->id, $ids);
    }

    public function test_intents_search_by_firstname_finds_row(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'СидоровDtPi',
            'name' => 'УникИмяDtPi',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'СидоровDtPi',
            'name' => 'Пётр',
        ]);

        $iHit = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $hit->id,
            'provider_inv_id' => 811000011,
            'out_sum_cents' => 10000,
        ]);
        $iMiss = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $miss->id,
            'provider_inv_id' => 811000012,
            'out_sum_cents' => 20000,
        ]);

        $ids = $this->intentIdsFromAjax($this->intentsSearchQuery('УникИмяDtPi'));

        $this->assertContains($iHit->id, $ids);
        $this->assertNotContains($iMiss->id, $ids);
    }

    public function test_intents_search_by_partner_title_finds_row(): void
    {
        $this->partner->title = 'ПартнёрХармDtPi';
        $this->partner->save();
        $this->foreignPartner->title = 'ЧужойПартнёрDtPiTitle';
        $this->foreignPartner->save();

        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезСовпаденияDtPi',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname' => 'ТожеБезDtPi',
        ]);

        $iHit = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $hit->id,
            'provider_inv_id' => 811000021,
            'out_sum_cents' => 10000,
        ]);
        $iMiss = PaymentIntent::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'user_id' => $miss->id,
            'provider_inv_id' => 811000022,
            'out_sum_cents' => 20000,
        ]);

        $ids = $this->intentIdsFromAjax($this->intentsSearchQuery('ПартнёрХармDtPi'));

        $this->assertContains($iHit->id, $ids);
        $this->assertNotContains($iMiss->id, $ids);
    }

    public function test_intents_search_by_provider_inv_id_finds_row(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезЦифрDtPiInv',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ТожеБезDtPiInv',
        ]);

        $iHit = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $hit->id,
            'provider_inv_id' => 811000033,
            'out_sum_cents' => 10000,
        ]);
        $iMiss = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $miss->id,
            'provider_inv_id' => 811000044,
            'out_sum_cents' => 20000,
        ]);

        $ids = $this->intentIdsFromAjax($this->intentsSearchQuery('811000033'));

        $this->assertContains($iHit->id, $ids);
        $this->assertNotContains($iMiss->id, $ids);
    }

    public function test_intents_search_does_not_match_amount_or_user_agent(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезЦифрВФамилииDtPi',
            'name' => 'Студент',
        ]);
        $intent = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'provider_inv_id' => 811000055,
            'out_sum_cents' => 777700,
            'client_user_agent' => 'UniqUaDtPiBot/9.9',
            'status' => 'paid',
        ]);

        foreach (['7777', 'UniqUaDtPiBot'] as $needle) {
            $ids = $this->intentIdsFromAjax($this->intentsSearchQuery($needle));
            $this->assertNotContains(
                $intent->id,
                $ids,
                "Поиск «{$needle}» не должен цеплять сумму/UA"
            );
        }
    }

    public function test_intents_search_does_not_treat_percent_as_like_wildcard(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПроцентDtPi',
            'name' => 'Иван',
        ]);
        $intent = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'provider_inv_id' => 811000066,
            'out_sum_cents' => 10000,
        ]);

        $ids = $this->intentIdsFromAjax($this->intentsSearchQuery('%'));

        $this->assertNotContains($intent->id, $ids);
    }

    public function test_intents_page_keeps_fio_partner_and_inv_searchable(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('reports.payment-intents.index'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create(\'#payment-intents-table\'', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            "/name:\\s*'user_name'\\s*,\\s*searchable:\\s*false/",
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            "/name:\\s*'partner_title'\\s*,\\s*searchable:\\s*false/",
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            "/name:\\s*'provider_inv_id'\\s*,\\s*searchable:\\s*false/",
            $html
        );
        $this->assertMatchesRegularExpression("/name:\\s*'out_sum'[^\\n]*searchable:\\s*false/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'created_at'[^\\n]*searchable:\\s*false/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'paid_at'[^\\n]*searchable:\\s*false/", $html);
        $this->assertStringNotContainsString('searching: false', $html);
        $this->assertStringContainsString('$form.on(\'submit\'', $html);
        $this->assertStringContainsString('$(\'#paymentIntentsResetBtn\').on(\'click\'', $html);
    }

    public function test_empty_and_whitespace_monthly_search_does_not_hide_months(): void
    {
        $a = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПустойПоискADtMon',
            'is_enabled' => 1,
        ]);
        $b = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПустойПоискBDtMon',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $a->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-01-01',
            'operation_date' => '2026-01-10 12:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $b->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 20000,
            'payment_month' => '2026-02-01',
            'operation_date' => '2026-02-10 12:00:00',
        ]);

        foreach (['', '   '] as $needle) {
            $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery($needle));
            $this->assertContains('2026-01', $keys, "needle=".json_encode($needle));
            $this->assertContains('2026-02', $keys, "needle=".json_encode($needle));
        }
    }

    public function test_monthly_search_still_matches_legacy_snapshot_user_name(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'НеТоИмяDtMon',
            'name' => 'Студент',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'user_name' => 'СнимокУникDtMon',
            'summ_cents' => 10000,
            'payment_month' => '2026-12-01',
            'operation_date' => '2026-12-10 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('СнимокУникDtMon'));

        $this->assertContains('2026-12', $keys);
    }

    public function test_monthly_search_by_full_name_and_year_month_key(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФамилияDtMon',
            'name' => 'ИмяDtMon',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $hit->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2024-11-01',
            'operation_date' => '2024-11-10 12:00:00',
        ]);

        $byFio = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('ФамилияDtMon ИмяDtMon'));
        $this->assertContains('2024-11', $byFio);

        $byKey = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('2024-11'));
        $this->assertContains('2024-11', $byKey);
    }

    public function test_monthly_detail_search_does_not_match_amount(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезЦифрDtMonDetAmt',
            'is_enabled' => 1,
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 888800,
            'payment_month' => '2026-02-01',
            'operation_date' => '2026-02-10 12:00:00',
        ]);

        $ids = $this->monthlyPaymentIdsFromAjax('2026-02', $this->monthlyDetailSearchQuery('8888'));

        $this->assertNotContains($payment->id, $ids);
    }

    public function test_empty_and_whitespace_intents_search_does_not_hide_rows(): void
    {
        $a = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПустойПоискADtPi',
        ]);
        $b = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПустойПоискBDtPi',
        ]);
        $iA = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $a->id,
            'provider_inv_id' => 833000001,
            'out_sum_cents' => 10000,
        ]);
        $iB = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $b->id,
            'provider_inv_id' => 833000002,
            'out_sum_cents' => 20000,
        ]);

        foreach (['', '   '] as $needle) {
            $ids = $this->intentIdsFromAjax($this->intentsSearchQuery($needle));
            $this->assertContains($iA->id, $ids, "needle=".json_encode($needle));
            $this->assertContains($iB->id, $ids, "needle=".json_encode($needle));
        }
    }

    public function test_intents_search_by_full_name_and_numeric_id(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФамилияDtPi',
            'name' => 'ИмяDtPi',
        ]);
        $iHit = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $hit->id,
            'provider_inv_id' => 833000011,
            'out_sum_cents' => 10000,
        ]);

        $byFio = $this->intentIdsFromAjax($this->intentsSearchQuery('ФамилияDtPi ИмяDtPi'));
        $this->assertContains($iHit->id, $byFio);

        $byId = $this->intentIdsFromAjax($this->intentsSearchQuery((string) $iHit->id));
        $this->assertContains($iHit->id, $byId);
    }

    public function test_admin_intents_search_does_not_show_other_partner_rows(): void
    {
        $this->grantIntentsView($this->user);
        $this->asAdmin();

        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname' => 'ЧужойПартнёрDtPiAdm',
        ]);
        $iForeign = PaymentIntent::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'user_id' => $foreign->id,
            'provider_inv_id' => 833000022,
            'out_sum_cents' => 10000,
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payment-intents.data', $this->intentsSearchQuery('ЧужойПартнёрDtPiAdm')))
            ->assertOk()
            ->json();
        $ids = collect($json['data'] ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all();

        $this->assertNotContains($iForeign->id, $ids);
    }

    public function test_monthly_page_keeps_search_enabled_and_nested_table_without_search_box(): void
    {
        $html = $this->get(route('reports.payments.monthly'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('searching: false', $html);
        $this->assertStringContainsString("dom: 'rtip'", $html);
        $this->assertStringContainsString('$payMonthlyFiltersForm.on(\'submit\'', $html);
        $this->assertStringContainsString('$(\'#paymentsMonthlyFiltersResetBtn\').on(\'click\'', $html);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true });', $html);
    }

    public function test_monthly_search_february_title_does_not_match_january(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезМесяцаВФамилииDtMon',
            'name' => 'Студент',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-01-01',
            'operation_date' => '2026-01-15 12:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 20000,
            'payment_month' => '2026-02-01',
            'operation_date' => '2026-02-15 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('Февраль 2026'));

        $this->assertContains('2026-02', $keys);
        $this->assertNotContains('2026-01', $keys);
    }

    public function test_monthly_search_does_not_treat_underscore_as_like_wildcard(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезПодчёркиванияDtMon',
            'name' => 'Иван',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-03-01',
            'operation_date' => '2026-03-15 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('_'));

        $this->assertNotContains('2026-03', $keys);
    }

    public function test_monthly_page_without_locations_view_hides_location_filter_flag(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        \Illuminate\Support\Facades\DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('reports.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $html = $this->get(route('reports.payments.monthly'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('var canViewLocations = false;', $html);
        $this->assertStringContainsString('if (canViewLocations) {', $html);
        $this->assertStringContainsString('$payMonthlyFiltersForm.on(\'submit\'', $html);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true });', $html);
        $this->assertStringNotContainsString('searching: false', $html);
        $this->assertStringContainsString("dom: 'rtip'", $html);
        $this->assertDoesNotMatchRegularExpression(
            "/name:\\s*'user_name'\\s*,\\s*searchable:\\s*false/",
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            "/name:\\s*'team_title'\\s*,\\s*searchable:\\s*false/",
            $html
        );
    }

    public function test_monthly_form_filter_and_search_apply_together(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФильтрИПоискDtMon',
            'name' => 'Иван',
            'is_enabled' => 1,
        ]);
        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФильтрИПоискDtMon',
            'name' => 'Пётр',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $hit->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-04-01',
            'operation_date' => '2026-04-10 12:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $other->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 20000,
            'payment_month' => '2026-05-01',
            'operation_date' => '2026-05-10 12:00:00',
        ]);

        $keys = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('Иван', [
            'filter_user_id' => $hit->id,
        ]));
        $this->assertContains('2026-04', $keys);
        $this->assertNotContains('2026-05', $keys);

        $keysMiss = $this->monthlyMonthKeysFromAjax($this->monthlySearchQuery('Пётр', [
            'filter_user_id' => $hit->id,
        ]));
        $this->assertNotContains('2026-04', $keysMiss);
        $this->assertNotContains('2026-05', $keysMiss);
    }

    public function test_monthly_detail_search_by_team_title_finds_payment(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаХармDtMonDet',
        ]);
        $other = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ДругаяГруппаDtMonDet',
        ]);
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезСовпаденияDtMonDet',
            'is_enabled' => 1,
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ТожеБезDtMonDet',
            'is_enabled' => 1,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($hit, [(int) $team->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($miss, [(int) $other->id]);

        $pHit = Payment::factory()->create([
            'user_id' => $hit->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => $team->title,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-06-01',
            'operation_date' => '2026-06-10 12:00:00',
        ]);
        $pMiss = Payment::factory()->create([
            'user_id' => $miss->id,
            'partner_id' => $this->partner->id,
            'team_id' => $other->id,
            'team_title' => $other->title,
            'user_name' => null,
            'summ_cents' => 20000,
            'payment_month' => '2026-06-01',
            'operation_date' => '2026-06-11 12:00:00',
        ]);

        $ids = $this->monthlyPaymentIdsFromAjax('2026-06', $this->monthlyDetailSearchQuery('ГруппаХармDtMonDet'));

        $this->assertContains($pHit->id, $ids);
        $this->assertNotContains($pMiss->id, $ids);
    }

    public function test_intents_search_does_not_treat_underscore_as_like_wildcard(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезПодчёркиванияDtPi',
            'name' => 'Иван',
        ]);
        $intent = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'provider_inv_id' => 833000033,
            'out_sum_cents' => 10000,
        ]);

        $ids = $this->intentIdsFromAjax($this->intentsSearchQuery('_'));

        $this->assertNotContains($intent->id, $ids);
    }

    public function test_intents_form_filter_and_search_apply_together(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФильтрИПоискDtPi',
            'name' => 'Иван',
        ]);
        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФильтрИПоискDtPi',
            'name' => 'Пётр',
        ]);
        $iHit = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $hit->id,
            'provider_inv_id' => 833000044,
            'out_sum_cents' => 10000,
        ]);
        $iOther = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $other->id,
            'provider_inv_id' => 833000045,
            'out_sum_cents' => 20000,
        ]);

        $ids = $this->intentIdsFromAjax($this->intentsSearchQuery('Иван', [
            'user_id' => $hit->id,
        ]));
        $this->assertContains($iHit->id, $ids);
        $this->assertNotContains($iOther->id, $ids);

        $idsMiss = $this->intentIdsFromAjax($this->intentsSearchQuery('Пётр', [
            'user_id' => $hit->id,
        ]));
        $this->assertNotContains($iHit->id, $idsMiss);
        $this->assertNotContains($iOther->id, $idsMiss);
    }

    private function grantIntentsView(User $actor): void
    {
        \Illuminate\Support\Facades\DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('reports.payment.intents.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
