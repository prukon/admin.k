<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Location;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Глобальный поиск DataTables в отчётах «Платежи» и LTV:
 * UX-баги (пустая выдача по ФИО / Ajax error tn/7), доступ, границы поиска.
 */
final class ReportsDatatableSearchFeatureTest extends CrmTestCase
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
     * Колонки как в живой таблице «Платежи»: summ/даты searchable,
     * плюс location_title — до фикса Yajra autoFilter давал пустую выдачу или 500.
     *
     * @return list<array<string, string>>
     */
    private function paymentsBrowserColumns(): array
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
            $col('DT_RowIndex', 'DT_RowIndex', false, false),
            $col('user_name', 'user_name'),
            $col('team_title', 'team_title'),
            $col('location_title', 'location_title'),
            $col('summ', 'summ'),
            $col('payment_month', 'payment_month'),
            $col('operation_date', 'operation_date'),
            $col('payment_provider', 'payment_provider', false, false),
        ];
    }

    /**
     * Колонки как в ltv-table: агрегаты searchable=true (регресс filter(..., true) → 42S22).
     *
     * @return list<array<string, string>>
     */
    private function ltvBrowserColumns(): array
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
            $col('user_name', 'user_name'),
            $col('team_title', 'team_title'),
            $col('total_price', 'total_price'),
            $col('payment_count', 'payment_count'),
            $col('first_payment_date', 'first_payment_date'),
            $col('last_payment_date', 'last_payment_date'),
            $col('is_enabled', 'is_enabled'),
            $col('user_id', 'user_id', false),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function paymentsSearchQuery(string $needle, array $extra = []): array
    {
        return array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'columns' => $this->paymentsBrowserColumns(),
            'search' => ['value' => $needle],
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ltvSearchQuery(string $needle, array $extra = []): array
    {
        return array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'columns' => $this->ltvBrowserColumns(),
            'search' => ['value' => $needle],
        ], $extra);
    }

    /**
     * @return list<int>
     */
    private function paymentIdsFromAjax(array $query): array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('payments.getPayments', $query))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        return collect($json['data'] ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @return list<int>
     */
    private function ltvUserIdsFromAjax(array $query): array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.data', $query))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        return collect($json['data'] ?? [])->pluck('user_id')->map(fn ($v) => (int) $v)->all();
    }

    private function grantLocationsView(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array{method: string, url: string, headers?: array<string, string>}>
     */
    private function searchEndpointsPayload(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('payments'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.ltv'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('payments.getPayments', $this->paymentsSearchQuery('тест')),
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.ltv.data', $this->ltvSearchQuery('тест')),
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
        ];
    }

    public function test_guest_cannot_open_or_search_payments_and_ltv(): void
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

    public function test_user_without_reports_view_cannot_search_payments_or_ltv(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);

        foreach ($this->searchEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без reports.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_gets_datatables_json_when_searching_payments_and_ltv(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('payments.getPayments', $this->paymentsSearchQuery('')))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.data', $this->ltvSearchQuery('')))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_payments_search_without_ajax_header_is_not_found(): void
    {
        $this->get(route('payments.getPayments', $this->paymentsSearchQuery('Иванов')))
            ->assertNotFound();
    }

    public function test_ltv_search_without_ajax_header_is_not_found(): void
    {
        $this->get(route('reports.ltv.data', $this->ltvSearchQuery('Иванов')))
            ->assertNotFound();
    }

    public function test_unsupported_methods_on_search_endpoints_do_not_return_500(): void
    {
        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            foreach ([
                route('payments.getPayments', $this->paymentsSearchQuery('x')),
                route('reports.ltv.data', $this->ltvSearchQuery('x')),
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

    /**
     * UX: поле поиска не находило фамилию, потому что Yajra смотрел payments.user_name.
     */
    public function test_payments_search_by_lastname_finds_row_when_snapshot_name_is_empty(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ХармУникDtPay',
            'name' => 'Иван',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ДругойDtPay',
            'name' => 'Пётр',
        ]);

        $pHit = Payment::factory()->create([
            'user_id' => $hit->id,
            'user_name' => null,
            'summ_cents' => 10000,
        ]);
        $pMiss = Payment::factory()->create([
            'user_id' => $miss->id,
            'user_name' => 'СовсемДругоеИмя',
            'summ_cents' => 20000,
        ]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery('ХармУникDtPay'));

        $this->assertContains($pHit->id, $ids);
        $this->assertNotContains($pMiss->id, $ids);
    }

    public function test_payments_search_by_firstname_finds_student(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'СидоровDt',
            'name' => 'УникИмяDtPay',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'СидоровDt',
            'name' => 'Пётр',
        ]);

        $pHit = Payment::factory()->create([
            'user_id' => $hit->id,
            'user_name' => null,
            'summ_cents' => 10000,
        ]);
        $pMiss = Payment::factory()->create([
            'user_id' => $miss->id,
            'user_name' => null,
            'summ_cents' => 20000,
        ]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery('УникИмяDtPay'));

        $this->assertContains($pHit->id, $ids);
        $this->assertNotContains($pMiss->id, $ids);
    }

    public function test_payments_search_by_full_name_finds_student(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФамилияDtPay',
            'name' => 'ИмяDtPay',
        ]);
        $pHit = Payment::factory()->create([
            'user_id' => $hit->id,
            'user_name' => null,
            'summ_cents' => 10000,
        ]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery('ФамилияDtPay ИмяDtPay'));

        $this->assertContains($pHit->id, $ids);
    }

    public function test_payments_search_still_matches_legacy_snapshot_user_name(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'НеТоИмяDt',
            'name' => 'Студент',
        ]);
        $pHit = Payment::factory()->create([
            'user_id' => $student->id,
            'user_name' => 'СнимокУникDtPay',
            'summ_cents' => 10000,
        ]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery('СнимокУникDtPay'));

        $this->assertContains($pHit->id, $ids);
    }

    public function test_payments_search_by_team_title_finds_paid_group(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаХармDtPay',
        ]);
        $other = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ДругаяГруппаDtPay',
        ]);
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезСовпаденияDtPay',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ТожеБезDtPay',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($hit, [(int) $team->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($miss, [(int) $other->id]);

        $pHit = Payment::factory()->create([
            'user_id' => $hit->id,
            'team_id' => $team->id,
            'team_title' => $team->title,
            'user_name' => null,
            'summ_cents' => 10000,
        ]);
        $pMiss = Payment::factory()->create([
            'user_id' => $miss->id,
            'team_id' => $other->id,
            'team_title' => $other->title,
            'user_name' => null,
            'summ_cents' => 20000,
        ]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery('ГруппаХармDtPay'));

        $this->assertContains($pHit->id, $ids);
        $this->assertNotContains($pMiss->id, $ids);
    }

    public function test_payments_search_does_not_match_amount_month_or_location(): void
    {
        $this->grantLocationsView();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'УникЛокПоискDt',
            'is_enabled' => true,
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезЦифрВФамилииDt',
            'name' => 'Студент',
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'user_name' => null,
            'location_id' => $location->id,
            'summ_cents' => 888800,
            'payment_month' => '2026-03-01',
            'operation_date' => '2026-03-15 12:00:00',
        ]);

        foreach (['8888', '2026-03', 'УникЛокПоискDt'] as $needle) {
            $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery($needle));
            $this->assertNotContains(
                $payment->id,
                $ids,
                "Поиск «{$needle}» не должен цеплять сумму/месяц/локацию"
            );
        }
    }

    public function test_payments_search_does_not_treat_percent_as_like_wildcard(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПроцентDtPay',
            'name' => 'Иван',
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'user_name' => null,
            'summ_cents' => 10000,
        ]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery('%'));

        $this->assertNotContains($payment->id, $ids);
    }

    public function test_payments_search_does_not_show_other_partner_students(): void
    {
        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname' => 'ЧужойПартнёрDtPay',
            'name' => 'Иван',
        ]);
        $pForeign = Payment::factory()->create([
            'user_id' => $foreign->id,
            'partner_id' => $this->foreignPartner->id,
            'user_name' => null,
            'summ_cents' => 10000,
        ]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery('ЧужойПартнёрDtPay'));

        $this->assertNotContains($pForeign->id, $ids);
    }

    public function test_empty_payments_search_does_not_hide_students(): void
    {
        $a = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПустойПоискADt',
        ]);
        $b = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПустойПоискBDt',
        ]);
        $pA = Payment::factory()->create(['user_id' => $a->id, 'user_name' => null, 'summ_cents' => 10000]);
        $pB = Payment::factory()->create(['user_id' => $b->id, 'user_name' => null, 'summ_cents' => 20000]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery(''));

        $this->assertContains($pA->id, $ids);
        $this->assertContains($pB->id, $ids);
    }

    /**
     * UX: Ajax error tn/7 — Yajra autoFilter искал payments.payment_count (42S22).
     */
    public function test_ltv_search_with_browser_columns_does_not_fail_and_finds_lastname(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ХармУникDtLtv',
            'name' => 'Иван',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ДругойDtLtv',
            'name' => 'Пётр',
        ]);
        Payment::factory()->create(['user_id' => $hit->id, 'summ_cents' => 10000]);
        Payment::factory()->create(['user_id' => $miss->id, 'summ_cents' => 20000]);

        $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery('ХармУникDtLtv'));

        $this->assertContains($hit->id, $ids);
        $this->assertNotContains($miss->id, $ids);
    }

    public function test_ltv_search_by_firstname_finds_student(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'СидоровDtLtv',
            'name' => 'УникИмяDtLtv',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'СидоровDtLtv',
            'name' => 'Пётр',
        ]);
        Payment::factory()->create(['user_id' => $hit->id, 'summ_cents' => 10000]);
        Payment::factory()->create(['user_id' => $miss->id, 'summ_cents' => 20000]);

        $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery('УникИмяDtLtv'));

        $this->assertContains($hit->id, $ids);
        $this->assertNotContains($miss->id, $ids);
    }

    public function test_ltv_search_by_team_title_finds_student(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаХармDtLtv',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезСовпаденияDtLtv',
            'team_id' => $team->id,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        Payment::factory()->create(['user_id' => $student->id, 'summ_cents' => 10000]);

        $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery('ГруппаХармDtLtv'));

        $this->assertContains($student->id, $ids);
    }

    public function test_ltv_search_does_not_match_amount(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезЦифрDtLtv',
            'name' => 'Студент',
        ]);
        Payment::factory()->create(['user_id' => $student->id, 'summ_cents' => 777700]);

        $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery('7777'));

        $this->assertNotContains($student->id, $ids);
    }

    public function test_payments_page_keeps_name_and_team_searchable_and_hides_the_rest(): void
    {
        $this->grantLocationsView();

        $html = $this->get(route('payments'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create(\'#payments-table\'', false)
            ->getContent();

        $this->assertMatchesRegularExpression("/name:\\s*'user_name'/", $html);
        $this->assertDoesNotMatchRegularExpression(
            "/name:\\s*'user_name'\\s*,\\s*searchable:\\s*false/",
            $html
        );
        $this->assertMatchesRegularExpression("/name:\\s*'team_title'/", $html);
        $this->assertDoesNotMatchRegularExpression(
            "/name:\\s*'team_title'\\s*,\\s*searchable:\\s*false/",
            $html
        );
        $this->assertMatchesRegularExpression("/name:\\s*'summ'\\s*,\\s*searchable:\\s*false/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'payment_month'\\s*,\\s*searchable:\\s*false/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'operation_date'\\s*,\\s*searchable:\\s*false/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'location_title'\\s*,\\s*searchable:\\s*false/", $html);
    }

    public function test_ltv_page_marks_aggregate_columns_not_searchable(): void
    {
        $html = $this->get(route('reports.ltv'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create(\'#ltv-table\'', false)
            ->getContent();

        $this->assertMatchesRegularExpression("/name:\\s*'user_name'/", $html);
        $this->assertDoesNotMatchRegularExpression(
            "/name:\\s*'user_name'\\s*,\\s*searchable:\\s*false/",
            $html
        );
        $this->assertMatchesRegularExpression("/name:\\s*'team_title'/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'total_price'[^\\n]*searchable:\\s*false/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'payment_count'[^\\n]*searchable:\\s*false/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'first_payment_date'\\s*,\\s*searchable:\\s*false/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'last_payment_date'\\s*,\\s*searchable:\\s*false/", $html);
        $this->assertMatchesRegularExpression("/name:\\s*'is_enabled'\\s*,\\s*searchable:\\s*false/", $html);
        $this->assertStringNotContainsString('searching: false', $html);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true });', $html);
        $this->assertStringContainsString('$ltvFiltersForm.on(\'submit\'', $html);
        $this->assertStringContainsString('$(\'#ltvReportFiltersResetBtn\').on(\'click\'', $html);
    }

    public function test_ltv_search_by_full_name_finds_student(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФамилияDtLtv',
            'name' => 'ИмяDtLtv',
        ]);
        Payment::factory()->create(['user_id' => $hit->id, 'summ_cents' => 10000]);

        $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery('ФамилияDtLtv ИмяDtLtv'));

        $this->assertContains($hit->id, $ids);
    }

    public function test_empty_and_whitespace_ltv_search_does_not_hide_students(): void
    {
        $a = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПустойПоискADtLtv',
        ]);
        $b = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПустойПоискBDtLtv',
        ]);
        Payment::factory()->create(['user_id' => $a->id, 'summ_cents' => 10000]);
        Payment::factory()->create(['user_id' => $b->id, 'summ_cents' => 20000]);

        foreach (['', '   '] as $needle) {
            $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery($needle));
            $this->assertContains($a->id, $ids, "needle=".json_encode($needle));
            $this->assertContains($b->id, $ids, "needle=".json_encode($needle));
        }
    }

    public function test_ltv_search_does_not_treat_percent_as_like_wildcard(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПроцентDtLtv',
            'name' => 'Иван',
        ]);
        Payment::factory()->create(['user_id' => $student->id, 'summ_cents' => 10000]);

        $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery('%'));

        $this->assertNotContains($student->id, $ids);
    }

    public function test_ltv_search_does_not_show_other_partner_students(): void
    {
        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname' => 'ЧужойПартнёрDtLtv',
            'name' => 'Иван',
        ]);
        Payment::factory()->create([
            'user_id' => $foreign->id,
            'partner_id' => $this->foreignPartner->id,
            'summ_cents' => 10000,
        ]);

        $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery('ЧужойПартнёрDtLtv'));

        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_whitespace_payments_search_behaves_like_empty(): void
    {
        $a = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ПробелПоискADt',
        ]);
        $pA = Payment::factory()->create(['user_id' => $a->id, 'user_name' => null, 'summ_cents' => 10000]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery('   '));

        $this->assertContains($pA->id, $ids);
    }

    public function test_payments_search_does_not_treat_underscore_as_like_wildcard(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезПодчёркиванияDt',
            'name' => 'Иван',
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'user_name' => null,
            'summ_cents' => 10000,
        ]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery('_'));

        $this->assertNotContains($payment->id, $ids);
    }

    public function test_payments_search_narrows_records_filtered_but_keeps_records_total(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФильтрCountDtPay',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ДругойCountDtPay',
        ]);
        Payment::factory()->create(['user_id' => $hit->id, 'user_name' => null, 'summ_cents' => 10000]);
        Payment::factory()->create(['user_id' => $miss->id, 'user_name' => null, 'summ_cents' => 20000]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('payments.getPayments', $this->paymentsSearchQuery('ФильтрCountDtPay')))
            ->assertOk()
            ->json();

        $this->assertGreaterThanOrEqual(2, (int) $json['recordsTotal']);
        $this->assertSame(1, (int) $json['recordsFiltered']);
    }

    public function test_payments_form_filter_and_search_apply_together(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФильтрИПоискDt',
            'name' => 'Иван',
        ]);
        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФильтрИПоискDt',
            'name' => 'Пётр',
        ]);
        $pHit = Payment::factory()->create(['user_id' => $hit->id, 'user_name' => null, 'summ_cents' => 10000]);
        $pOther = Payment::factory()->create(['user_id' => $other->id, 'user_name' => null, 'summ_cents' => 20000]);

        $ids = $this->paymentIdsFromAjax($this->paymentsSearchQuery('Иван', [
            'filter_user_id' => $hit->id,
        ]));
        $this->assertContains($pHit->id, $ids);
        $this->assertNotContains($pOther->id, $ids);

        $idsMiss = $this->paymentIdsFromAjax($this->paymentsSearchQuery('Пётр', [
            'filter_user_id' => $hit->id,
        ]));
        $this->assertNotContains($pHit->id, $idsMiss);
        $this->assertNotContains($pOther->id, $idsMiss);
    }

    public function test_payments_page_without_locations_view_hides_location_filter_flag(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('reports.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $html = $this->get(route('payments'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('const canViewLocations = false;', $html);
        $this->assertStringContainsString('if (canViewLocations) {', $html);
        $this->assertStringContainsString('$payFiltersForm.on(\'submit\'', $html);
        $this->assertStringContainsString('dtApi.reload();', $html);
        $this->assertStringContainsString('$(\'#paymentsReportFiltersResetBtn\').on(\'click\'', $html);
        $this->assertStringNotContainsString('searching: false', $html);
    }

    public function test_ltv_nested_payments_ajax_with_draw_returns_datatables_json(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ВложенныйDtLtv',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'summ_cents' => 888800,
            'payment_month' => '2026-03-01',
        ]);

        $col = static function (string $data, string $name, bool $searchable = true): array {
            return [
                'data' => $data,
                'name' => $name,
                'searchable' => $searchable ? 'true' : 'false',
                'orderable' => 'true',
            ];
        };

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', [
                'user' => $student->id,
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'columns' => [
                    $col('operation_date', 'operation_date'),
                    $col('summ', 'summ'),
                    $col('payment_month', 'payment_month'),
                    $col('payment_provider', 'payment_provider', false),
                ],
                'search' => ['value' => ''],
            ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_ltv_search_does_not_match_first_or_last_payment_date(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезДатВФамилииDtLtv',
            'name' => 'Студент',
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'summ_cents' => 10000,
            'payment_month' => '2026-03-01',
            'operation_date' => '2026-03-15 12:00:00',
        ]);

        foreach (['2026-03', '15.03.2026'] as $needle) {
            $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery($needle));
            $this->assertNotContains(
                $student->id,
                $ids,
                "Поиск «{$needle}» не должен цеплять даты платежей"
            );
        }
    }

    public function test_ltv_search_does_not_treat_underscore_as_like_wildcard(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'БезПодчёркиванияDtLtv',
            'name' => 'Иван',
        ]);
        Payment::factory()->create(['user_id' => $student->id, 'summ_cents' => 10000]);

        $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery('_'));

        $this->assertNotContains($student->id, $ids);
    }

    public function test_ltv_page_without_locations_view_hides_location_filter_flag(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('reports.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $html = $this->get(route('reports.ltv'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('var canViewLocations = false;', $html);
        $this->assertStringContainsString('if (canViewLocations) {', $html);
        $this->assertStringContainsString('$ltvFiltersForm.on(\'submit\'', $html);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true });', $html);
        $this->assertStringNotContainsString('searching: false', $html);
    }

    public function test_ltv_form_filter_and_search_apply_together(): void
    {
        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФильтрИПоискDtLtv',
            'name' => 'Иван',
        ]);
        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ФильтрИПоискDtLtv',
            'name' => 'Пётр',
        ]);
        Payment::factory()->create(['user_id' => $hit->id, 'summ_cents' => 10000]);
        Payment::factory()->create(['user_id' => $other->id, 'summ_cents' => 20000]);

        $ids = $this->ltvUserIdsFromAjax($this->ltvSearchQuery('Иван', [
            'filter_user_id' => $hit->id,
        ]));
        $this->assertContains($hit->id, $ids);
        $this->assertNotContains($other->id, $ids);

        $idsMiss = $this->ltvUserIdsFromAjax($this->ltvSearchQuery('Пётр', [
            'filter_user_id' => $hit->id,
        ]));
        $this->assertNotContains($hit->id, $idsMiss);
        $this->assertNotContains($other->id, $idsMiss);
    }
}
