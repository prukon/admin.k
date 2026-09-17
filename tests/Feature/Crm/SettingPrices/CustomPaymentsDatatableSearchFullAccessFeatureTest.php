<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * HTTP-матрица глобального поиска DataTables на вкладке доп. платежей:
 * гость / без права / с правом / SA / non-AJAX / методы / без организации.
 */
final class CustomPaymentsDatatableSearchFullAccessFeatureTest extends CrmTestCase
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
     * Колонки как в живой таблице до фикса (user_name/amount searchable → 42S22).
     *
     * @return list<array<string, string>>
     */
    private function browserColumns(): array
    {
        $col = static function (string $data, string $name, bool $searchable = true): array {
            return [
                'data' => $data,
                'name' => $name,
                'searchable' => $searchable ? 'true' : 'false',
                'orderable' => 'true',
            ];
        };

        return [
            $col('id', 'id'),
            $col('user_name', 'user_name'),
            $col('team_label', 'team_label'),
            $col('amount', 'amount'),
            $col('note', 'note'),
            $col('status_label', 'status', false),
            $col('actions', 'actions', false),
        ];
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
            'columns' => $this->browserColumns(),
            'search' => ['value' => $needle],
        ];
    }

    /**
     * @return list<array{method: string, url: string, headers?: array<string, string>}>
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
                'url' => route('admin.settingPrices.customPayments'),
                'headers' => $html,
            ],
            [
                'method' => 'GET',
                'url' => route('admin.settingPrices.customPayments.data', $this->dtParams()),
                'headers' => $ajax,
            ],
        ];
    }

    private function grantCustomPaymentsAccess(User $actor): void
    {
        foreach (['setPrices.view', 'setPrices.customPayments.view'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_guest_cannot_open_or_search_custom_payments(): void
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

    public function test_user_without_custom_payments_view_gets_403_on_search(): void
    {
        foreach ($this->searchEndpoints() as $item) {
            $actor = $this->createUserWithoutPermission('setPrices.customPayments.view', $this->partner);
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId('setPrices.view'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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
                "Без customPayments.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_set_prices_view_gets_403_on_search(): void
    {
        foreach ($this->searchEndpoints() as $item) {
            $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
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
                "Без setPrices.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_permission_can_open_page_and_search_without_500(): void
    {
        foreach ($this->searchEndpoints() as $item) {
            $actor = $this->createUserWithoutPermission('setPrices.customPayments.view', $this->partner);
            $this->grantCustomPaymentsAccess($actor);
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
                "С правом: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }

    public function test_superadmin_can_search_custom_payments(): void
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

    public function test_data_search_without_ajax_header_returns_json_not_500(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.customPayments.view', $this->partner);
        $this->grantCustomPaymentsAccess($actor);
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.settingPrices.customPayments.data', $this->dtParams('Иванов')))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_unsupported_methods_on_search_data_do_not_return_500_or_empty_200(): void
    {
        $this->asSuperadmin();

        $url = route('admin.settingPrices.customPayments.data', $this->dtParams('x'));

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
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
            ->get(route('admin.settingPrices.customPayments', ['search' => ['value' => 'тест']]));

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => 'Ваша организация недоступна.']);
    }
}
