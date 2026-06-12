<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Partner;
use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Отчёты → Платежные запросы» (/admin/reports/payment-intents):
 * контроль доступа (guest / без права / с правом / superadmin) и smoke 200 для всех endpoints.
 */
final class PaymentIntentsPageFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_cannot_access_any_payment_intents_endpoint(): void
    {
        Auth::logout();

        foreach ($this->allSectionRoutesPayload() as $item) {
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
        }
    }

    public function test_user_without_reports_payment_intents_view_gets_403_on_all_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('reports.payment.intents.view', $this->partner);
        $this->actingAs($actor);

        foreach ($this->allSectionRoutesPayload() as $item) {
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
                "Без reports.payment.intents.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_reports_payment_intents_view_all_section_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('reports.payment.intents.view', $this->partner);
        $this->grantReportsPaymentIntentsView((int) $actor->role_id);
        $this->actingAs($actor);

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser();
    }

    public function test_superadmin_all_section_endpoints_return_200(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser(isSuperadmin: true);
    }

    public function test_authorized_user_all_filter_param_variants_return_200(): void
    {
        $this->asSuperadmin();

        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'status' => 'paid',
            'provider' => 'tbank',
            'out_sum' => 1500,
            'paid_at' => now()->subDay(),
        ]);

        foreach ($this->allFilterParamVariants() as $params) {
            $this->get(route('reports.payment-intents.index', $params))
                ->assertOk()
                ->assertViewHas('activeTab', 'payment-intents');

            $this->get(route('reports.payment-intents.total', $params))->assertOk();

            $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('reports.payment-intents.data', array_merge($this->baseDataTableParams(), $params)))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_guest_and_unauthorized_users_cannot_access_with_filter_query_params(): void
    {
        $params = [
            'inv_id' => '1',
            'status' => 'paid',
            'provider' => 'tbank',
            'created_from' => now()->subMonth()->toDateString(),
            'paid_to' => now()->toDateString(),
        ];

        Auth::logout();
        $this->get(route('reports.payment-intents.index', $params))->assertRedirect();
        $this->getJson(route('reports.payment-intents.data', array_merge($this->baseDataTableParams(), $params)))
            ->assertStatus(401);

        $denied = $this->createUserWithoutPermission('reports.payment.intents.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('reports.payment-intents.index', $params))->assertForbidden();
        $this->getJson(route('reports.payment-intents.data', array_merge($this->baseDataTableParams(), $params)))
            ->assertForbidden();
        $this->get(route('reports.payment-intents.total', $params))->assertForbidden();
    }

    private function assertAllSectionEndpointsSucceedForAuthorizedUser(bool $isSuperadmin = false): void
    {
        $this->get(route('reports.payment-intents.index'))
            ->assertOk()
            ->assertViewIs('admin.report.index')
            ->assertViewHas('activeTab', 'payment-intents')
            ->assertSee('id="payment-intents-table"', false)
            ->assertSee('KidsCrmDataTable.create', false);

        $dataParams = $this->baseDataTableParams();
        if ($isSuperadmin) {
            $dataParams['partner_id'] = (string) $this->partner->id;
        }

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payment-intents.data', $dataParams))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('reports.payment-intents.total'))->assertOk();

        $this->get('/admin/reports/payment-intents/columns-settings')->assertOk();

        $this->postJson('/admin/reports/payment-intents/columns-settings', [
            'columns' => [
                'id' => true,
                'meta' => false,
                'client_user_agent' => true,
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $this->get(route('reports.payment-intents.partners.search', ['q' => '']))->assertOk();
        $this->get(route('reports.payment-intents.users.search', ['q' => '']))->assertOk();

        $partner = Partner::factory()->create(['title' => 'Smoke Partner PI']);
        $student = User::factory()->create([
            'partner_id' => $partner->id,
            'lastname' => 'Смоков',
            'name' => 'Платёж',
        ]);

        $this->get(route('reports.payment-intents.partners.search', ['q' => 'Smoke']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->get(route('reports.payment-intents.users.search', [
            'q' => 'Платёж',
            'partner_id' => $partner->id,
        ]))
            ->assertOk()
            ->assertJsonPath('results.0.id', $student->id);

        $this->get(route('reports.payment-intents.index', [
            'partner_id' => $partner->id,
            'user_id' => $student->id,
            'status' => 'pending',
            'provider' => 'robokassa',
            'created_from' => now()->subMonth()->toDateString(),
            'paid_to' => now()->toDateString(),
        ]))->assertOk();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allSectionRoutesPayload(): array
    {
        $dataUrl = route('reports.payment-intents.data', $this->baseDataTableParams());

        return [
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => $dataUrl,
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.data', array_merge(
                    $this->baseDataTableParams(),
                    [
                        'status' => 'paid',
                        'provider' => 'tbank',
                        'inv_id' => '42',
                        'created_from' => now()->subYear()->toDateString(),
                        'paid_to' => now()->toDateString(),
                    ]
                )),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.total'),
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.total', [
                    'status' => 'pending',
                    'provider' => 'robokassa',
                ]),
            ],
            [
                'method' => 'GET',
                'url' => '/admin/reports/payment-intents/columns-settings',
            ],
            [
                'method' => 'POST',
                'url' => '/admin/reports/payment-intents/columns-settings',
                'data' => ['columns' => ['id' => true, 'status' => false]],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.partners.search', ['q' => 'test']),
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.users.search', ['q' => 'test', 'partner_id' => 1]),
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payment-intents.index', [
                    'status' => 'paid',
                    'provider' => 'tbank',
                ]),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function allFilterParamVariants(): array
    {
        $partner = Partner::factory()->create(['title' => 'Filter PI Partner']);
        $student = User::factory()->create([
            'partner_id' => $partner->id,
            'lastname' => 'Фильтров',
            'name' => 'Тест',
        ]);

        $intent = PaymentIntent::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $student->id,
            'provider_inv_id' => 424242,
            'status' => 'paid',
            'provider' => 'tbank',
        ]);

        return [
            [],
            ['inv_id' => (string) $intent->id],
            ['inv_id' => (string) $intent->provider_inv_id],
            ['partner_id' => (string) $partner->id],
            ['user_id' => (string) $student->id],
            ['status' => 'paid'],
            ['provider' => 'tbank'],
            ['created_from' => now()->subWeek()->toDateString()],
            ['created_to' => now()->toDateString()],
            ['paid_from' => now()->subWeek()->toDateString()],
            ['paid_to' => now()->toDateString()],
            [
                'partner_id' => (string) $partner->id,
                'user_id' => (string) $student->id,
                'status' => 'paid',
                'provider' => 'tbank',
                'created_from' => now()->subMonth()->toDateString(),
                'paid_to' => now()->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseDataTableParams(): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function grantReportsPaymentIntentsView(int $roleId): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId('reports.payment.intents.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
