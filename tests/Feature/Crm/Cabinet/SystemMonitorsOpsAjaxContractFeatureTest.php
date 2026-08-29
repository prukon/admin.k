<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Exceptions\Handler;
use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\FiscalReceipt;
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
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * P1: JSON-контракт GET /cabinet/system-monitors/ops —
 * шесть блоков, окно 24 ч, все партнёры, без PII.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsAjaxContractFeatureTest extends SystemMonitorsTestCase
{
    public function test_snapshot_is_available_when_personal_flag_is_off(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('window_hours', 24);
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
            ->assertJsonPath('auth.failed_logins', 0)
            ->assertJsonPath('auth.failed_2fa', 0)
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null)
            ->assertJsonStructure([
                'ok',
                'window_hours',
                'queue' => ['worker', 'scheduler', 'jobs', 'failed_jobs', 'overdue_payouts'],
                'till' => ['overdue_payouts', 'failed_intents', 'fiscal_errors'],
                'errors' => ['count', 'last_class', 'last_message', 'top_class'],
                'gateways' => [
                    'tinkoff' => ['last_ok_at', 'last_fail_at', 'last_fail_message', 'last_ok_age_seconds', 'last_fail_age_seconds'],
                    'smsru' => ['last_ok_at', 'last_fail_at', 'last_fail_message', 'last_ok_age_seconds', 'last_fail_age_seconds'],
                    'cloudkassir' => ['last_ok_at', 'last_fail_at', 'last_fail_message', 'last_ok_age_seconds', 'last_fail_age_seconds'],
                ],
                'auth' => ['failed_logins', 'failed_2fa'],
                'welcome' => ['missing_count', 'last_user_id'],
            ]);
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
            ->assertJsonPath('auth.failed_2fa', 1);

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

    public function test_auth_hourly_bucket_outside_24h_is_ignored(): void
    {
        $this->asSuperadmin();
        $this->travelTo(now()->subHours(25));
        OpsMonitor::recordFailedLogin();
        $this->travelBack();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 0);
    }

    public function test_wrong_password_increments_failed_logins(): void
    {
        $this->asSuperadmin();
        Auth::logout();

        $this->from(route('login'))->post(route('login'), [
            'email' => $this->user->email,
            'password' => 'wrong-password-for-ops',
        ])->assertSessionHasErrors('password');

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 1);
    }

    public function test_unknown_email_does_not_count_as_failed_password(): void
    {
        $this->asSuperadmin();
        Auth::logout();

        $this->from(route('login'))->post(route('login'), [
            'email' => 'nobody-ops@example.test',
            'password' => 'whatever-password',
        ])->assertSessionHasErrors('email');

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 0);
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
            ->assertJsonPath('auth.failed_2fa', 1);
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
