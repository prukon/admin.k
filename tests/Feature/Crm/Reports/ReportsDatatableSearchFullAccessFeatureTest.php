<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * HTTP-матрица глобального поиска DataTables на четырёх отчётах:
 * Платежи, monthly, LTV, payment-intents (гость / без права / с правом / методы / non-AJAX).
 */
final class ReportsDatatableSearchFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->flushHeaders();
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @return array<string, mixed>
     */
    private function dtParams(string $needle = 'тест'): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => $needle],
        ];
    }

    /**
     * @return list<array{method: string, url: string, headers?: array<string, string>, permission: string, ajax_required?: bool}>
     */
    private function searchEndpoints(): array
    {
        $ajax = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ];
        $html = ['HTTP_ACCEPT' => 'text/html'];

        return [
            [
                'method' => 'GET',
                'url' => route('payments'),
                'headers' => $html,
                'permission' => 'reports.view',
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payments.monthly'),
                'headers' => $html,
                'permission' => 'reports.view',
            ],
            [
                'method' => 'GET',
                'url' => route('reports.ltv'),
                'headers' => $html,
                'permission' => 'reports.view',
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.index'),
                'headers' => $html,
                'permission' => 'reports.payment.intents.view',
            ],
            [
                'method' => 'GET',
                'url' => route('payments.getPayments', $this->dtParams()),
                'headers' => $ajax,
                'permission' => 'reports.view',
                'ajax_required' => true,
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payments.monthly.data', $this->dtParams() + ['mode' => 'subscription']),
                'headers' => $ajax,
                'permission' => 'reports.view',
                'ajax_required' => true,
            ],
            [
                'method' => 'GET',
                'url' => route('reports.ltv.data', $this->dtParams()),
                'headers' => $ajax,
                'permission' => 'reports.view',
                'ajax_required' => true,
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.data', $this->dtParams()),
                'headers' => $ajax,
                'permission' => 'reports.payment.intents.view',
                'ajax_required' => false,
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payments.total', ['search' => ['value' => 'тест']]),
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
                'permission' => 'reports.view',
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payments.monthly.total', ['search' => ['value' => 'тест']]),
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
                'permission' => 'reports.view',
            ],
            [
                'method' => 'GET',
                'url' => route('reports.ltv.total', ['search' => ['value' => 'тест']]),
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
                'permission' => 'reports.view',
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.total', ['search' => ['value' => 'тест']]),
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
                'permission' => 'reports.payment.intents.view',
            ],
        ];
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_cannot_open_or_search_any_of_the_four_reports(): void
    {
        Auth::logout();

        foreach ($this->searchEndpoints() as $item) {
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

    public function test_user_without_permission_gets_403_on_each_report_search(): void
    {
        foreach ($this->searchEndpoints() as $item) {
            $actor = $this->createUserWithoutPermission($item['permission'], $this->partner);
            $this->actingAs($actor)->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed' => true,
            ]);

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
                "Без {$item['permission']}: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_permission_can_open_pages_and_search_without_500(): void
    {
        foreach ($this->searchEndpoints() as $item) {
            $actor = $this->createUserWithoutPermission($item['permission'], $this->partner);
            $this->grantPermission($actor, $item['permission']);
            $this->actingAs($actor)->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed' => true,
            ]);

            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "С правом {$item['permission']}: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }

    public function test_superadmin_can_search_all_four_reports(): void
    {
        $this->asSuperadmin();

        foreach ($this->searchEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "SA: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_data_endpoints_without_ajax_header_do_not_return_500(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'reports.payment.intents.view');

        $this->get(route('payments.getPayments', $this->dtParams('Иванов')))->assertNotFound();
        $this->get(route('reports.ltv.data', $this->dtParams('Иванов')))->assertNotFound();
        $this->get(route('reports.payments.monthly.data', $this->dtParams('Иванов')))->assertNotFound();

        $this->get(route('reports.payment-intents.data', $this->dtParams('Иванов')))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_unsupported_methods_on_search_data_do_not_return_500_or_empty_200(): void
    {
        $this->asSuperadmin();

        $urls = [
            route('payments.getPayments', $this->dtParams('x')),
            route('reports.ltv.data', $this->dtParams('x')),
            route('reports.payments.monthly.data', $this->dtParams('x')),
            route('reports.payment-intents.data', $this->dtParams('x')),
            route('reports.payments.total'),
            route('reports.ltv.total'),
            route('reports.payments.monthly.total'),
            route('reports.payment-intents.total'),
        ];

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            foreach ($urls as $url) {
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

    public function test_admin_without_organization_is_logged_out_from_search(): void
    {
        $this->asAdmin();
        $this->user->partner_id = null;
        $this->user->save();
        $this->actingAs($this->user);
        $this->withSession([
            'current_partner' => null,
            '2fa:passed' => true,
        ]);

        $response = $this->from(route('login'))
            ->get(route('payments', ['search' => ['value' => 'тест']]));

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => 'Ваша организация недоступна.']);
    }

    public function test_nested_monthly_and_ltv_payments_search_follow_same_access_rules(): void
    {
        $this->asAdmin();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'payment_month' => '2026-02-01',
            'summ_cents' => 10000,
        ]);

        $monthlyUrl = route('reports.payments.monthly.payments', array_merge(
            ['yearMonth' => '2026-02'],
            $this->dtParams('')
        ));
        $ltvUrl = route('reports.ltv.user_payments', array_merge(
            ['user' => $student->id],
            $this->dtParams('')
        ));

        Auth::logout();
        $guestMonthly = $this->call('GET', $monthlyUrl, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $this->assertContains($guestMonthly->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $guestMonthly->getStatusCode());
        $guestLtv = $this->call('GET', $ltvUrl, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $this->assertContains($guestLtv->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $guestLtv->getStatusCode());

        $denied = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($denied)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->call('GET', $monthlyUrl, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ])->assertForbidden();
        $this->call('GET', $ltvUrl, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ])->assertForbidden();

        $this->asAdmin();
        $this->withHeaders($this->ajaxHeaders())
            ->getJson($monthlyUrl)
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        $this->withHeaders($this->ajaxHeaders())
            ->getJson($ltvUrl)
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->flushHeaders();
        $this->call('GET', $monthlyUrl, [], [], [], ['HTTP_ACCEPT' => 'text/html'])->assertNotFound();
        $this->call('GET', $ltvUrl, [], [], [], ['HTTP_ACCEPT' => 'text/html'])->assertNotFound();
    }

    public function test_header_total_ignores_datatable_search_value(): void
    {
        $this->asAdmin();

        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ТолькоОдинDtFull',
            'is_enabled' => 1,
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ДругойDtFull',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $hit->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 10000,
            'payment_month' => '2026-03-01',
        ]);
        Payment::factory()->create([
            'user_id' => $miss->id,
            'partner_id' => $this->partner->id,
            'user_name' => null,
            'summ_cents' => 20000,
            'payment_month' => '2026-03-01',
        ]);

        $search = ['search' => ['value' => 'ТолькоОдинDtFull']];

        foreach ([
            'reports.payments.total',
            'reports.ltv.total',
            'reports.payments.monthly.total',
        ] as $routeName) {
            $without = (float) $this->getJson(route($routeName))->json('total_raw');
            $with = (float) $this->getJson(route($routeName, $search))->json('total_raw');
            $this->assertEqualsWithDelta(
                $without,
                $with,
                0.001,
                $routeName
            );
            $this->assertGreaterThan(0, $without, $routeName);
        }
    }

    public function test_intents_total_ignores_datatable_search_value(): void
    {
        $this->asSuperadmin();

        $hit = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ТолькоОдинDtPiFull',
        ]);
        $miss = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'ДругойDtPiFull',
        ]);
        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $hit->id,
            'provider_inv_id' => 822000001,
            'out_sum_cents' => 10000,
        ]);
        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $miss->id,
            'provider_inv_id' => 822000002,
            'out_sum_cents' => 20000,
        ]);

        $without = (float) $this->getJson(route('reports.payment-intents.total', [
            'partner_id' => $this->partner->id,
        ]))->json('total_raw');
        $with = (float) $this->getJson(route('reports.payment-intents.total', [
            'partner_id' => $this->partner->id,
            'search' => ['value' => 'ТолькоОдинDtPiFull'],
        ]))->json('total_raw');
        $this->assertEqualsWithDelta($without, $with, 0.001);
        $this->assertGreaterThan(0, $without);
    }
}
