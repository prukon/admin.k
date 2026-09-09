<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к HTML четырёх вкладок отчётов с закреплённой шапкой:
 * гость / без reports.view / viewer / admin / superadmin;
 * чужие методы не 500 и не пустой 200; повторное открытие с фильтрами
 * не снимает pin.
 *
 * @see ReportsTablesStickyHeaderFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ReportsTablesStickyHeaderFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function stickyPageRoutes(): array
    {
        return [
            'payments' => ['payments', 'payments-table'],
            'monthly'  => ['reports.payments.monthly', 'payments-monthly-table'],
            'ltv'      => ['reports.ltv', 'ltv-table'],
            'debts'    => ['debts', 'debts-table'],
        ];
    }

    #[DataProvider('stickyPageRoutes')]
    public function test_guest_cannot_open_sticky_report_page(string $route, string $tableId): void
    {
        Auth::logout();
        $url = route($route);

        $web = $this->get($url);
        $this->assertNotSame(500, $web->getStatusCode(), $url);
        $this->assertNotSame(200, $web->getStatusCode(), $url);
        $web->assertStatus(302);
        $this->assertStringNotContainsString('KidsCrmReportTableSticky', (string) $web->getContent());

        $json = $this->getJson($url);
        $this->assertNotSame(500, $json->getStatusCode(), $url);
        $json->assertStatus(401);

        $ajax = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get($url);
        $this->assertNotSame(500, $ajax->getStatusCode(), $url);
        $this->assertContains($ajax->getStatusCode(), [302, 401]);
        unset($tableId);
    }

    #[DataProvider('stickyPageRoutes')]
    public function test_manager_without_reports_view_gets_403_on_sticky_report_page(string $route, string $tableId): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];
        $url = route($route);

        $web = $this->actingAs($actor)->withSession($session)->get($url);
        $this->assertNotSame(500, $web->getStatusCode(), $url);
        $web->assertStatus(403);
        $this->assertStringNotContainsString('KidsCrmReportTableSticky.bind(\'#'.$tableId.'\')', (string) $web->getContent());

        $json = $this->actingAs($actor)->withSession($session)->getJson($url);
        $this->assertNotSame(500, $json->getStatusCode(), $url);
        $json->assertStatus(403);
    }

    #[DataProvider('stickyPageRoutes')]
    public function test_viewer_with_reports_view_sees_pinned_thead_markup(string $route, string $tableId): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->grantPermission($actor, 'reports.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $page = $this->get(route($route));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html), $route);
        $this->assertStringContainsString('id="'.$tableId.'"', $html);
        $this->assertStringContainsString('dataTables.fixedHeader.min.js', $html);
        $this->assertStringContainsString('KidsCrmReportTableSticky.bind(\'#'.$tableId.'\')', $html);
        $this->assertStringContainsString('header: true', $html);
        $this->assertStringContainsString('footer: false', $html);
        $this->assertStringContainsString(
            '($.fn.dataTable && $.fn.dataTable.FixedHeader)',
            $html,
            'Нет плагина → false, иначе шапка не должна падать'
        );
    }

    #[DataProvider('stickyPageRoutes')]
    public function test_admin_and_superadmin_open_sticky_report_page_without_500(string $route, string $tableId): void
    {
        $this->asAdmin();
        $admin = $this->get(route($route));
        $admin->assertOk();
        $this->assertNotSame('', trim((string) $admin->getContent()));
        $admin->assertSee('KidsCrmReportTableSticky.bind(\'#'.$tableId.'\')', false);

        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $sa = $this->get(route($route));
        $sa->assertOk();
        $this->assertNotSame('', trim((string) $sa->getContent()));
        $sa->assertSee('KidsCrmReportTableSticky.bind(\'#'.$tableId.'\')', false);
    }

    #[DataProvider('stickyPageRoutes')]
    public function test_reopening_sticky_report_with_filters_still_pins_thead(string $route, string $tableId): void
    {
        $this->asAdmin();

        $first = $this->get(route($route));
        $first->assertOk();
        $this->assertStringContainsString(
            'KidsCrmReportTableSticky.bind(\'#'.$tableId.'\')',
            (string) $first->getContent()
        );

        $reopened = $this->get(route($route, ['status' => 'inactive']));
        $reopened->assertOk();
        $html = (string) $reopened->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('KidsCrmReportTableSticky.bind(\'#'.$tableId.'\')', $html);
        $this->assertStringContainsString('header: true', $html);
        $this->assertStringContainsString('footer: false', $html);
        $this->assertStringContainsString('dataTables.fixedHeader.min.js', $html);
    }

    #[DataProvider('stickyPageRoutes')]
    public function test_unsupported_methods_on_sticky_report_page_are_not_server_errors(string $route, string $tableId): void
    {
        $this->asAdmin();
        $url = route($route);

        foreach (['post', 'patch', 'put', 'delete'] as $method) {
            $response = $this->{$method}($url);
            $this->assertNotSame(500, $response->getStatusCode(), $method.' '.$url);
            $this->assertNotSame(200, $response->getStatusCode(), $method.' '.$url.' пустой/бессмысленный 200');
            $this->assertContains(
                $response->getStatusCode(),
                [404, 405, 419],
                $method.' '.$url.' → '.$response->getStatusCode()
            );
        }

        $this->postJson($url)->assertStatus(405);
        $this->patchJson($url)->assertStatus(405);
        $this->putJson($url)->assertStatus(405);
        $this->deleteJson($url)->assertStatus(405);
        unset($tableId);
    }

    public function test_user_without_organization_is_logged_out_from_sticky_report_pages(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        foreach (['payments', 'reports.payments.monthly', 'reports.ltv', 'debts'] as $route) {
            $response = $this->from(route('login'))->get(route($route));
            $response->assertStatus(302);
            $this->assertGuest();
            $response->assertSessionHasErrors([
                'email' => 'Ваша организация недоступна.',
            ]);
            $this->actingAs($actor);
        }
    }

    public function test_data_json_of_sticky_pages_does_not_return_500_or_empty_200(): void
    {
        $this->asAdmin();

        $ajax = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ];
        $dt = ['draw' => 1, 'start' => 0, 'length' => 10];

        $ajaxUrls = [
            route('payments.getPayments', $dt),
            route('reports.payments.monthly.data', $dt + ['mode' => 'subscription']),
            route('reports.ltv.data', $dt),
            route('debts.getDebts', $dt),
        ];

        foreach ($ajaxUrls as $url) {
            $response = $this->call('GET', $url, [], [], [], $ajax);
            $this->assertSame(200, $response->getStatusCode(), $url);
            $this->assertNotSame('', trim((string) $response->getContent()), $url);
            $response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
            $this->assertStringNotContainsString('KidsCrmReportTableSticky', (string) $response->getContent());
        }

        $this->get(route('payments.getPayments', $dt))->assertNotFound();
        $this->get(route('reports.ltv.data', $dt))->assertNotFound();
        $this->get(route('reports.payments.monthly.data', $dt))->assertNotFound();
        $this->get(route('debts.getDebts', $dt))->assertNotFound();
    }

    public function test_guest_and_manager_without_permission_do_not_get_500_on_data_json(): void
    {
        Auth::logout();
        $ajax = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ];
        $url = route('payments.getPayments', ['draw' => 1, 'start' => 0, 'length' => 10]);

        $guest = $this->call('GET', $url, [], [], [], $ajax);
        $this->assertNotSame(500, $guest->getStatusCode());
        $this->assertContains($guest->getStatusCode(), [302, 401, 403, 419]);

        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $denied = $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ])->call('GET', $url, [], [], [], $ajax);
        $this->assertNotSame(500, $denied->getStatusCode());
        $denied->assertStatus(403);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'      => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
}
