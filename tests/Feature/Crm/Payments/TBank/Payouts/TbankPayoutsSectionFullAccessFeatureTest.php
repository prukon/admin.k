<?php

namespace Tests\Feature\Crm\Payments\TBank\Payouts;

use App\Models\TinkoffPayout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к разделу «Выплаты T‑Bank» (/admin/tinkoff/payouts) и всем связанным endpoint'ам:
 * при наличии tbank.payouts.manage — 200, иначе 403.
 */
final class TbankPayoutsSectionFullAccessFeatureTest extends CrmTestCase
{
    private TinkoffPayout $payout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->payout = TinkoffPayout::query()->create([
            'payment_id'                => null,
            'partner_id'                => $this->partner->id,
            'deal_id'                   => 'section-access-' . uniqid(),
            'amount'                    => 1000,
            'is_final'                  => true,
            'status'                    => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run'               => now()->addDay(),
            'completed_at'              => null,
        ]);
    }

    public function test_guest_cannot_access_any_section_endpoint(): void
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

    public function test_user_without_payouts_manage_gets_403_on_all_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
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
                "Без tbank.payouts.manage: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_payouts_manage_page_and_all_api_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPayoutsManage((int) $actor->role_id);
        $this->actingAs($actor);

        $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertViewHas('activeTab', 'payouts');

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
                200,
                $response->getStatusCode(),
                "С tbank.payouts.manage: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_superadmin_all_section_endpoints_return_200_including_partners_search(): void
    {
        $this->asSuperadmin();
        $this->grantPayoutsManage((int) $this->user->role_id);

        $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertViewHas('activeTab', 'payouts');

        $routes = array_merge(
            $this->allSectionRoutesPayload(),
            [
                [
                    'method' => 'GET',
                    'url'    => '/admin/tinkoff/payouts/partners-search?q=',
                ],
            ]
        );

        foreach ($routes as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Суперадмин: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_only_partner_view_cannot_open_payouts_tab(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPartnerView((int) $actor->role_id);
        $this->actingAs($actor);

        $this->get(route('admin.tinkoff.payouts.index'))->assertForbidden();
        $this->get('/admin/tinkoff/payouts/data?draw=1')->assertForbidden();
        $this->get('/admin/tinkoff/payouts/total')->assertForbidden();
    }

    public function test_regular_user_partners_search_returns_403_even_with_manage_permission(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPayoutsManage((int) $actor->role_id);
        $this->actingAs($actor);

        $this->get('/admin/tinkoff/payouts/partners-search?q=test')->assertForbidden();
    }

    public function test_schedule_update_returns_redirect_when_allowed(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPayoutsManage((int) $actor->role_id);
        $this->actingAs($actor);

        $this->from('/admin/tinkoff/payouts/' . $this->payout->id)
            ->post('/admin/tinkoff/payouts/' . $this->payout->id . '/schedule', [
                'when_to_run' => now()->addHours(4)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect('/admin/tinkoff/payouts/' . $this->payout->id);
    }

    public function test_datatable_filter_query_variants_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPayoutsManage((int) $actor->role_id);
        $this->actingAs($actor);

        TinkoffPayout::query()->create([
            'payment_id'                => null,
            'partner_id'                => $this->partner->id,
            'deal_id'                   => 'filter-smoke-' . uniqid(),
            'amount'                    => 500,
            'is_final'                  => false,
            'status'                    => 'COMPLETED',
            'source'                    => 'auto',
            'tinkoff_payout_payment_id' => 'pay-123',
            'when_to_run'               => null,
            'completed_at'              => now(),
        ]);

        $queries = [
            '/admin/tinkoff/payouts/data?draw=1&start=0&length=10&status=INITIATED',
            '/admin/tinkoff/payouts/data?draw=1&start=0&length=10&status=COMPLETED',
            '/admin/tinkoff/payouts/data?draw=1&start=0&length=10&source=auto',
            '/admin/tinkoff/payouts/data?draw=1&start=0&length=10&source=manual',
            '/admin/tinkoff/payouts/data?draw=1&start=0&length=10&stuck_only=1&stuck_minutes=30',
            '/admin/tinkoff/payouts/total?status=COMPLETED',
            '/admin/tinkoff/payouts/total?source=auto',
        ];

        foreach ($queries as $url) {
            $this->get($url)->assertOk();
        }
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allSectionRoutesPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.tinkoff.payouts.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => '/admin/tinkoff/payouts/data?draw=1&start=0&length=10',
            ],
            [
                'method' => 'GET',
                'url'    => '/admin/tinkoff/payouts/total',
            ],
            [
                'method' => 'GET',
                'url'    => '/admin/tinkoff/payouts/columns-settings',
            ],
            [
                'method' => 'POST',
                'url'    => '/admin/tinkoff/payouts/columns-settings',
                'data'   => [
                    'columns' => [
                        'status'                  => true,
                        'partner'                 => true,
                        'legal_entity_organization' => true,
                        'net'                     => true,
                    ],
                ],
            ],
            [
                'method' => 'GET',
                'url'    => '/admin/tinkoff/payouts/payers-search?q=',
            ],
            [
                'method'  => 'GET',
                'url'     => '/admin/tinkoff/payouts/' . $this->payout->id,
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }

    private function grantPayoutsManage(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('tbank.payouts.manage'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantPartnerView(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('partner.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
