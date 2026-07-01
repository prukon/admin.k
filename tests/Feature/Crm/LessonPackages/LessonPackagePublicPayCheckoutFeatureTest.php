<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserLessonPackagePublicPayLink;
use App\Services\TeamUserSyncService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Публичная оплата абонемента (/pay/ulp): группа, «Поставщик услуг», сумма без копеек,
 * контракты endpoint'ов и контроль доступа.
 *
 * Публичные endpoint'ы (без auth):
 * - GET /pay/ulp/{token}
 * - GET /pay/ulp/{token}/qr/json
 * - GET /pay/ulp/{token}/qr/payload
 * - GET /pay/ulp/{token}/qr/state
 *
 * Admin endpoint:
 * - POST /admin/lesson-packages/assignments/{assignment}/public-pay-link (lessonPackages.view)
 */
final class LessonPackagePublicPayCheckoutFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{team: Team, assignment: UserLessonPackage, expectedLabel: string}
     */
    private function seedCheckoutContext(float $fee = 110.0): array
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_ULP_CHECKOUT',
            'token_password' => 'PWD_ULP_CHECKOUT',
            'e2c_terminal_key' => 'E2C_ULP',
            'e2c_token_password' => 'E2C_PWD',
        ]);

        Partner::query()->whereKey($this->partner->id)->update([
            'tinkoff_partner_id' => null,
            'tax_id' => null,
        ]);
        $this->partner->refresh();

        ['team' => $team] = $this->seedTbankTeamChainForStudent(
            entityOverrides: [
                'organization_name' => 'ИП Public ULP',
                'tax_id' => '770011223344',
                'city' => 'Казань',
            ],
        );
        $team->update(['title' => 'Группа ULP Public']);

        $assignment = $this->createUnpaidAssignment($fee, (int) $team->id);

        return [
            'team' => $team->fresh(),
            'assignment' => $assignment,
            'expectedLabel' => 'ИП Public ULP, ИНН 770011223344, Казань',
        ];
    }

    private function createUnpaidAssignment(float $fee, ?int $teamId = null): UserLessonPackage
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'ULP checkout test',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        return UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'team_id' => $teamId,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => number_format($fee, 2, '.', ''),
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
    }

    private function fakeTbankPublicPayHttp(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();

            if (str_contains($url, '/v2/Init')) {
                $body = $request->data();

                return Http::response([
                    'Success' => true,
                    'PaymentId' => '8800112233',
                    'PaymentURL' => 'https://pay.example.test/',
                    'OrderId' => (string) ($body['OrderId'] ?? 'order-ulp'),
                    'ErrorCode' => '0',
                ], 200);
            }

            if (str_contains($url, '/v2/GetState')) {
                return Http::response([
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Status' => 'NEW',
                ], 200);
            }

            if (str_contains($url, '/v2/GetQr')) {
                return Http::response([
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Data' => 'https://qr.nspk.ru/ulp-test',
                ], 200);
            }

            return Http::response(['Success' => false, 'Message' => 'unexpected'], 500);
        });
    }

    private function issuePublicPayToken(UserLessonPackage $assignment): string
    {
        $this->grantPermission('lessonPackages.view');
        $this->fakeTbankPublicPayHttp();

        $issue = $this->postJson(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $assignment->id]),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $url = (string) $issue->json('url');
        $token = substr($url, strrpos($url, '/') + 1);
        $this->assertSame(64, strlen($token));

        return $token;
    }

    /**
     * @return list<array{method: string, url: string, acceptJson?: bool}>
     */
    private function publicPayEndpoints(string $token): array
    {
        return [
            ['method' => 'GET', 'url' => route('ulp.public.pay', ['token' => $token])],
            ['method' => 'GET', 'url' => route('ulp.public.pay.qr.json', ['token' => $token]), 'acceptJson' => true],
            ['method' => 'GET', 'url' => route('ulp.public.pay.qr.payload', ['token' => $token]), 'acceptJson' => true],
            ['method' => 'GET', 'url' => route('ulp.public.pay.qr.state', ['token' => $token]), 'acceptJson' => true],
        ];
    }

    // -------------------------------------------------------------------------
    // Checkout UI: группа, поставщик, сумма без копеек
    // -------------------------------------------------------------------------

    public function test_public_pay_qr_page_shows_team_service_provider_and_amount_without_kopecks(): void
    {
        $ctx = $this->seedCheckoutContext(110.0);
        $token = $this->issuePublicPayToken($ctx['assignment']);

        $this->get(route('ulp.public.pay', ['token' => $token]))
            ->assertOk()
            ->assertViewIs('payment.ulp-public-pay')
            ->assertViewHas('showTbankLegalEntityBlock', true)
            ->assertViewHas('serviceProviderTeamTitle', 'Группа ULP Public')
            ->assertViewHas('serviceProviderLabel', $ctx['expectedLabel'])
            ->assertViewHas('amountRubFormatted', '110')
            ->assertSee('К оплате:', false)
            ->assertSee('110&nbsp;₽', false)
            ->assertDontSee('110.00', false)
            ->assertSee('Группа', false)
            ->assertSee('Группа ULP Public', false)
            ->assertSee('Поставщик услуг', false)
            ->assertSee($ctx['expectedLabel'], false);
    }

    public function test_public_pay_amount_rounds_to_whole_rubles_without_kopecks(): void
    {
        $ctx = $this->seedCheckoutContext(110.50);
        $token = $this->issuePublicPayToken($ctx['assignment']);

        $this->get(route('ulp.public.pay', ['token' => $token]))
            ->assertOk()
            ->assertViewHas('amountRubFormatted', '111')
            ->assertSee('111&nbsp;₽', false)
            ->assertDontSee('110.50', false);
    }

    public function test_public_pay_fallback_text_when_service_provider_label_missing(): void
    {
        $html = view('payment.ulp-public-pay', [
            'token' => str_repeat('a', 64),
            'amountRubFormatted' => '500',
            'successUrl' => url('/'),
            'isMobileClient' => false,
            'showTbankLegalEntityBlock' => true,
            'serviceProviderTeamTitle' => 'Группа без юр. лица',
            'serviceProviderLabel' => null,
        ])->render();

        $this->assertStringContainsString('Группа без юр. лица', $html);
        $this->assertStringContainsString('Обратитесь в школу/клуб.', $html);
    }

    public function test_public_pay_status_pages_do_not_show_service_provider_block(): void
    {
        $assignment = $this->createUnpaidAssignment(500.0);
        UserLessonPackage::query()->whereKey($assignment->id)->update(['is_paid' => true]);

        $paidToken = bin2hex(random_bytes(32));
        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $assignment->id,
            'partner_id' => $this->partner->id,
            'token' => $paidToken,
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('ulp.public.pay', ['token' => $paidToken]))
            ->assertOk()
            ->assertViewIs('payment.ulp-public-status')
            ->assertSee('Оплата получена', false)
            ->assertDontSee('Поставщик услуг', false);

        $configToken = bin2hex(random_bytes(32));
        $unpaid = $this->createUnpaidAssignment(500.0);
        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $unpaid->id,
            'partner_id' => $this->partner->id,
            'token' => $configToken,
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('ulp.public.pay', ['token' => $configToken]))
            ->assertOk()
            ->assertViewIs('payment.ulp-public-status')
            ->assertSee('Оплата недоступна', false)
            ->assertDontSee('Поставщик услуг', false);
    }

    // -------------------------------------------------------------------------
    // Публичные endpoint'ы: контракты, не 500
    // -------------------------------------------------------------------------

    public function test_public_pay_endpoints_ok_for_guest(): void
    {
        $ctx = $this->seedCheckoutContext();
        $token = $this->issuePublicPayToken($ctx['assignment']);

        Auth::logout();

        foreach ($this->publicPayEndpoints($token) as $item) {
            $headers = ! empty($item['acceptJson'])
                ? ['HTTP_ACCEPT' => 'application/json']
                : ['HTTP_ACCEPT' => 'text/html'];

            $response = $this->call($item['method'], $item['url'], [], [], [], $headers);

            $this->assertNotSame(
                500,
                $response->getStatusCode(),
                "{$item['method']} {$item['url']} → 500"
            );
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_public_pay_endpoints_ok_for_authenticated_user_with_permission(): void
    {
        $ctx = $this->seedCheckoutContext();
        $this->grantPermission('lessonPackages.view');
        $token = $this->issuePublicPayToken($ctx['assignment']);

        foreach ($this->publicPayEndpoints($token) as $item) {
            $headers = ! empty($item['acceptJson'])
                ? ['HTTP_ACCEPT' => 'application/json']
                : ['HTTP_ACCEPT' => 'text/html'];

            $response = $this->call($item['method'], $item['url'], [], [], [], $headers);

            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function test_public_pay_qr_json_and_payload_return_expected_structure(): void
    {
        $ctx = $this->seedCheckoutContext();
        $token = $this->issuePublicPayToken($ctx['assignment']);

        // Init T‑Bank выполняется при первом открытии QR-страницы, не при выдаче ссылки.
        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();

        $this->getJson(route('ulp.public.pay.qr.json', ['token' => $token]))
            ->assertOk()
            ->assertJsonPath('Success', true)
            ->assertJsonStructure(['Success', 'Data']);

        $this->getJson(route('ulp.public.pay.qr.payload', ['token' => $token]))
            ->assertOk()
            ->assertJsonPath('Success', true)
            ->assertJsonStructure(['Success', 'Data']);

        $this->getJson(route('ulp.public.pay.qr.state', ['token' => $token]))
            ->assertOk()
            ->assertJsonPath('Success', true)
            ->assertJsonStructure(['Success', 'Status']);
    }

    public function test_public_pay_qr_endpoints_return_404_when_payment_not_initialized(): void
    {
        $assignment = $this->createUnpaidAssignment(500.0);
        $token = bin2hex(random_bytes(32));

        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $assignment->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'expires_at' => now()->addDay(),
            'tinkoff_payment_id' => null,
        ]);

        $this->getJson(route('ulp.public.pay.qr.json', ['token' => $token]))
            ->assertNotFound()
            ->assertJsonPath('Success', false);

        $this->getJson(route('ulp.public.pay.qr.payload', ['token' => $token]))
            ->assertNotFound()
            ->assertJsonPath('Success', false);

        $this->getJson(route('ulp.public.pay.qr.state', ['token' => $token]))
            ->assertNotFound()
            ->assertJsonPath('Success', false);
    }

    // -------------------------------------------------------------------------
    // Admin: POST …/public-pay-link — доступ и контракты
    // -------------------------------------------------------------------------

    public function test_issue_public_pay_link_ajax_contract_with_lesson_packages_view(): void
    {
        $ctx = $this->seedCheckoutContext();
        $this->grantPermission('lessonPackages.view');
        $this->fakeTbankPublicPayHttp();

        $this->postJson(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ctx['assignment']->id]),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJsonStructure(['url']);

        $this->assertDatabaseHas('user_lesson_package_public_pay_links', [
            'user_lesson_package_id' => $ctx['assignment']->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_issue_public_pay_link_non_ajax_post_returns_json_and_creates_link_record(): void
    {
        $ctx = $this->seedCheckoutContext();
        $this->grantPermission('lessonPackages.view');
        $this->fakeTbankPublicPayHttp();

        $response = $this->post(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ctx['assignment']->id]),
            ['_token' => csrf_token()],
        );

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(200, $response->getStatusCode());
        $response->assertJsonStructure(['url']);
        $this->assertNotSame('', (string) $response->json('url'));

        $this->assertDatabaseHas('user_lesson_package_public_pay_links', [
            'user_lesson_package_id' => $ctx['assignment']->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_issue_public_pay_link_forbidden_without_lesson_packages_view(): void
    {
        $ctx = $this->seedCheckoutContext();
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->postJson(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ctx['assignment']->id]),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertForbidden();
    }

    public function test_issue_public_pay_link_guest_is_denied(): void
    {
        $ctx = $this->seedCheckoutContext();
        Auth::logout();

        $response = $this->postJson(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ctx['assignment']->id]),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
    }

    public function test_issue_public_pay_link_returns_not_found_for_foreign_assignment(): void
    {
        $this->grantPermission('lessonPackages.view');

        $foreignStudent = User::factory()->create(['partner_id' => $this->foreignPartner->id]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Foreign ULP',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 5000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $foreignAssignment = UserLessonPackage::query()->create([
            'user_id' => $foreignStudent->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount' => '300.00',
            'is_paid' => false,
            'created_by' => $foreignStudent->id,
        ]);

        $this->postJson(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $foreignAssignment->id]),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertNotFound();
    }
}
