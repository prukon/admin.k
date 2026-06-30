<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\LessonPackage;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Payable;
use App\Models\Team;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Models\UserCustomPayment;
use App\Services\TeamUserSyncService;
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

    private function defaultStudentTeam(): Team
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        return $team;
    }

    public function test_payment_index_ok_and_out_sum_comes_from_user_period_prices_when_custom_payment(): void
    {
        $team = $this->defaultStudentTeam();
        $upp = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'date_start' => '2026-09-01',
            'date_end' => '2026-09-30',
            'amount' => '777.00',
            'is_paid' => 0,
        ]);

        $response = $this->post(route('payment'), [
            'payment_kind' => 'custom_payment',
            'custom_payment_id' => $upp->id,
            'paymentDate' => 'Дополнительный платеж: 01.09.2026 - 30.09.2026',
            'outSum' => '1.00',
        ]);

        $response->assertOk();
        $response->assertViewIs('payment.paymentUser');
        $response->assertViewHas('outSum', '777.00');
        $response->assertViewHas('paymentKind', 'custom_payment');
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

        $this->seedGlobalTbank([
                    'terminal_key' => 'TERM_TEST',
                    'token_password' => 'PWD_TEST',
                    'e2c_terminal_key' => 'E2C_TERM',
                    'e2c_token_password' => 'E2C_PWD',
                ]);

        $this->seedTbankTeamChainForStudent(shopCode: 'SHOP-TEST');

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

    public function test_tinkoff_card_init_uses_amount_from_user_period_prices_when_custom_payment_and_sends_user_period_price_id_in_data(): void
    {
        $this->grantTbankCardPermission();

        $this->seedGlobalTbank([
                    'terminal_key' => 'TERM_TEST',
                    'token_password' => 'PWD_TEST',
                    'e2c_terminal_key' => 'E2C_TERM',
                    'e2c_token_password' => 'E2C_PWD',
                ]);

        $chain = $this->seedTbankTeamChainForStudent(shopCode: 'SHOP-TEST');

        $team = $chain['team'];
        $upp = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $team->id,
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
                    'PaymentURL' => 'https://example.test/pay-custom-payment',
                ], 200);
            }
            return Http::response(['Success' => false], 500);
        });

        $response = $this->post(route('payment.tinkoff.pay'), [
            'payment_kind' => 'custom_payment',
            'custom_payment_id' => $upp->id,
            'paymentDate' => 'Дополнительный платеж',
        ]);

        $response->assertRedirect();
        $this->assertSame(32100, (int) $capturedAmount);
        $this->assertIsArray($capturedData);
        $this->assertSame((string) $upp->id, (string) ($capturedData['user_period_price_id'] ?? ''));

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame('custom_payment_fee', (string) $payable->type);
        $this->assertSame((int) $upp->id, (int) ($payable->meta['user_period_price_id'] ?? 0));
        $this->assertSame((int) $team->id, (int) ($payable->meta['team_id'] ?? 0));
    }

    private function seedLessonPackageAssignment(float $feeAmount, ?Team $team = null): UserLessonPackage
    {
        if ($team === null) {
            $team = $this->defaultStudentTeam();
        }

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'ULP payment flow',
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
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => number_format($feeAmount, 2, '.', ''),
            'is_paid' => false,
        ]);
    }

    public function test_payment_index_ignores_request_out_sum_for_lesson_package(): void
    {
        $ulp = $this->seedLessonPackageAssignment(444.0);

        $response = $this->post(route('payment'), [
            'payment_kind' => 'lesson_package',
            'user_lesson_package_id' => $ulp->id,
            'paymentDate' => 'ignored label',
            'outSum' => '1.00',
        ]);

        $response->assertOk();
        $response->assertViewHas('outSum', '444.00');
        $response->assertViewHas('paymentKind', 'lesson_package');
        $response->assertViewHas('userLessonPackageId', (int) $ulp->id);
    }

    /**
     * T‑Bank (карта): для lesson_package Init без outSum в запросе — Amount из fee_amount.
     */
    public function test_tinkoff_card_init_lesson_package_without_out_sum_uses_fee_amount(): void
    {
        $this->grantTbankCardPermission();

        $this->seedGlobalTbank([
                    'terminal_key' => 'TERM_TEST',
                    'token_password' => 'PWD_TEST',
                    'e2c_terminal_key' => 'E2C_TERM',
                    'e2c_token_password' => 'E2C_PWD',
                ]);

        $chain = $this->seedTbankTeamChainForStudent(shopCode: 'SHOP-TEST');

        $ulp = $this->seedLessonPackageAssignment(612.5, $chain['team']);

        $capturedAmount = null;
        Http::fake(function ($request) use (&$capturedAmount) {
            if (str_contains($request->url(), '/v2/Init')) {
                $capturedAmount = $request->data()['Amount'] ?? null;

                return Http::response([
                    'Success' => true,
                    'PaymentId' => 888003,
                    'PaymentURL' => 'https://example.test/pay-ulp',
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $response = $this->post(route('payment.tinkoff.pay'), [
            'payment_kind' => 'lesson_package',
            'user_lesson_package_id' => $ulp->id,
            'paymentDate' => 'ignored',
        ]);

        $response->assertRedirect();
        $this->assertSame(61250, (int) $capturedAmount);

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame('lesson_package_fee', (string) $payable->type);
        $this->assertSame((int) $ulp->id, (int) ($payable->meta['user_lesson_package_id'] ?? 0));
        $this->assertSame('612.50', (string) $payable->amount);
    }

    /**
     * Robokassa: для lesson_package сумма в редиректе из fee_amount, не из POST.
     */
    public function test_robokassa_pay_lesson_package_redirect_uses_fee_amount_from_db(): void
    {
        $this->grantRobokassaPaymentPermission();

        PaymentSystem::factory()
            ->robokassa()
            ->create(['partner_id' => $this->partner->id]);

        $ulp = $this->seedLessonPackageAssignment(888.0);

        $response = $this->post(route('payment.pay'), [
            'payment_kind' => 'lesson_package',
            'user_lesson_package_id' => $ulp->id,
            'paymentDate' => 'ignored',
            'outSum' => '1.00',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('OutSum=888.00', $response->headers->get('Location') ?? '');
        $intent = PaymentIntent::query()->latest('id')->first();
        $this->assertNotNull($intent);
        $this->assertSame('888.00', (string) $intent->out_sum);
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
