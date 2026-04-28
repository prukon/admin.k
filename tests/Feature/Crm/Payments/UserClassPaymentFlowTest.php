<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Payable;
use App\Models\UserPrice;
use App\Models\UserPeriodPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Оплата занятий: страница /payment (POST), сумма из users_prices, права paying.classes,
 * Robokassa/T‑Bank init с периодом.
 */
class UserClassPaymentFlowTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantTbankCardPermission(): void
    {
        $permId = $this->permissionId('payment.method.tbankCard');
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantRobokassaPaymentPermission(): void
    {
        $permId = $this->permissionId('payment.method.robokassa');
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_payment_index_ok_and_out_sum_comes_from_user_period_prices_when_abonement(): void
    {
        $upp = UserPeriodPrice::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'date_start' => '2026-09-01',
            'date_end' => '2026-09-30',
            'amount' => '777.00',
            'is_paid' => 0,
        ]);

        $response = $this->post(route('payment'), [
            'payment_kind' => 'abonement',
            'abonement_id' => $upp->id,
            'paymentDate' => 'Абонемент: 01.09.2026 - 30.09.2026',
            'outSum' => '1.00',
        ]);

        $response->assertOk();
        $response->assertViewIs('payment.paymentUser');
        $response->assertViewHas('outSum', '777.00');
        $response->assertViewHas('paymentKind', 'abonement');
        $response->assertViewHas('userPeriodPriceId', (int) $upp->id);
        $response->assertViewHas('formatedPaymentDate', null);
    }

    /**
     * Неавторизованный запрос перенаправляется (нет доступа к POST /payment).
     */
    public function test_payment_index_redirects_when_guest(): void
    {
        auth()->logout();

        $this->post(route('payment'), [
            'paymentDate' => 'Март 2026',
            'outSum' => '10',
        ])->assertRedirect();
    }

    /**
     * Доступ к выбору способа оплаты только с правом paying.classes.
     */
    public function test_payment_index_forbidden_without_paying_classes_permission(): void
    {
        $denied = $this->createUserWithoutPermission('paying.classes', $this->partner);
        $this->actingAs($denied);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->post(route('payment'), [
            'paymentDate' => 'Март 2026',
            'formatedPaymentDate' => '2026-03-01',
            'outSum' => '1',
        ])->assertForbidden();
    }

    /**
     * С правом paying.classes страница оплаты отвечает 200; сумма берётся из users_prices.
     */
    public function test_payment_index_ok_and_out_sum_comes_from_users_prices_when_monthly(): void
    {
        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2026-03-01', 4150, false)
            ->create();

        $response = $this->post(route('payment'), [
            'paymentDate' => 'Март 2026',
            'formatedPaymentDate' => '2026-03-01',
            'outSum' => '10.00',
        ]);

        $response->assertOk();
        $response->assertViewIs('payment.paymentUser');
        $response->assertViewHas('outSum', '4150.00');
        $response->assertViewHas('formatedPaymentDate', '2026-03-01');
    }

    /**
     * POST outSum игнорируется при месячном периоде (сравнение с ценой в БД).
     */
    public function test_payment_index_ignores_request_out_sum_for_monthly_fee(): void
    {
        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2026-04-01', 99, false)
            ->create();

        $response = $this->post(route('payment'), [
            'paymentDate' => 'Апрель 2026',
            'formatedPaymentDate' => '2026-04-01',
            'outSum' => '1.00',
        ]);

        $response->assertOk();
        $response->assertViewHas('outSum', '99.00');
    }

    /**
     * Без начисления за период — 403 от резолвера.
     */
    public function test_payment_index_forbidden_when_no_user_price_for_period(): void
    {
        $this->post(route('payment'), [
            'paymentDate' => 'Май 2026',
            'formatedPaymentDate' => '2026-05-01',
            'outSum' => '100',
        ])->assertForbidden();
    }

    /**
     * Только русская строка периода (без formatedPaymentDate): парсинг «Февраль» + год не уезжает в март (!F Y).
     */
    public function test_payment_index_resolves_russian_month_only_using_formated_date(): void
    {
        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2026-02-01', 77, false)
            ->create();

        $response = $this->post(route('payment'), [
            'paymentDate' => 'Февраль 2026',
            'outSum' => '1',
        ]);

        $response->assertOk();
        $response->assertViewHas('outSum', '77.00');
        $response->assertViewHas('formatedPaymentDate', '2026-02-01');
    }

    /**
     * Страницы результата Robokassa доступны авторизованному пользователю (200).
     */
    public function test_payment_success_and_fail_pages_return_200(): void
    {
        $this->get(route('payment.success'))->assertOk();
        $this->get(route('payment.fail'))->assertOk();
    }

    /**
     * Без права payment.method.robokassa редирект на оплату недоступен, даже при настроенной ПС и начислении.
     */
    public function test_robokassa_pay_forbidden_without_robokassa_method_permission(): void
    {
        PaymentSystem::factory()
            ->robokassa()
            ->create(['partner_id' => $this->partner->id]);

        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2026-07-01', 2500, false)
            ->create();

        $this->post(route('payment.pay'), [
            'formatedPaymentDate' => '2026-07-01',
            'outSum' => '1.00',
        ])->assertForbidden();
    }

    /**
     * Robokassa: при месячном периоде сумма в редиректе из БД, не из POST.
     */
    public function test_robokassa_pay_redirect_contains_resolved_out_sum_for_monthly(): void
    {
        $this->grantRobokassaPaymentPermission();

        PaymentSystem::factory()
            ->robokassa()
            ->create(['partner_id' => $this->partner->id]);

        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2026-07-01', 2500, false)
            ->create();

        $response = $this->post(route('payment.pay'), [
            'formatedPaymentDate' => '2026-07-01',
            'outSum' => '1.00',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('OutSum=2500.00', $response->headers->get('Location') ?? '');
        $intent = PaymentIntent::query()->latest('id')->first();
        $this->assertNotNull($intent);
        $this->assertSame('2500.00', (string) $intent->out_sum);
    }

    /**
     * T‑Bank (карта): при наличии периода Init получает Amount из users_prices; outSum из формы не обязателен.
     */
    public function test_tinkoff_card_init_uses_amount_from_users_prices_when_monthly(): void
    {
        $this->grantTbankCardPermission();

        $this->partner->tinkoff_partner_id = 'SHOP-TEST';
        $this->partner->save();

        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'is_enabled' => true,
            'settings' => [
                'terminal_key' => 'TERM_TEST',
                'token_password' => 'PWD_TEST',
                'e2c_terminal_key' => 'E2C_TERM',
                'e2c_token_password' => 'E2C_PWD',
            ],
        ]);

        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2025-06-01', 123, false)
            ->create();

        $capturedAmount = null;
        Http::fake(function ($request) use (&$capturedAmount) {
            if (str_contains($request->url(), '/v2/Init')) {
                $capturedAmount = $request->data()['Amount'] ?? null;
                return Http::response([
                    'Success' => true,
                    'PaymentId' => 888001,
                    'PaymentURL' => 'https://example.test/pay-card',
                ], 200);
            }
            return Http::response(['Success' => false], 500);
        });

        $response = $this->post(route('payment.tinkoff.pay'), [
            'formatedPaymentDate' => '2025-06-01',
        ]);

        $response->assertRedirect();
        $this->assertSame(12300, (int) $capturedAmount, 'Amount в копейках должен соответствовать цене из users_prices');
    }

    public function test_tinkoff_card_init_uses_amount_from_user_period_prices_when_abonement_and_sends_user_period_price_id_in_data(): void
    {
        $this->grantTbankCardPermission();

        $this->partner->tinkoff_partner_id = 'SHOP-TEST';
        $this->partner->save();

        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'is_enabled' => true,
            'settings' => [
                'terminal_key' => 'TERM_TEST',
                'token_password' => 'PWD_TEST',
                'e2c_terminal_key' => 'E2C_TERM',
                'e2c_token_password' => 'E2C_PWD',
            ],
        ]);

        $upp = UserPeriodPrice::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'date_start' => '2026-10-01',
            'date_end' => '2026-10-31',
            'amount' => '321.00',
            'is_paid' => 0,
        ]);

        $capturedAmount = null;
        $capturedData = null;
        Http::fake(function ($request) use (&$capturedAmount, &$capturedData) {
            if (str_contains($request->url(), '/v2/Init')) {
                $capturedAmount = $request->data()['Amount'] ?? null;
                $capturedData = $request->data()['DATA'] ?? null;
                return Http::response([
                    'Success' => true,
                    'PaymentId' => 888002,
                    'PaymentURL' => 'https://example.test/pay-abonement',
                ], 200);
            }
            return Http::response(['Success' => false], 500);
        });

        $response = $this->post(route('payment.tinkoff.pay'), [
            'payment_kind' => 'abonement',
            'abonement_id' => $upp->id,
            'paymentDate' => 'Абонемент',
            'outSum' => '1.00',
        ]);

        $response->assertRedirect();
        $this->assertSame(32100, (int) $capturedAmount);
        $this->assertIsArray($capturedData);
        $this->assertSame((string) $upp->id, (string) ($capturedData['user_period_price_id'] ?? ''));

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame('abonement_fee_period', (string) $payable->type);
        $this->assertSame((int) $upp->id, (int) ($payable->meta['user_period_price_id'] ?? 0));
    }

    /**
     * Пользователь с правами оплаты получает 200 на POST /payment при «пустом» месяце без formatedPaymentDate (без резолвера месяца).
     */
    public function test_payment_index_ok_without_monthly_when_only_payment_date_empty_and_no_out_sum_resolution(): void
    {
        $response = $this->post(route('payment'), [
            'paymentDate' => '',
            'outSum' => '500.00',
        ]);

        $response->assertOk();
        $response->assertViewHas('outSum', '500.00');
    }
}
