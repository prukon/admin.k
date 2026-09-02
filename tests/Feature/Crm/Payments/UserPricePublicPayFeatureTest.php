<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Models\UserPricePublicPayLink;
use App\Services\Payments\UserPricePublicPayService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Публичная СБП-оплата месячного начисления: /pm/{code}, Init при открытии, статусы.
 *
 * @see docs/documentation/payments.html
 * @see docs/documentation/setting-prices-payment-notifications.html
 */
final class UserPricePublicPayFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_UP_PUBLIC',
            'token_password' => 'PWD_UP_PUBLIC',
            'e2c_terminal_key' => 'E2C_UP',
            'e2c_token_password' => 'E2C_UP_PWD',
        ]);

        ['team' => $this->team] = $this->seedTbankTeamChainForStudent(
            entityOverrides: [
                'organization_name' => 'ИП Monthly SBP',
                'tax_id' => '770099887766',
                'city' => 'Казань',
            ],
        );
        $this->team->update(['title' => 'Группа Monthly SBP']);

        $this->student = $this->user;

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
            'name' => 'Фикс публичная оплата',
            'price_cents' => 11000,
        ]);
    }

    private function createUnpaidUserPrice(int $priceCents = 11000): UserPrice
    {
        return UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => $priceCents,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    private function issueLink(UserPrice $userPrice): UserPricePublicPayLink
    {
        $url = app(UserPricePublicPayService::class)->shareUrlForNotification($userPrice);
        $this->assertNotSame('', $url);
        $this->assertStringContainsString('/pm/', $url);

        $link = UserPricePublicPayLink::query()->where('users_price_id', $userPrice->id)->first();
        $this->assertNotNull($link);

        return $link;
    }

    private function fakeTbankPublicPayHttp(): void
    {
        $initCount = 0;
        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$initCount) {
            $url = $request->url();

            if (str_contains($url, '/v2/Init')) {
                $initCount++;
                $body = $request->data();

                return Http::response([
                    'Success' => true,
                    'PaymentId' => (string) (8800110000 + $initCount),
                    'PaymentURL' => 'https://pay.example.test/',
                    'OrderId' => (string) ($body['OrderId'] ?? 'order-up'),
                    'ErrorCode' => '0',
                ], 200);
            }

            if (str_contains($url, '/v2/GetState')) {
                return Http::response([
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Status' => 'NEW',
                    'Amount' => 11000,
                ], 200);
            }

            if (str_contains($url, '/v2/GetQr')) {
                $dataType = (string) ($request->data()['DataType'] ?? 'IMAGE');

                return Http::response([
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Data' => $dataType === 'PAYLOAD'
                        ? 'https://qr.nspk.ru/up-test-payload'
                        : 'https://qr.nspk.ru/up-test',
                ], 200);
            }

            if (str_contains($url, '/v2/Cancel')) {
                return Http::response([
                    'Success' => true,
                    'Status' => 'CANCELED',
                    'ErrorCode' => '0',
                ], 200);
            }

            return Http::response(['Success' => false, 'Message' => 'unexpected'], 500);
        });
    }

    public function test_unknown_short_code_returns_404(): void
    {
        $this->get(route('up.public.pay.short', ['code' => 'abcdefghjk']))
            ->assertNotFound();
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->get(route('up.public.pay', ['token' => str_repeat('a', 64)]))
            ->assertNotFound();
    }

    public function test_share_url_is_empty_when_tbank_missing(): void
    {
        Partner::query()->whereKey($this->partner->id)->update([
            'tinkoff_partner_id' => null,
        ]);
        \App\Models\PaymentSystem::query()
            ->whereNull('partner_id')
            ->where('name', 'tbank')
            ->update(['is_enabled' => false]);

        $userPrice = $this->createUnpaidUserPrice();
        $url = app(UserPricePublicPayService::class)->shareUrlForNotification($userPrice);

        $this->assertSame('', $url);
        $this->assertDatabaseCount('user_price_public_pay_links', 0);
    }

    public function test_guest_opens_short_url_inits_sbp_and_sees_checkout(): void
    {
        $this->fakeTbankPublicPayHttp();
        $userPrice = $this->createUnpaidUserPrice(11000);
        $link = $this->issueLink($userPrice);
        $this->assertNull($link->tinkoff_payment_id);

        Auth::logout();

        $this->get(route('up.public.pay.short', ['code' => $link->short_code]))
            ->assertOk()
            ->assertSee('Оплата через СБП', false)
            ->assertSee('Группа Monthly SBP', false)
            ->assertSee('ИП Monthly SBP', false)
            ->assertSee('110&nbsp;₽', false)
            ->assertDontSee('110.00', false)
            ->assertDontSee(',00', false);

        $link->refresh();
        $this->assertSame('8800110001', (string) $link->tinkoff_payment_id);
        $this->assertDatabaseHas('payables', [
            'id' => $link->payable_id,
            'type' => 'monthly_fee',
            'amount_cents' => 11000,
            'user_id' => $this->student->id,
        ]);
    }

    public function test_long_token_url_opens_same_qr_page(): void
    {
        $this->fakeTbankPublicPayHttp();
        $link = $this->issueLink($this->createUnpaidUserPrice());

        $this->get(route('up.public.pay', ['token' => $link->token]))
            ->assertOk()
            ->assertSee('Оплата через СБП', false);
    }

    public function test_qr_endpoints_ok_for_guest_after_page_open(): void
    {
        $this->fakeTbankPublicPayHttp();
        $link = $this->issueLink($this->createUnpaidUserPrice());

        Auth::logout();
        $this->get(route('up.public.pay.short', ['code' => $link->short_code]))->assertOk();

        $this->getJson(route('up.public.pay.qr.json', ['token' => $link->token]))
            ->assertOk()
            ->assertJsonPath('Success', true)
            ->assertJsonStructure(['Success', 'Data']);

        $this->getJson(route('up.public.pay.qr.payload', ['token' => $link->token]))
            ->assertOk()
            ->assertJsonPath('Success', true)
            ->assertJsonPath('Data', 'https://qr.nspk.ru/up-test-payload');

        $this->getJson(route('up.public.pay.qr.state', ['token' => $link->token]))
            ->assertOk()
            ->assertJsonPath('Success', true)
            ->assertJsonPath('Status', 'NEW');
    }

    public function test_qr_state_returns_404_when_payment_not_initialized(): void
    {
        $userPrice = $this->createUnpaidUserPrice();
        $token = bin2hex(random_bytes(32));
        UserPricePublicPayLink::query()->create([
            'users_price_id' => $userPrice->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'short_code' => 'abcdEfghJk',
            'expires_at' => now()->addDay(),
        ]);

        $this->getJson(route('up.public.pay.qr.state', ['token' => $token]))
            ->assertNotFound()
            ->assertJsonPath('Success', false);
    }

    public function test_expired_link_shows_status_page(): void
    {
        $userPrice = $this->createUnpaidUserPrice();
        $token = bin2hex(random_bytes(32));
        UserPricePublicPayLink::query()->create([
            'users_price_id' => $userPrice->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'short_code' => 'expCode12a',
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('up.public.pay.short', ['code' => 'expCode12a']))
            ->assertOk()
            ->assertSee('Ссылка недействительна', false);
    }

    public function test_already_paid_shows_status_page_and_qr_state_confirmed(): void
    {
        $userPrice = $this->createUnpaidUserPrice();
        $token = bin2hex(random_bytes(32));
        UserPricePublicPayLink::query()->create([
            'users_price_id' => $userPrice->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'short_code' => 'paidCode12',
            'expires_at' => now()->addDay(),
            'tinkoff_payment_id' => '8800990001',
        ]);
        $userPrice->update(['is_paid' => true]);

        $this->get(route('up.public.pay', ['token' => $token]))
            ->assertOk()
            ->assertSee('Оплата получена', false);

        $this->getJson(route('up.public.pay.qr.state', ['token' => $token]))
            ->assertOk()
            ->assertJsonPath('Status', 'CONFIRMED');
    }

    public function test_amount_change_reinits_without_rotating_short_code(): void
    {
        $this->fakeTbankPublicPayHttp();

        $userPrice = $this->createUnpaidUserPrice(11000);
        $link = $this->issueLink($userPrice);
        $code = (string) $link->short_code;

        $this->get(route('up.public.pay.short', ['code' => $code]))->assertOk();
        $link->refresh();
        $firstPaymentId = (string) $link->tinkoff_payment_id;
        $this->assertSame('8800110001', $firstPaymentId);

        $userPrice->update(['price_cents' => 22000]);

        $this->get(route('up.public.pay.short', ['code' => $code]))
            ->assertOk()
            ->assertSee('220', false);

        $link->refresh();
        $this->assertSame($code, (string) $link->short_code);
        $this->assertNotSame($firstPaymentId, (string) $link->tinkoff_payment_id);
        $this->assertDatabaseHas('payables', [
            'id' => $link->payable_id,
            'type' => 'monthly_fee',
            'amount_cents' => 22000,
        ]);
        $this->assertDatabaseHas('payables', [
            'type' => 'monthly_fee',
            'amount_cents' => 11000,
            'status' => 'cancelled',
        ]);
    }

    public function test_authenticated_user_without_payment_permission_can_open_page(): void
    {
        $this->fakeTbankPublicPayHttp();
        $link = $this->issueLink($this->createUnpaidUserPrice());

        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true])
            ->get(route('up.public.pay.short', ['code' => $link->short_code]))
            ->assertOk()
            ->assertSee('Оплата через СБП', false);
    }

    public function test_postpay_before_pay_available_does_not_issue_url(): void
    {
        $this->travelTo(now()->setTimezone('Europe/Moscow')->setDate(2026, 9, 15)->setTime(12, 0));

        $postpay = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'name' => 'Постоплата',
            'price_cents' => 100000,
        ]);

        $userPrice = UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 100000,
            'is_paid' => 0,
            'lesson_package_id' => $postpay->id,
        ]);

        $url = app(UserPricePublicPayService::class)->shareUrlForNotification($userPrice);
        $this->assertSame('', $url);
        $this->assertDatabaseCount('user_price_public_pay_links', 0);
    }

    /**
     * UX-баг: страница месяца шарит blade с абонементом. Если контроллер не передаст
     * qrJsonUrl/qrPayloadUrl/qrStateUrl, inline JS уйдёт на /pay/ulp/ — QR мёртвый.
     */
    public function test_monthly_checkout_html_uses_up_qr_routes_not_ulp_fallback(): void
    {
        $this->fakeTbankPublicPayHttp();
        $link = $this->issueLink($this->createUnpaidUserPrice(11000));

        Auth::logout();

        $html = $this->get(route('up.public.pay.short', ['code' => $link->short_code]))
            ->assertOk()
            ->assertSee('<title>Оплата</title>', false)
            ->assertDontSee('Оплата абонемента', false)
            ->assertSee('110&nbsp;₽', false)
            ->assertSee('К оплате:', false)
            ->assertSee('Поставщик услуг', false)
            ->getContent();

        $this->assertStringContainsString('\/pay\/up\/'.$link->token.'\/qr\/json', $html);
        $this->assertStringContainsString('\/pay\/up\/'.$link->token.'\/qr\/payload', $html);
        $this->assertStringContainsString('\/pay\/up\/'.$link->token.'\/qr\/state', $html);
        $this->assertStringNotContainsString('\/pay\/ulp\/', $html);
    }

    public function test_long_token_url_html_uses_same_up_qr_routes(): void
    {
        $this->fakeTbankPublicPayHttp();
        $link = $this->issueLink($this->createUnpaidUserPrice());

        $html = $this->get(route('up.public.pay', ['token' => $link->token]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('\/pay\/up\/'.$link->token.'\/qr\/json', $html);
        $this->assertStringNotContainsString('\/pay\/ulp\/', $html);
    }

    public function test_checkout_shows_kopecks_with_comma_when_not_round(): void
    {
        $this->fakeTbankPublicPayHttp();
        $link = $this->issueLink($this->createUnpaidUserPrice(11050));

        Auth::logout();

        $this->get(route('up.public.pay.short', ['code' => $link->short_code]))
            ->assertOk()
            ->assertSee('110,50&nbsp;₽', false)
            ->assertDontSee('110.50', false)
            ->assertDontSee('110,50.00', false);
    }

    /**
     * @return list<array{method: string, url: string, acceptJson?: bool}>
     */
    private function publicPayGetEndpoints(UserPricePublicPayLink $link): array
    {
        $token = (string) $link->token;

        return [
            ['method' => 'GET', 'url' => route('up.public.pay.short', ['code' => $link->short_code])],
            ['method' => 'GET', 'url' => route('up.public.pay', ['token' => $token])],
            ['method' => 'GET', 'url' => route('up.public.pay.qr.json', ['token' => $token]), 'acceptJson' => true],
            ['method' => 'GET', 'url' => route('up.public.pay.qr.payload', ['token' => $token]), 'acceptJson' => true],
            ['method' => 'GET', 'url' => route('up.public.pay.qr.state', ['token' => $token]), 'acceptJson' => true],
        ];
    }

    public function test_public_pay_get_endpoints_return_200_for_guest_after_page_open(): void
    {
        $this->fakeTbankPublicPayHttp();
        $link = $this->issueLink($this->createUnpaidUserPrice());

        Auth::logout();
        $this->get(route('up.public.pay.short', ['code' => $link->short_code]))->assertOk();

        foreach ($this->publicPayGetEndpoints($link) as $item) {
            $headers = ! empty($item['acceptJson'])
                ? ['HTTP_ACCEPT' => 'application/json']
                : ['HTTP_ACCEPT' => 'text/html'];

            $response = $this->call($item['method'], $item['url'], [], [], [], $headers);

            $this->assertNotSame(500, $response->getStatusCode(), "{$item['method']} {$item['url']}");
            $this->assertSame(200, $response->getStatusCode(), "Гость: {$item['method']} {$item['url']}");
        }
    }

    public function test_public_pay_get_endpoints_return_200_for_manager_with_rights(): void
    {
        $this->fakeTbankPublicPayHttp();
        $link = $this->issueLink($this->createUnpaidUserPrice());

        $this->get(route('up.public.pay.short', ['code' => $link->short_code]))->assertOk();

        foreach ($this->publicPayGetEndpoints($link) as $item) {
            $headers = ! empty($item['acceptJson'])
                ? ['HTTP_ACCEPT' => 'application/json']
                : ['HTTP_ACCEPT' => 'text/html'];

            $response = $this->call($item['method'], $item['url'], [], [], [], $headers);

            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function test_public_pay_get_endpoints_return_200_for_user_without_payment_permission(): void
    {
        $this->fakeTbankPublicPayHttp();
        $link = $this->issueLink($this->createUnpaidUserPrice());

        $denied = $this->createUserWithoutPermission('payment.method.tbankSBP', $this->partner);
        $this->actingAs($denied)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('up.public.pay.short', ['code' => $link->short_code]))->assertOk();

        foreach ($this->publicPayGetEndpoints($link) as $item) {
            $headers = ! empty($item['acceptJson'])
                ? ['HTTP_ACCEPT' => 'application/json']
                : ['HTTP_ACCEPT' => 'text/html'];

            $response = $this->call($item['method'], $item['url'], [], [], [], $headers);

            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function test_unsupported_methods_on_public_pay_do_not_return_500_or_empty_200(): void
    {
        $this->fakeTbankPublicPayHttp();
        $link = $this->issueLink($this->createUnpaidUserPrice());
        $urls = [
            route('up.public.pay.short', ['code' => $link->short_code]),
            route('up.public.pay', ['token' => $link->token]),
            route('up.public.pay.qr.json', ['token' => $link->token]),
            route('up.public.pay.qr.payload', ['token' => $link->token]),
            route('up.public.pay.qr.state', ['token' => $link->token]),
        ];

        Auth::logout();

        foreach ($urls as $url) {
            foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->call($method, $url, [], [], [], ['HTTP_ACCEPT' => 'application/json']);

                $this->assertNotSame(
                    500,
                    $response->getStatusCode(),
                    "{$method} {$url} → 500"
                );
                $this->assertContains(
                    $response->getStatusCode(),
                    [404, 405, 419],
                    "{$method} {$url} → {$response->getStatusCode()}"
                );
            }
        }
    }

    public function test_qr_json_unknown_token_returns_json_404_not_html_500(): void
    {
        Auth::logout();

        $this->getJson(route('up.public.pay.qr.json', ['token' => str_repeat('b', 64)]))
            ->assertNotFound()
            ->assertJsonPath('Success', false);

        $this->getJson(route('up.public.pay.qr.payload', ['token' => str_repeat('c', 64)]))
            ->assertNotFound()
            ->assertJsonPath('Success', false);

        $this->getJson(route('up.public.pay.qr.state', ['token' => str_repeat('d', 64)]))
            ->assertNotFound()
            ->assertJsonPath('Success', false);
    }

    public function test_manual_paid_row_shows_already_paid_and_does_not_init_tbank(): void
    {
        Http::fake(fn () => Http::response(['Success' => false, 'Message' => 'tbank-should-not-run'], 500));

        $userPrice = $this->createUnpaidUserPrice();
        $userPrice->update([
            'is_paid' => false,
            'is_manual_paid' => true,
        ]);

        $token = bin2hex(random_bytes(32));
        UserPricePublicPayLink::query()->create([
            'users_price_id' => $userPrice->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'short_code' => 'manualPaid1',
            'expires_at' => now()->addDay(),
        ]);

        Auth::logout();

        $this->get(route('up.public.pay.short', ['code' => 'manualPaid1']))
            ->assertOk()
            ->assertSee('Оплата получена', false)
            ->assertDontSee('Оплата через СБП', false)
            ->assertDontSee('Поставщик услуг', false);

        Http::assertNothingSent();
        $this->assertDatabaseCount('payables', 0);
    }

    public function test_already_paid_does_not_call_tbank_init(): void
    {
        Http::fake(fn () => Http::response(['Success' => false], 500));

        $userPrice = $this->createUnpaidUserPrice();
        $userPrice->update(['is_paid' => true]);
        $token = bin2hex(random_bytes(32));
        UserPricePublicPayLink::query()->create([
            'users_price_id' => $userPrice->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'short_code' => 'paidNoInit1',
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('up.public.pay', ['token' => $token]))
            ->assertOk()
            ->assertSee('Оплата получена', false);

        Http::assertNothingSent();
    }

    public function test_parent_sees_unavailable_not_500_if_tbank_turned_off_after_link_issued(): void
    {
        $userPrice = $this->createUnpaidUserPrice();
        $link = $this->issueLink($userPrice);

        Partner::query()->whereKey($this->partner->id)->update([
            'tinkoff_partner_id' => null,
        ]);
        \App\Models\PaymentSystem::query()
            ->whereNull('partner_id')
            ->where('name', 'tbank')
            ->update(['is_enabled' => false]);

        Http::fake(fn () => Http::response(['Success' => false], 500));
        Auth::logout();

        $this->get(route('up.public.pay.short', ['code' => $link->short_code]))
            ->assertOk()
            ->assertSee('Оплата недоступна', false)
            ->assertDontSee('Оплата через СБП', false)
            ->assertDontSee('Поставщик услуг', false);

        Http::assertNothingSent();
    }

    public function test_share_url_empty_when_amount_above_sbp_maximum(): void
    {
        $userPrice = $this->createUnpaidUserPrice(100000001);
        $url = app(UserPricePublicPayService::class)->shareUrlForNotification($userPrice);

        $this->assertSame('', $url);
        $this->assertDatabaseCount('user_price_public_pay_links', 0);
    }

    public function test_share_url_empty_when_already_paid(): void
    {
        $userPrice = $this->createUnpaidUserPrice();
        $userPrice->update(['is_paid' => true]);

        $url = app(UserPricePublicPayService::class)->shareUrlForNotification($userPrice);

        $this->assertSame('', $url);
        $this->assertDatabaseCount('user_price_public_pay_links', 0);
    }

    public function test_opening_link_after_amount_raised_above_sbp_max_shows_error_not_500(): void
    {
        $userPrice = $this->createUnpaidUserPrice(11000);
        $link = $this->issueLink($userPrice);
        $userPrice->update(['price_cents' => 100000001]);

        Http::fake(fn () => Http::response(['Success' => false], 500));
        Auth::logout();

        $this->get(route('up.public.pay.short', ['code' => $link->short_code]))
            ->assertOk()
            ->assertSee('Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.', false)
            ->assertDontSee('Оплата через СБП', false);

        Http::assertNothingSent();
    }
}
