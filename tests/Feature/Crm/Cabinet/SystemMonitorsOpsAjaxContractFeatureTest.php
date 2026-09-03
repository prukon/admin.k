<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Enums\AuditEvent;
use App\Exceptions\Handler;
use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\FiscalReceipt;
use App\Models\MyLog;
use App\Models\OutgoingEmailLog;
use App\Models\Partner;
use App\Models\PaymentIntent;
use App\Models\SchoolLead;
use App\Models\TinkoffPayout;
use App\Models\User;
use App\Services\CloudKassir\CloudKassirService;
use App\Services\SmsRuService;
use App\Services\Tinkoff\TinkoffApiClient;
use App\Support\OpsMonitor;
use App\Support\SchedulerHeartbeat;
use ErrorException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * P1: JSON-контракт GET /cabinet/system-monitors/ops —
 * восемь блоков, окно 24 ч (вход — 72 ч), строки «Сегодня» / «Вчера» — календарный день, все партнёры.
 * 500/шлюзы без PII; auth.recent_* содержит введённые email/пароль/код и IP.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsAjaxContractFeatureTest extends SystemMonitorsTestCase
{
    public function test_snapshot_is_available_when_personal_flag_is_off(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('window_hours', 24)
            ->assertJsonPath('day.turnover', 0)
            ->assertJsonPath('yesterday.payments_count', 0);
        $this->assertIsInt($response->json('day.turnover'));
        $this->assertIsInt($response->json('yesterday.commission'));
    }

    public function test_empty_snapshot_has_ok_and_zero_counters(): void
    {
        $this->asSuperadmin();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('window_hours', 24)
            ->assertJsonPath('till.failed_intents', 0)
            ->assertJsonPath('till.fiscal_errors', 0)
            ->assertJsonPath('errors.count', 0)
            ->assertJsonPath('errors.recent', [])
            ->assertJsonPath('auth.failed_logins', 0)
            ->assertJsonPath('auth.failed_2fa', 0)
            ->assertJsonPath('auth.window_hours', 72)
            ->assertJsonPath('auth.recent_logins', [])
            ->assertJsonPath('auth.recent_2fa', [])
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null)
            ->assertJsonPath('day.turnover', 0)
            ->assertJsonPath('day.commission', 0)
            ->assertJsonPath('day.payments_count', 0)
            ->assertJsonPath('yesterday.turnover', 0)
            ->assertJsonPath('yesterday.commission', 0)
            ->assertJsonPath('yesterday.payments_count', 0);

        $empty = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'ok',
                'window_hours',
                'day' => ['turnover', 'commission', 'payments_count'],
                'yesterday' => ['turnover', 'commission', 'payments_count'],
                'queue' => ['worker', 'scheduler', 'jobs', 'failed_jobs', 'overdue_payouts'],
                'till' => ['overdue_payouts', 'failed_intents', 'fiscal_errors'],
                'errors' => ['count', 'last_class', 'last_message', 'top_class', 'recent'],
                'gateways' => [
                    'tinkoff' => ['last_ok_at', 'last_fail_at', 'last_fail_message', 'last_ok_age_seconds', 'last_fail_age_seconds'],
                    'smsru' => ['last_ok_at', 'last_fail_at', 'last_fail_message', 'last_ok_age_seconds', 'last_fail_age_seconds'],
                    'cloudkassir' => ['last_ok_at', 'last_fail_at', 'last_fail_message', 'last_ok_age_seconds', 'last_fail_age_seconds'],
                ],
                'auth' => ['window_hours', 'failed_logins', 'failed_2fa', 'recent_logins', 'recent_2fa'],
                'welcome' => ['missing_count', 'last_user_id'],
            ]);
        $this->assertIsInt($empty->json('day.turnover'));
        $this->assertIsInt($empty->json('day.commission'));
        $this->assertIsInt($empty->json('day.payments_count'));
        $this->assertIsInt($empty->json('yesterday.turnover'));
        $this->assertIsInt($empty->json('yesterday.commission'));
        $this->assertIsInt($empty->json('yesterday.payments_count'));
    }

    public function test_json_does_not_leak_email_phone_password_or_pan(): void
    {
        $this->asSuperadmin();
        OpsMonitor::recordException(new RuntimeException(
            'boom secret-ops@example.test +79001234567 4111111111111111 password=hunter2'
        ));

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk();

        $raw = (string) $response->getContent();
        $message = (string) $response->json('errors.last_message');
        $this->assertStringNotContainsString('secret-ops@example.test', $raw);
        $this->assertStringNotContainsString('79001234567', $raw);
        $this->assertStringNotContainsString('4111111111111111', $raw);
        $this->assertStringNotContainsString('hunter2', $raw);
        $this->assertArrayNotHasKey('email', $response->json('welcome'));
        $this->assertArrayNotHasKey('password', $response->json());
        $this->assertLessThanOrEqual(OpsMonitor::MESSAGE_LIMIT, mb_strlen($message));
        $this->assertStringContainsString('[email]', $message);
        $this->assertStringContainsString('[secret]', $message);
        $this->assertIsArray($response->json('errors.recent'));
        $this->assertNotEmpty($response->json('errors.recent'));
        $this->assertStringNotContainsString('secret-ops@example.test', (string) $response->json('errors.recent.0.message'));
        $this->assertStringNotContainsString('hunter2', (string) $response->json('errors.recent.0.message'));
    }

    public function test_failed_intents_and_fiscal_errors_use_24h_window(): void
    {
        $this->asSuperadmin();
        $now = now();
        $this->travelTo($now);

        PaymentIntent::factory()->failed()->create([
            'partner_id' => $this->partner->id,
            'created_at' => $now->copy()->subHours(25),
            'updated_at' => $now->copy()->subHours(25),
        ]);
        PaymentIntent::factory()->failed()->create([
            'partner_id' => $this->partner->id,
            'created_at' => $now->copy()->subHours(2),
            'updated_at' => $now->copy()->subHours(2),
        ]);
        PaymentIntent::factory()->paid()->create([
            'partner_id' => $this->partner->id,
        ]);

        FiscalReceipt::factory()->forPartner((int) $this->partner->id)->errored()->create([
            'failed_at' => $now->copy()->subHours(25),
            'updated_at' => $now->copy()->subHours(25),
        ]);
        FiscalReceipt::factory()->forPartner((int) $this->partner->id)->errored()->create([
            'failed_at' => $now->copy()->subHour(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('till.failed_intents', 1)
            ->assertJsonPath('till.fiscal_errors', 1);
    }

    public function test_overdue_payouts_include_other_partners_ignoring_session(): void
    {
        $this->asSuperadmin();
        $other = Partner::factory()->create();

        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $other->id,
            'deal_id' => 'ops-overdue-'.$other->id,
            'amount' => 100,
            'is_final' => false,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->subHour(),
            'completed_at' => null,
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('queue.overdue_payouts', 1)
            ->assertJsonPath('till.overdue_payouts', 1);
    }

    public function test_handler_records_reportable_500_and_skips_validation(): void
    {
        $this->asSuperadmin();
        $handler = $this->app->make(Handler::class);
        $handler->report(new RuntimeException('ops-handler-boom'));

        try {
            throw ValidationException::withMessages(['field' => 'нет']);
        } catch (ValidationException $e) {
            $handler->report($e);
        }

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('errors.count', 1)
            ->assertJsonPath('errors.last_class', 'RuntimeException')
            ->assertJsonPath('errors.top_class', 'RuntimeException');

        $this->assertStringContainsString('ops-handler-boom', (string) $response->json('errors.last_message'));
        $this->assertSame('RuntimeException', $response->json('errors.recent.0.class'));
        $this->assertStringContainsString('ops-handler-boom', (string) $response->json('errors.recent.0.message'));
    }

    public function test_gateway_and_auth_cache_counters_appear_in_snapshot(): void
    {
        $this->asSuperadmin();
        OpsMonitor::recordGatewayOk(OpsMonitor::GATEWAY_TINKOFF);
        OpsMonitor::recordGatewayFail(OpsMonitor::GATEWAY_SMSRU, 'HTTP 502');
        OpsMonitor::recordFailedLogin();
        OpsMonitor::recordFailedLogin();
        OpsMonitor::recordFailedTwoFactor();

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 2)
            ->assertJsonPath('auth.failed_2fa', 1)
            ->assertJsonPath('auth.recent_logins', [])
            ->assertJsonPath('auth.recent_2fa', []);

        $this->assertNotNull($response->json('gateways.tinkoff.last_ok_at'));
        $this->assertSame(0, $response->json('gateways.tinkoff.last_ok_age_seconds'));
        $this->assertNotNull($response->json('gateways.smsru.last_fail_at'));
        $this->assertSame('HTTP 502', $response->json('gateways.smsru.last_fail_message'));
        $this->assertNull($response->json('gateways.cloudkassir.last_ok_at'));
    }

    public function test_converted_lead_without_sent_welcome_is_counted(): void
    {
        $this->asSuperadmin();
        $student = $this->createUserWithRole('user', $this->partner, [
            'email' => 'welcome-missing@example.test',
            'created_at' => now()->subHour(),
        ]);
        SchoolLead::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Лид',
            'phone' => '+7 900 000-00-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id' => $student->id,
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 1)
            ->assertJsonPath('welcome.last_user_id', $student->id);
    }

    public function test_sent_welcome_email_excludes_lead_from_missing(): void
    {
        $this->asSuperadmin();
        $student = $this->createUserWithRole('user', $this->partner, [
            'email' => 'welcome-sent@example.test',
            'created_at' => now()->subHour(),
        ]);
        SchoolLead::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Лид',
            'phone' => '+7 900 000-00-02',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id' => $student->id,
        ]);
        OutgoingEmailLog::query()->create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'mailable_class' => ClientWelcomeCredentialsMail::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $student->id,
            'to_summary' => 'welcome-sent@example.test',
            'sent_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null);
    }

    public function test_auth_hourly_bucket_outside_72h_is_ignored(): void
    {
        $this->asSuperadmin();
        $this->travelTo(now()->subHours(73));
        OpsMonitor::recordFailedLogin();
        $this->travelBack();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 0);
    }

    public function test_auth_hourly_bucket_inside_72h_is_counted(): void
    {
        $this->asSuperadmin();
        $this->travelTo(now()->subHours(25));
        OpsMonitor::recordFailedLogin();
        $this->travelBack();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 1);
    }

    public function test_wrong_password_increments_failed_logins(): void
    {
        $this->asSuperadmin();
        Auth::logout();
        $typedPassword = 'wrong-password-for-ops';

        $this->from(route('login'))->post(route('login'), [
            'email' => $this->user->email,
            'password' => $typedPassword,
        ])->assertSessionHasErrors('password');

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 1)
            ->assertJsonPath('auth.recent_logins.0.email', $this->user->email)
            ->assertJsonPath('auth.recent_logins.0.password', $typedPassword)
            ->assertJsonPath('auth.recent_logins.0.user_found', true);

        $this->assertNotNull($this->getJson($this->opsUrl(), $this->ajaxHeaders())->json('auth.recent_logins.0.ip'));
        $this->assertNotNull($this->getJson($this->opsUrl(), $this->ajaxHeaders())->json('auth.recent_logins.0.at'));

        $log = MyLog::query()->where('event', AuditEvent::AuthLoginFailed->value)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString($this->user->email, (string) $log->description);
        $this->assertStringNotContainsString($typedPassword, (string) $log->description);
        $this->assertSame((int) $this->user->id, (int) $log->user_id);
    }

    public function test_unknown_email_counts_as_failed_login_and_keeps_typed_credentials(): void
    {
        $this->asSuperadmin();
        Auth::logout();
        $typedEmail = 'nobody-ops@example.test';
        $typedPassword = 'whatever-password';

        $this->from(route('login'))->post(route('login'), [
            'email' => $typedEmail,
            'password' => $typedPassword,
        ])->assertSessionHasErrors('email');

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 1)
            ->assertJsonPath('auth.recent_logins.0.email', $typedEmail)
            ->assertJsonPath('auth.recent_logins.0.password', $typedPassword)
            ->assertJsonPath('auth.recent_logins.0.user_found', false);

        $log = MyLog::query()->where('event', AuditEvent::AuthLoginFailed->value)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString($typedEmail, (string) $log->description);
        $this->assertStringNotContainsString($typedPassword, (string) $log->description);
        $this->assertNull($log->user_id);
    }

    public function test_wrong_two_factor_code_increments_failed_2fa(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_code' => Hash::make('123456'),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id])
            ->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_2fa', 1)
            ->assertJsonPath('auth.recent_2fa.0.email', $this->user->email)
            ->assertJsonPath('auth.recent_2fa.0.code', '000000');
    }

    public function test_expired_two_factor_code_does_not_count_as_failed_2fa(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_code' => Hash::make('123456'),
            'two_factor_expires_at' => now()->subMinute(),
        ])->save();

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id])
            ->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_2fa', 0);
    }

    public function test_missing_two_factor_code_does_not_count_as_failed_2fa(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ])->save();

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id])
            ->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_2fa', 0);
    }

    public function test_smsru_http_success_is_recorded_at_the_call_site(): void
    {
        $this->asSuperadmin();
        config([
            'services.sms_ru.api_id' => 'test-smsru-key',
            'services.sms_ru.from' => 'kidscrm',
        ]);
        Http::fake([
            'sms.ru/*' => Http::response([
                'status' => 'OK',
                'sms' => ['79001234567' => ['status' => 'OK']],
            ], 200),
        ]);

        $this->assertTrue(app(SmsRuService::class)->send('79001234567', 'ops-ok'));

        $ok = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk();
        $this->assertNotNull($ok->json('gateways.smsru.last_ok_at'));
        $this->assertNull($ok->json('gateways.smsru.last_fail_at'));
    }

    public function test_smsru_http_failure_is_recorded_at_the_call_site(): void
    {
        $this->asSuperadmin();
        config([
            'services.sms_ru.api_id' => 'test-smsru-key',
            'services.sms_ru.from' => 'kidscrm',
        ]);
        Http::fake(['sms.ru/*' => Http::response('gateway-down', 502)]);

        $result = app(SmsRuService::class)->send('79001234567', 'ops-fail');
        $this->assertSame('HTTP error: 502', $result);

        $fail = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk();
        $this->assertNotNull($fail->json('gateways.smsru.last_fail_at'));
        $this->assertSame('HTTP 502', $fail->json('gateways.smsru.last_fail_message'));
        $this->assertNull($fail->json('gateways.smsru.last_ok_at'));
    }

    public function test_empty_smsru_api_id_does_not_record_gateway_fail(): void
    {
        $this->asSuperadmin();
        config(['services.sms_ru.api_id' => '']);

        $result = app(SmsRuService::class)->send('79001234567', 'no-key');
        $this->assertSame('Empty api_id', $result);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('gateways.smsru.last_ok_at', null)
            ->assertJsonPath('gateways.smsru.last_fail_at', null);
    }

    public function test_tinkoff_http_error_is_recorded_at_the_call_site(): void
    {
        $this->asSuperadmin();
        Http::fake(['*' => Http::response('nope', 502)]);

        try {
            TinkoffApiClient::post('https://securepay.tinkoff.ru', '/v2/Init', ['Amount' => 1]);
            $this->fail('T‑Bank HTTP 502 после retry должен пробрасываться');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('502', $e->getMessage());
        }

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk();
        $this->assertNotNull($response->json('gateways.tinkoff.last_fail_at'));
        $this->assertStringContainsString('502', (string) $response->json('gateways.tinkoff.last_fail_message'));
        $this->assertNull($response->json('gateways.tinkoff.last_ok_at'));
    }

    public function test_cloudkassir_http_success_is_recorded_at_the_call_site(): void
    {
        $this->asSuperadmin();
        config([
            'services.cloudkassir.public_id' => 'pk-ops-test',
            'services.cloudkassir.api_secret' => 'sk-ops-test',
            'services.cloudkassir.base_url' => 'https://api.cloudpayments.ru',
        ]);
        $this->app->forgetInstance(CloudKassirService::class);

        Http::fake(['api.cloudpayments.ru/*' => Http::response(['Success' => true], 200)]);
        app(CloudKassirService::class)->getReceiptStatus('ops-ext-1');

        $ok = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk();
        $this->assertNotNull($ok->json('gateways.cloudkassir.last_ok_at'));
        $this->assertNull($ok->json('gateways.cloudkassir.last_fail_at'));
    }

    public function test_cloudkassir_non_json_failure_is_recorded_at_the_call_site(): void
    {
        $this->asSuperadmin();
        config([
            'services.cloudkassir.public_id' => 'pk-ops-test',
            'services.cloudkassir.api_secret' => 'sk-ops-test',
            'services.cloudkassir.base_url' => 'https://api.cloudpayments.ru',
        ]);
        $this->app->forgetInstance(CloudKassirService::class);

        Http::fake(['api.cloudpayments.ru/*' => Http::response('not-json', 500)]);
        try {
            app(CloudKassirService::class)->getReceiptStatus('ops-ext-2');
            $this->fail('CloudKassir non-JSON должен бросать');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('non-JSON', $e->getMessage());
        }

        $fail = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk();
        $this->assertNotNull($fail->json('gateways.cloudkassir.last_fail_at'));
        $this->assertStringContainsString('non-JSON', (string) $fail->json('gateways.cloudkassir.last_fail_message'));
        $this->assertNull($fail->json('gateways.cloudkassir.last_ok_at'));
    }

    public function test_handler_skips_http_4xx_and_csrf_mismatch(): void
    {
        $this->asSuperadmin();
        $handler = $this->app->make(Handler::class);
        $handler->report(new NotFoundHttpException('missing'));
        $handler->report(new HttpException(403, 'forbidden-ops'));
        $handler->report(new TokenMismatchException('csrf-ops'));

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('errors.count', 0)
            ->assertJsonPath('errors.last_class', null);
    }

    public function test_last_message_is_truncated_to_80_and_top_class_is_the_most_frequent(): void
    {
        $this->asSuperadmin();
        OpsMonitor::recordException(new RuntimeException('один'));
        OpsMonitor::recordException(new InvalidArgumentException('два'));
        OpsMonitor::recordException(new InvalidArgumentException('три'));
        OpsMonitor::recordException(new InvalidArgumentException('четыре'));
        OpsMonitor::recordException(new RuntimeException(str_repeat('я', 120)));

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('errors.count', 5)
            ->assertJsonPath('errors.last_class', 'RuntimeException')
            ->assertJsonPath('errors.top_class', 'InvalidArgumentException');

        $message = (string) $response->json('errors.last_message');
        $this->assertSame(OpsMonitor::MESSAGE_LIMIT, mb_strlen($message));
        $this->assertTrue(str_ends_with($message, '…'));
        $this->assertCount(5, $response->json('errors.recent'));
        $this->assertSame('RuntimeException', $response->json('errors.recent.0.class'));
    }

    public function test_view_exception_unwraps_to_previous_class_and_relativizes_view_path(): void
    {
        $this->asSuperadmin();
        $inner = new ErrorException('Undefined variable $demo');
        $absolute = base_path('resources/views/ops-demo.blade.php');
        $view = new ViewException(
            $inner->getMessage().' (View: '.$absolute.')',
            0,
            1,
            $inner->getFile() ?: __FILE__,
            $inner->getLine() ?: 1,
            $inner
        );
        OpsMonitor::recordException($view);

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('errors.count', 1)
            ->assertJsonPath('errors.last_class', 'ErrorException')
            ->assertJsonPath('errors.top_class', 'ErrorException')
            ->assertJsonPath('errors.recent.0.class', 'ErrorException');

        $message = (string) $response->json('errors.last_message');
        $this->assertStringContainsString('Undefined variable $demo', $message);
        $this->assertStringContainsString('resources/views/ops-demo.blade.php', $message);
        $this->assertStringNotContainsString(base_path(), $message);
        $this->assertStringNotContainsString('ViewException', (string) $response->json('errors.last_class'));
        $this->assertSame($message, $response->json('errors.recent.0.message'));
    }

    public function test_ignition_view_exception_wrapper_unwraps_to_previous_class(): void
    {
        $this->asSuperadmin();
        $inner = new ErrorException('Undefined variable $ignition');
        $mapped = new \Spatie\LaravelIgnition\Exceptions\ViewException(
            $inner->getMessage(),
            0,
            1,
            $inner->getFile() ?: __FILE__,
            $inner->getLine() ?: 1,
            $inner
        );
        OpsMonitor::recordException($mapped);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('errors.last_class', 'ErrorException')
            ->assertJsonPath('errors.recent.0.class', 'ErrorException');
    }

    public function test_recent_ring_keeps_newest_20(): void
    {
        $this->asSuperadmin();
        for ($i = 1; $i <= 21; $i++) {
            OpsMonitor::recordException(new RuntimeException('n'.$i));
        }

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk();

        $recent = $response->json('errors.recent');
        $this->assertIsArray($recent);
        $this->assertCount(OpsMonitor::RECENT_LIMIT, $recent);
        $this->assertSame('n21', $recent[0]['message']);
        $this->assertSame('n2', $recent[19]['message']);
        foreach ($recent as $row) {
            $this->assertArrayHasKey('class', $row);
            $this->assertArrayHasKey('message', $row);
            $this->assertArrayHasKey('route', $row);
            $this->assertArrayHasKey('path', $row);
            $this->assertArrayHasKey('at', $row);
        }
    }

    public function test_recent_drops_entries_older_than_24h(): void
    {
        $this->asSuperadmin();
        $now = now();
        $this->travelTo($now->copy()->subHours(25));
        OpsMonitor::recordException(new RuntimeException('too-old'));
        $this->travelTo($now);
        OpsMonitor::recordException(new RuntimeException('fresh'));

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk();

        $recent = $response->json('errors.recent');
        $this->assertCount(1, $recent);
        $this->assertSame('fresh', $recent[0]['message']);
    }

    public function test_recent_path_is_request_path_without_query_string(): void
    {
        $this->asSuperadmin();
        $this->actingAs($this->user)
            ->get(route('dashboard', ['email' => 'secret-ops@example.test', 'token' => 'abc123']));

        OpsMonitor::recordException(new RuntimeException('from-dashboard'));

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('errors.recent.0.class', 'RuntimeException');

        $path = (string) $response->json('errors.recent.0.path');
        $route = (string) $response->json('errors.recent.0.route');
        $raw = (string) $response->getContent();
        $this->assertNotSame('', $path);
        $this->assertStringNotContainsString('?', $path);
        $this->assertStringNotContainsString('secret-ops@example.test', $path);
        $this->assertStringNotContainsString('secret-ops@example.test', $raw);
        $this->assertStringNotContainsString('token=', $path);
        $this->assertSame('dashboard', $route);
    }

    public function test_handler_report_unwraps_view_exception_to_cause_and_relative_view(): void
    {
        $this->asSuperadmin();
        $inner = new ErrorException('Undefined variable $handlerView');
        $view = new ViewException(
            $inner->getMessage().' (View: '.base_path('resources/views/handler-ops.blade.php').')',
            0,
            1,
            $inner->getFile() ?: __FILE__,
            $inner->getLine() ?: 1,
            $inner
        );
        $this->app->make(Handler::class)->report($view);

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('errors.count', 1)
            ->assertJsonPath('errors.last_class', 'ErrorException')
            ->assertJsonPath('errors.recent.0.class', 'ErrorException');

        $message = (string) $response->json('errors.last_message');
        $this->assertStringContainsString('Undefined variable $handlerView', $message);
        $this->assertStringContainsString('resources/views/handler-ops.blade.php', $message);
        $this->assertStringNotContainsString(base_path(), $message);
        $this->assertStringNotContainsString('ViewException', (string) $response->json('errors.last_class'));
    }

    public function test_view_exception_without_previous_keeps_view_exception_class(): void
    {
        $this->asSuperadmin();
        OpsMonitor::recordException(new ViewException('plain view boom', 0, 1, __FILE__, __LINE__));

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('errors.last_class', 'ViewException')
            ->assertJsonPath('errors.recent.0.class', 'ViewException');

        $this->assertStringContainsString('plain view boom', (string) $response->json('errors.last_message'));
    }

    public function test_nested_view_exception_unwraps_to_root_cause(): void
    {
        $this->asSuperadmin();
        $inner = new ErrorException('Undefined variable $nested');
        $mid = new ViewException(
            $inner->getMessage().' (View: '.base_path('resources/views/nested-mid.blade.php').')',
            0,
            1,
            __FILE__,
            1,
            $inner
        );
        $outer = new ViewException($mid->getMessage(), 0, 1, __FILE__, 1, $mid);
        OpsMonitor::recordException($outer);

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('errors.last_class', 'ErrorException');

        $message = (string) $response->json('errors.last_message');
        $this->assertStringContainsString('Undefined variable $nested', $message);
        $this->assertStringContainsString('resources/views/nested-mid.blade.php', $message);
        $this->assertStringNotContainsString(base_path(), $message);
    }

    public function test_broken_error_cache_does_not_turn_ops_into_500(): void
    {
        $this->asSuperadmin();
        Cache::put('ops:errors:recent', 'not-an-array', now()->addHour());
        Cache::put('ops:errors:last', 15, now()->addHour());
        Cache::put('ops:errors:hour:'.now()->format('YmdH'), 'nope', now()->addHour());

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('errors.count', 0)
            ->assertJsonPath('errors.last_class', null)
            ->assertJsonPath('errors.last_message', null)
            ->assertJsonPath('errors.recent', []);
    }

    public function test_student_with_permission_sees_recent_without_pii(): void
    {
        $student = $this->createUserWithRole('user', $this->partner);
        $this->grantSystemMonitorsView($student);
        OpsMonitor::recordException(new RuntimeException(
            'student-ops secret-ops@example.test password=hunter2'
        ));

        $response = $this->actingInCurrentPartner($student)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('errors.recent.0.class', 'RuntimeException');

        $raw = (string) $response->getContent();
        $this->assertStringNotContainsString('secret-ops@example.test', $raw);
        $this->assertStringNotContainsString('hunter2', $raw);
        $this->assertIsArray($response->json('errors.recent'));
    }

    public function test_failed_intents_and_fiscal_of_other_partner_are_counted_despite_session(): void
    {
        $this->asSuperadmin();
        $other = Partner::factory()->create();

        PaymentIntent::factory()->failed()->create([
            'partner_id' => $other->id,
            'updated_at' => now()->subHour(),
        ]);
        FiscalReceipt::factory()->forPartner((int) $other->id)->errored()->create([
            'failed_at' => now()->subHour(),
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('till.failed_intents', 1)
            ->assertJsonPath('till.fiscal_errors', 1);
    }

    public function test_fiscal_error_without_failed_at_uses_updated_at_inside_24h(): void
    {
        $this->asSuperadmin();
        FiscalReceipt::factory()->forPartner((int) $this->partner->id)->errored()->create([
            'failed_at' => null,
            'updated_at' => now()->subHours(2),
        ]);
        FiscalReceipt::factory()->forPartner((int) $this->partner->id)->errored()->create([
            'failed_at' => null,
            'updated_at' => now()->subHours(25),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('till.fiscal_errors', 1);
    }

    public function test_future_and_already_sent_payouts_are_not_overdue(): void
    {
        $this->asSuperadmin();
        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'deal_id' => 'ops-future-'.$this->partner->id,
            'amount' => 100,
            'is_final' => false,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addHour(),
            'completed_at' => null,
        ]);
        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'deal_id' => 'ops-sent-'.$this->partner->id,
            'amount' => 100,
            'is_final' => false,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => 'pay-already-sent',
            'when_to_run' => now()->subHour(),
            'completed_at' => null,
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('queue.overdue_payouts', 0)
            ->assertJsonPath('till.overdue_payouts', 0);
    }

    public function test_admin_overlay_counts_other_school_overdue_while_queues_page_does_not(): void
    {
        $this->asAdmin();
        $this->grantSystemMonitorsView($this->user);
        $this->grantPermissionToActor($this->user, 'settings.view');
        $this->grantPermissionToActor($this->user, 'settings.queues.view');

        $other = Partner::factory()->create();
        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $other->id,
            'deal_id' => 'ops-other-overdue-'.$other->id,
            'amount' => 100,
            'is_final' => false,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->subHour(),
            'completed_at' => null,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('queue.overdue_payouts', 1)
            ->assertJsonPath('till.overdue_payouts', 1);

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson(route('admin.setting.queues.status'), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.overdue_scheduled_payouts_count', 0);
    }

    public function test_failed_welcome_mail_still_counts_as_missing(): void
    {
        $this->asSuperadmin();
        $student = $this->createUserWithRole('user', $this->partner, [
            'email' => 'welcome-failed@example.test',
            'created_at' => now()->subHour(),
        ]);
        SchoolLead::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Лид',
            'phone' => '+7 900 000-00-03',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id' => $student->id,
        ]);
        OutgoingEmailLog::query()->create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_FAILED,
            'mailable_class' => ClientWelcomeCredentialsMail::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $student->id,
            'to_summary' => 'welcome-failed@example.test',
            'sent_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 1)
            ->assertJsonPath('welcome.last_user_id', $student->id);
    }

    public function test_sent_welcome_without_mailable_class_matched_by_subject(): void
    {
        $this->asSuperadmin();
        $student = $this->createUserWithRole('user', $this->partner, [
            'email' => 'welcome-legacy@example.test',
            'created_at' => now()->subHour(),
        ]);
        SchoolLead::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Лид',
            'phone' => '+7 900 000-00-05',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id' => $student->id,
        ]);
        OutgoingEmailLog::query()->create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'mailable_class' => null,
            'notifiable_type' => null,
            'notifiable_id' => null,
            'to_summary' => 'welcome-legacy@example.test',
            'subject' => ClientWelcomeCredentialsMail::SUBJECT_PREFIX.' — '.$this->partner->title,
            'sent_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null);
    }

    public function test_sent_other_mail_without_mailable_class_still_counts_as_missing_welcome(): void
    {
        $this->asSuperadmin();
        $student = $this->createUserWithRole('user', $this->partner, [
            'email' => 'welcome-other-subject@example.test',
            'created_at' => now()->subHour(),
        ]);
        SchoolLead::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Лид',
            'phone' => '+7 900 000-00-06',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id' => $student->id,
        ]);
        OutgoingEmailLog::query()->create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'mailable_class' => null,
            'notifiable_type' => null,
            'notifiable_id' => null,
            'to_summary' => 'welcome-other-subject@example.test',
            'subject' => 'Новая заявка с сайта — '.$this->partner->title,
            'sent_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 1)
            ->assertJsonPath('welcome.last_user_id', $student->id);
    }

    public function test_sent_non_welcome_mailable_to_same_email_is_still_missing(): void
    {
        $this->asSuperadmin();
        $student = $this->createUserWithRole('user', $this->partner, [
            'email' => 'welcome-payment@example.test',
            'created_at' => now()->subHour(),
        ]);
        SchoolLead::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Лид',
            'phone' => '+7 900 000-00-07',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id' => $student->id,
        ]);
        OutgoingEmailLog::query()->create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'mailable_class' => \App\Mail\PaymentNotificationMail::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $student->id,
            'to_summary' => 'welcome-payment@example.test',
            'subject' => ClientWelcomeCredentialsMail::SUBJECT_PREFIX.' — '.$this->partner->title,
            'sent_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 1)
            ->assertJsonPath('welcome.last_user_id', $student->id);
    }

    public function test_user_without_lead_and_lead_older_than_24h_are_not_missing_welcome(): void
    {
        $this->asSuperadmin();
        $this->createUserWithRole('user', $this->partner, [
            'email' => 'no-lead@example.test',
            'created_at' => now()->subHour(),
        ]);
        $old = $this->createUserWithRole('user', $this->partner, [
            'email' => 'old-welcome@example.test',
            'created_at' => now()->subHours(25),
        ]);
        SchoolLead::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Старый лид',
            'phone' => '+7 900 000-00-04',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id' => $old->id,
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null);
    }

    public function test_queue_worker_and_scheduler_heartbeats_appear_in_overlay_json(): void
    {
        $this->asSuperadmin();
        Cache::put('queue:monitor:last_heartbeat_at', now()->timestamp, now()->addHours(2));
        SchedulerHeartbeat::touch();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('queue.worker.code', 'alive')
            ->assertJsonPath('queue.scheduler.code', 'alive');
    }
}
