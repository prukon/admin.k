<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к LTV и «Платежи по месяцам» после колонки/фильтра оплаченной группы:
 * гость / без reports.view / с правом / superadmin; JSON-структура;
 * не 500 и не пустой 200; учётка без организации.
 *
 * @see PaymentMonthlyReportTeamPivotFeatureTest
 * @see LtvReportTeamPivotFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ReportsLtvMonthlyPaidTeamFullAccessFeatureTest extends CrmTestCase
{
    private User $student;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Доступ-группа',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => 1,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->student, (int) $this->team->id);

        Payment::factory()->create([
            'user_id' => $this->student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'team_title' => 'Доступ-группа',
            'summ_cents' => 120000,
            'payment_month' => '2026-08-01',
            'operation_date' => '2026-09-11 20:40:00',
        ]);
    }

    public function test_guest_cannot_access_any_ltv_or_monthly_endpoint(): void
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

            $this->assertNotSame(500, $response->getStatusCode(), "Гость 500: {$item['method']} {$item['url']}");
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_manager_without_reports_view_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertNotSame(500, $response->getStatusCode(), "Без права 500: {$item['method']} {$item['url']}");
            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без reports.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_reports_view_all_section_endpoints_return_expected_status(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->grantPermission($actor, 'reports.view');
        $this->actingAs($actor);

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser();
    }

    public function test_superadmin_all_section_endpoints_return_expected_status(): void
    {
        $this->asSuperadmin();

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser();
    }

    public function test_columns_settings_ajax_validation_returns_422_with_field_errors(): void
    {
        $this->asAdmin();

        $this->postJson(route('reports.payments.monthly.columns-settings.save'), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);

        $this->postJson(route('reports.ltv.columns-settings.save'), ['page_length' => 15], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page_length'])
            ->assertJsonPath('errors.page_length.0', 'Можно показать 10, 20, 50 или 100 записей.');
    }

    public function test_invalid_month_key_returns_400_not_500(): void
    {
        $this->asAdmin();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', [
                'yearMonth' => 'not-a-month',
                'mode' => 'subscription',
                'draw' => 1,
            ]))
            ->assertStatus(400);
    }

    public function test_user_without_organization_is_logged_out_from_ltv_and_monthly(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        foreach (['reports.payments.monthly', 'reports.ltv'] as $route) {
            $response = $this->from(route('login'))->get(route($route));
            $response->assertStatus(302);
            $this->assertGuest();
            $response->assertSessionHasErrors([
                'email' => 'Ваша организация недоступна.',
            ]);
            $this->actingAs($actor);
        }
    }

    public function test_unsupported_methods_do_not_return_500_or_empty_200(): void
    {
        $this->asAdmin();

        $urls = [
            route('reports.payments.monthly'),
            route('reports.payments.monthly.total'),
            route('reports.ltv'),
            route('reports.ltv.total'),
            route('reports.payments.monthly.columns-settings.get'),
            route('reports.ltv.columns-settings.get'),
        ];

        foreach ($urls as $url) {
            foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->json($method, $url, ['page_length' => 50]);
                $this->assertNotSame(500, $response->getStatusCode(), "$method $url");
                $this->assertNotSame(200, $response->getStatusCode(), "$method $url пустой 200");
                $this->assertContains($response->getStatusCode(), [404, 405], "$method $url");
            }
        }
    }

    public function test_foreign_partner_student_nested_ltv_does_not_leak_payments(): void
    {
        $this->asAdmin();

        Payment::factory()->create([
            'user_id' => $this->foreignUser->id,
            'partner_id' => $this->foreignUser->partner_id,
            'team_id' => $this->team->id,
            'team_title' => 'Чужая',
            'summ_cents' => 888800,
            'payment_month' => '2026-08-01',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', [
                'user' => $this->foreignUser->id,
                'status' => '',
            ]))
            ->assertOk()
            ->json();

        $this->assertSame((int) $this->foreignUser->id, (int) ($json['user_id'] ?? 0));
        $this->assertSame([], $json['payments'] ?? ['not-empty']);
    }

    private function assertAllSectionEndpointsSucceedForAuthorizedUser(): void
    {
        $monthlyPage = $this->get(route('reports.payments.monthly'))
            ->assertOk();
        $monthlyHtml = (string) $monthlyPage->getContent();
        $this->assertNotSame('', trim($monthlyHtml));
        $this->assertStringContainsString('id="pay-monthly-filter-team"', $monthlyHtml);
        $this->assertStringContainsString('id="payments-monthly-table"', $monthlyHtml);

        $ltvPage = $this->get(route('reports.ltv'))
            ->assertOk();
        $ltvHtml = (string) $ltvPage->getContent();
        $this->assertNotSame('', trim($ltvHtml));
        $this->assertStringContainsString('id="pay-ltv-filter-team"', $ltvHtml);
        $this->assertStringContainsString('data-column-key="team_title"', $ltvHtml);

        $this->get(route('reports.payments.monthly.total'))
            ->assertOk()
            ->assertJsonStructure(['total_formatted', 'total_raw']);

        $this->get(route('reports.ltv.total'))
            ->assertOk()
            ->assertJsonStructure(['total_formatted', 'total_raw']);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.data', $this->baseDataTableParams() + ['mode' => 'subscription']))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.data', $this->baseDataTableParams()))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', $this->baseDataTableParams() + [
                'yearMonth' => '2026-08',
                'mode' => 'subscription',
            ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', $this->baseDataTableParams() + [
                'user' => $this->student->id,
            ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('reports.payments.monthly.columns-settings.get'))
            ->assertOk();
        $this->getJson(route('reports.ltv.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('reports.payments.monthly.columns-settings.save'), [
            'columns' => ['month_title' => true],
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->postJson(route('reports.ltv.columns-settings.save'), [
            'columns' => ['team_title' => true],
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allSectionRoutesPayload(): array
    {
        $ajax = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ];
        $html = ['HTTP_ACCEPT' => 'text/html'];
        $dt = $this->baseDataTableParams();

        return [
            ['method' => 'GET', 'url' => route('reports.payments.monthly'), 'headers' => $html],
            ['method' => 'GET', 'url' => route('reports.ltv'), 'headers' => $html],
            ['method' => 'GET', 'url' => route('reports.payments.monthly.total'), 'headers' => $ajax],
            ['method' => 'GET', 'url' => route('reports.ltv.total'), 'headers' => $ajax],
            [
                'method' => 'GET',
                'url' => route('reports.payments.monthly.data', $dt + ['mode' => 'subscription']),
                'headers' => $ajax,
            ],
            [
                'method' => 'GET',
                'url' => route('reports.ltv.data', $dt),
                'headers' => $ajax,
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payments.monthly.payments', $dt + [
                    'yearMonth' => '2026-08',
                    'mode' => 'subscription',
                ]),
                'headers' => $ajax,
            ],
            [
                'method' => 'GET',
                'url' => route('reports.ltv.user_payments', $dt + ['user' => $this->student->id]),
                'headers' => $ajax,
            ],
            ['method' => 'GET', 'url' => route('reports.payments.monthly.columns-settings.get'), 'headers' => $ajax],
            ['method' => 'GET', 'url' => route('reports.ltv.columns-settings.get'), 'headers' => $ajax],
            [
                'method' => 'POST',
                'url' => route('reports.payments.monthly.columns-settings.save'),
                'data' => ['columns' => ['month_title' => true]],
                'headers' => $ajax,
            ],
            [
                'method' => 'POST',
                'url' => route('reports.ltv.columns-settings.save'),
                'data' => ['columns' => ['team_title' => true]],
                'headers' => $ajax,
            ],
        ];
    }

    /**
     * @return array<string, int>
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
}
