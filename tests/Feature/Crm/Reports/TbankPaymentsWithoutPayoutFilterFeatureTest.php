<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фильтр «Не было выплаты» на вкладке «Платежи T‑Bank».
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TbankPaymentsWithoutPayoutFilterFeatureTest extends CrmTestCase
{
    private const INVALID_MESSAGE = 'Поле «Не было выплаты» содержит недопустимое значение.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_cannot_use_without_payout_filter_on_any_endpoint(): void
    {
        Auth::logout();

        foreach ($this->filterEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode(), $item['url']);
        }
    }

    public function test_user_without_report_permission_gets_403_on_without_payout_filter(): void
    {
        $denied = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->filterEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без reports.tbank.payments.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_without_payouts_manage_can_filter_payments_without_payout(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);

        $this->assertFalse($actor->can('tbank.payouts.manage'));
        $this->assertFalse($actor->can('reports.additional.value.view'));

        $own = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 12000]);
        $withPayout = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 88000]);
        $this->makePayout($withPayout, ['status' => 'COMPLETED']);

        $this->get(route('reports.tbank-payments.index', ['without_payout' => 1]))
            ->assertOk()
            ->assertSee('id="tp-filter-without-payout"', false)
            ->assertSee('Не было выплаты', false);

        $this->get(route('reports.tbank-payments.total', ['without_payout' => 1]))
            ->assertOk()
            ->assertJsonPath('total_raw', 120);

        $ids = $this->datatableIds(['without_payout' => 1]);
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($withPayout->id, $ids);
    }

    public function test_superadmin_without_payout_endpoints_return_200_not_empty(): void
    {
        $this->asSuperadmin();
        $this->makePayment(['amount' => 15000]);

        foreach ($this->authorizedFilterVariants() as $params) {
            $index = $this->get(route('reports.tbank-payments.index', $params));
            $this->assertSame(200, $index->getStatusCode());
            $this->assertNotSame('', trim((string) $index->getContent()));

            $total = $this->get(route('reports.tbank-payments.total', $params));
            $this->assertSame(200, $total->getStatusCode());
            $total->assertJsonStructure(['total_formatted', 'total_raw']);
            $this->assertNotSame('', (string) $total->json('total_formatted'));

            $data = $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('reports.tbank-payments.data', array_merge($this->baseDataTableParams(), $params)));
            $this->assertSame(200, $data->getStatusCode());
            $data->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
            $this->assertNotSame('', trim((string) $data->getContent()));
        }
    }

    public function test_user_without_organization_is_logged_out_from_without_payout_page(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        $response = $this->from(route('login'))
            ->get(route('reports.tbank-payments.index', ['without_payout' => 1]));

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Ваша организация недоступна.',
        ]);
    }

    public function test_unsupported_methods_with_without_payout_do_not_return_500_or_empty_200(): void
    {
        $this->asSuperadmin();

        $urls = [
            route('reports.tbank-payments.index', ['without_payout' => 1]),
            route('reports.tbank-payments.total', ['without_payout' => 1]),
            route('reports.tbank-payments.data', array_merge($this->baseDataTableParams(), ['without_payout' => 1])),
            '/admin/reports/tbank-payments/columns-settings',
        ];

        foreach ($urls as $url) {
            foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->json($method, $url, ['without_payout' => 1, 'page_length' => 50]);
                $this->assertNotSame(500, $response->getStatusCode(), "$method $url");
                $this->assertContains($response->getStatusCode(), [404, 405], "$method $url");
            }
        }
    }

    public function test_non_ajax_data_with_without_payout_still_returns_json_not_empty_html(): void
    {
        $this->asSuperadmin();
        $this->makePayment(['amount' => 11000]);

        $response = $this->get(route('reports.tbank-payments.data', array_merge(
            $this->baseDataTableParams(),
            ['without_payout' => 1]
        )), [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('recordsTotal', $json);
        $this->assertArrayHasKey('recordsFiltered', $json);
    }

    public function test_without_payout_keeps_payments_whose_payout_column_would_be_dash(): void
    {
        $this->asSuperadmin();

        $noPayout = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 10000]);
        $paymentRejected = $this->makePayment(['status' => 'REJECTED', 'amount' => 20000]);
        $payoutRejectedOnly = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 30000]);
        $completed = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 40000]);
        $initiated = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 50000]);
        $creditChecking = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 60000]);
        $retryAfterReject = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 70000]);

        $this->makePayout($payoutRejectedOnly, ['status' => 'REJECTED']);
        $this->makePayout($completed, ['status' => 'COMPLETED', 'net_amount' => 36000]);
        $this->makePayout($initiated, [
            'status' => 'INITIATED',
            'when_to_run' => Carbon::parse('2026-09-10 12:00:00'),
        ]);
        $this->makePayout($creditChecking, ['status' => 'CREDIT_CHECKING']);
        $this->makePayout($retryAfterReject, ['status' => 'REJECTED']);
        $this->makePayout($retryAfterReject, ['status' => 'COMPLETED', 'net_amount' => 63000]);

        $this->get(route('reports.tbank-payments.total', ['without_payout' => 1]))
            ->assertOk()
            ->assertJsonPath('total_raw', 600);

        $rows = collect($this->datatableRows(['without_payout' => 1]))->keyBy('id');
        $ids = $rows->keys()->map(fn ($id) => (int) $id)->all();

        $this->assertContains($noPayout->id, $ids);
        $this->assertContains($paymentRejected->id, $ids);
        $this->assertContains($payoutRejectedOnly->id, $ids);
        $this->assertNotContains($completed->id, $ids);
        $this->assertNotContains($initiated->id, $ids);
        $this->assertNotContains($creditChecking->id, $ids);
        $this->assertNotContains($retryAfterReject->id, $ids);

        $this->assertNull($rows[$noPayout->id]['payout_amount']);
        $this->assertNull($rows[$payoutRejectedOnly->id]['payout_amount']);
    }

    public function test_without_payout_together_with_confirmed_status_does_not_keep_new_payments(): void
    {
        $this->asSuperadmin();

        $confirmedNoPayout = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 10000]);
        $newNoPayout = $this->makePayment(['status' => 'NEW', 'amount' => 20000]);
        $confirmedPaid = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 30000]);
        $this->makePayout($confirmedPaid, ['status' => 'COMPLETED']);

        $ids = $this->datatableIds([
            'without_payout' => 1,
            'status' => 'CONFIRMED',
        ]);

        $this->assertContains($confirmedNoPayout->id, $ids);
        $this->assertNotContains($newNoPayout->id, $ids);
        $this->assertNotContains($confirmedPaid->id, $ids);

        $this->get(route('reports.tbank-payments.total', [
            'without_payout' => 1,
            'status' => 'CONFIRMED',
        ]))
            ->assertOk()
            ->assertJsonPath('total_raw', 100);
    }

    public function test_datatable_search_with_without_payout_does_not_keep_paid_order_match(): void
    {
        $this->asSuperadmin();

        $hit = $this->makePayment([
            'order_id' => 'uniq-nopayout-needle-77',
            'amount' => 10000,
        ]);
        $paidSameNeedle = $this->makePayment([
            'order_id' => 'uniq-nopayout-needle-88',
            'amount' => 20000,
        ]);
        $this->makePayout($paidSameNeedle, ['status' => 'COMPLETED']);

        $ids = $this->datatableIds([
            'without_payout' => 1,
            'search' => ['value' => 'uniq-nopayout-needle'],
        ]);

        $this->assertContains($hit->id, $ids);
        $this->assertNotContains($paidSameNeedle->id, $ids);
    }

    public function test_non_superadmin_without_payout_filter_does_not_show_foreign_partner_rows(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);

        $own = $this->makePayment(['partner_id' => $this->partner->id, 'amount' => 15000]);
        $foreign = $this->makePayment(['partner_id' => $this->foreignPartner->id, 'amount' => 88000]);

        $this->get(route('reports.tbank-payments.total', [
            'without_payout' => 1,
            'partner_id' => $this->foreignPartner->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total_raw', 150);

        $ids = $this->datatableIds([
            'without_payout' => 1,
            'partner_id' => $this->foreignPartner->id,
        ]);
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_first_open_leaves_without_payout_unchecked_and_filters_collapsed(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', false)
            ->getContent();

        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
        $this->assertStringNotContainsString('show', $collapseTag[0]);

        $checkbox = $this->withoutPayoutCheckboxTag($html);
        $this->assertStringContainsString('type="checkbox"', $checkbox);
        $this->assertStringContainsString('value="1"', $checkbox);
        $this->assertStringContainsString('name="without_payout"', $checkbox);
        $this->assertStringNotContainsString('checked', $checkbox);
        $this->assertStringNotContainsString('disabled', $checkbox);
        $this->assertStringContainsString('data-error-for="without_payout"', $html);
        $this->assertMatchesRegularExpression(
            '/id="tp-filter-created-to"[\s\S]*id="tp-filter-without-payout"[\s\S]*Применить/',
            $html
        );
    }

    public function test_opening_with_without_payout_checks_checkbox_and_opens_filters(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('reports.tbank-payments.index', ['without_payout' => 1]))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->getContent();

        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
        $this->assertStringContainsString('show', $collapseTag[0]);
        $this->assertStringContainsString('checked', $this->withoutPayoutCheckboxTag($html));
        $this->assertStringContainsString('<option value="" selected>Все статусы</option>', $html);
    }

    public function test_reopening_with_method_filter_does_not_check_without_payout(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('reports.tbank-payments.index', ['method' => 'sbp']))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->getContent();

        $this->assertStringContainsString('value="sbp" selected', $html);
        $this->assertStringNotContainsString('checked', $this->withoutPayoutCheckboxTag($html));
    }

    public function test_confirmed_status_keeps_panel_open_but_does_not_check_without_payout(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('reports.tbank-payments.index', ['status' => 'CONFIRMED']))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->getContent();

        $this->assertStringContainsString('value="CONFIRMED" selected', $html);
        $this->assertStringNotContainsString('checked', $this->withoutPayoutCheckboxTag($html));
    }

    public function test_off_sentinels_do_not_force_without_payout_on(): void
    {
        $this->asSuperadmin();

        foreach (['0', 'all', ''] as $value) {
            $html = $this->get(route('reports.tbank-payments.index', ['without_payout' => $value]))
                ->assertOk()
                ->assertViewHas('tpHasActiveFilters', false)
                ->getContent();

            $this->assertStringNotContainsString('checked', $this->withoutPayoutCheckboxTag($html), "value={$value}");
        }
    }

    public function test_reopening_with_without_payout_keeps_saved_page_length_and_column_defaults(): void
    {
        $this->asSuperadmin();

        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'page_length' => 50,
            'columns' => [
                'deal_id' => false,
                'amount' => true,
            ],
        ])->assertOk();

        $html = $this->get(route('reports.tbank-payments.index', ['without_payout' => 1]))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->assertViewHas('tbankPaymentsPageLength', 50)
            ->getContent();

        $createPos = strpos($html, "KidsCrmDataTable.create('#tbank-payments-table'");
        $this->assertNotFalse($createPos);
        $createChunk = substr($html, $createPos, 2500);
        $this->assertMatchesRegularExpression('/var currentPageLength\s*=\s*50\b/', $html);
        $this->assertMatchesRegularExpression('/pageLength:\s*currentPageLength\b/', $createChunk);
        $this->assertDoesNotMatchRegularExpression('/pageLength:\s*\d+\b/', $createChunk);
        $this->assertStringContainsString('persistPageLength: true', $createChunk);
        $this->assertStringContainsString('checked', $this->withoutPayoutCheckboxTag($html));
    }

    public function test_non_superadmin_sees_without_payout_checkbox_without_partner_filter(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertViewHas('tpCanFilterPartner', false)
            ->getContent();

        $this->assertStringContainsString('id="tp-filter-without-payout"', $html);
        $this->assertStringNotContainsString('id="tp-filter-partner"', $html);
    }

    public function test_invalid_without_payout_ajax_returns_422_with_field_message(): void
    {
        $this->asSuperadmin();

        foreach (['maybe', 'on', 'yes', 'true', '2'] as $invalid) {
            $this->getJson(route('reports.tbank-payments.total', ['without_payout' => $invalid]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['without_payout'])
                ->assertJsonPath('errors.without_payout.0', self::INVALID_MESSAGE);

            $this->getJson(route('reports.tbank-payments.data', array_merge(
                $this->baseDataTableParams(),
                ['without_payout' => $invalid]
            )))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['without_payout'])
                ->assertJsonPath('errors.without_payout.0', self::INVALID_MESSAGE);
        }
    }

    public function test_invalid_without_payout_non_ajax_redirects_back_and_shows_error_under_checkbox(): void
    {
        $this->asSuperadmin();

        $invalid = $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.index', ['without_payout' => 'maybe']));

        $invalid->assertStatus(302)
            ->assertSessionHasErrors(['without_payout']);
        $this->assertSame(
            self::INVALID_MESSAGE,
            session('errors')->first('without_payout')
        );

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('is-invalid', $this->withoutPayoutCheckboxTag($html));
        $this->assertStringContainsString('data-error-for="without_payout"', $html);
        $this->assertStringContainsString(self::INVALID_MESSAGE, $html);
        $this->assertStringNotContainsString('checked', $this->withoutPayoutCheckboxTag($html));
    }

    public function test_invalid_without_payout_on_total_and_data_non_ajax_redirects_with_field_error(): void
    {
        $this->asSuperadmin();

        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.total', ['without_payout' => 'maybe']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['without_payout']);

        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.data', array_merge(
                $this->baseDataTableParams(),
                ['without_payout' => 'maybe']
            )))
            ->assertStatus(302)
            ->assertSessionHasErrors(['without_payout']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filterEndpoints(): array
    {
        $params = ['without_payout' => 1];

        return [
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.index', $params),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.data', array_merge($this->baseDataTableParams(), $params)),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.total', $params),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function authorizedFilterVariants(): array
    {
        return [
            ['without_payout' => 1],
            ['without_payout' => 0],
            ['without_payout' => '1'],
            ['without_payout' => 1, 'status' => 'CONFIRMED'],
            ['without_payout' => 1, 'method' => 'sbp'],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<int>
     */
    private function datatableIds(array $params): array
    {
        return collect($this->datatableRows($params))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    private function datatableRows(array $params): array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.tbank-payments.data', array_merge($this->baseDataTableParams(), $params)))
            ->assertOk()
            ->json('data');

        $this->assertIsArray($json);

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseDataTableParams(): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function withoutPayoutCheckboxTag(string $html): string
    {
        $this->assertSame(
            1,
            preg_match('/<input\b[^>]*\bid="tp-filter-without-payout"[^>]*>/', $html, $match)
        );

        return $match[0];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePayment(array $overrides = []): TinkoffPayment
    {
        return TinkoffPayment::query()->create(array_merge([
            'order_id' => 'order-'.uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePayout(TinkoffPayment $payment, array $overrides = []): TinkoffPayout
    {
        return TinkoffPayout::query()->create(array_merge([
            'payment_id' => $payment->id,
            'partner_id' => $payment->partner_id,
            'deal_id' => (string) ($payment->deal_id ?: 'deal-'.$payment->id),
            'amount' => 1000,
            'is_final' => true,
            'status' => 'COMPLETED',
            'source' => 'auto',
        ], $overrides));
    }

    private function grantTbankPaymentsViewToAdmin(): User
    {
        $actor = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => (int) $actor->role_id,
                'permission_id' => $this->permissionId('reports.tbank.payments.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $actor;
    }
}
