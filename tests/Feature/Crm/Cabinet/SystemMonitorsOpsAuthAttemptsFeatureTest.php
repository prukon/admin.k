<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Support\OpsMonitor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Crm\Audit\Concerns\InteractsWithMyLogsAudit;

/**
 * P1: неуспешный вход и 2FA — запись в пульт (72 ч) и my_logs без пароля.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsAuthAttemptsFeatureTest extends SystemMonitorsTestCase
{
    use InteractsWithMyLogsAudit;

    public function test_guest_wrong_password_redirects_with_password_error_and_records_attempt(): void
    {
        $this->asSuperadmin();
        $email = (string) $this->user->email;
        $typed = 'wrong-password-for-ops';
        Auth::logout();

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $email,
            'password' => $typed,
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('password');
        $this->assertSame('Неправильный пароль.', session('errors')->first('password'));

        $ops = $this->opsSnapshot();
        $ops->assertOk()
            ->assertJsonPath('auth.failed_logins', 1)
            ->assertJsonPath('auth.recent_logins.0.email', $email)
            ->assertJsonPath('auth.recent_logins.0.password', $typed)
            ->assertJsonPath('auth.recent_logins.0.user_found', true);
        $this->assertNotNull($ops->json('auth.recent_logins.0.ip'));
        $this->assertNotNull($ops->json('auth.recent_logins.0.at'));

        $log = $this->latestFailedLoginLog();
        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::AuthLoginFailed->value, $log->event);
        $this->assertStringContainsString($email, (string) $log->description);
        $this->assertStringNotContainsString($typed, (string) $log->description);
        $this->assertSame((int) $this->user->id, (int) $log->user_id);
    }

    public function test_guest_unknown_email_redirects_with_email_error_and_still_counts(): void
    {
        $this->asSuperadmin();
        Auth::logout();
        $typedEmail = 'nobody-ops-auth@example.test';
        $typedPassword = 'typed-unknown-secret';

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $typedEmail,
            'password' => $typedPassword,
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertSame('Такой email не найден.', session('errors')->first('email'));
        $this->assertFalse(session('errors')->has('password'));

        $this->opsSnapshot()
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 1)
            ->assertJsonPath('auth.recent_logins.0.email', $typedEmail)
            ->assertJsonPath('auth.recent_logins.0.password', $typedPassword)
            ->assertJsonPath('auth.recent_logins.0.user_found', false);

        $log = $this->latestFailedLoginLog();
        $this->assertNotNull($log);
        $this->assertStringContainsString($typedEmail, (string) $log->description);
        $this->assertStringNotContainsString($typedPassword, (string) $log->description);
        $this->assertNull($log->user_id);
    }

    public function test_ajax_wrong_password_is_not_empty_200_and_still_records(): void
    {
        $this->asSuperadmin();
        $email = (string) $this->user->email;
        $typed = 'ajax-wrong-password';
        Auth::logout();

        $response = $this->from(route('login'))->postJson(route('login'), [
            'email' => $email,
            'password' => $typed,
        ], $this->ajaxHeaders());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 422,
            'AJAX неуспешный вход: 302 или 422, получено '.$response->getStatusCode()
        );
        if ($response->status() === 422) {
            $response->assertJsonValidationErrors('password');
        } else {
            $response->assertSessionHasErrors('password');
        }

        $this->opsSnapshot()
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 1)
            ->assertJsonPath('auth.recent_logins.0.password', $typed);
    }

    public function test_empty_login_fields_fail_validation_and_do_not_count_as_failed_entry(): void
    {
        $this->asSuperadmin();
        Auth::logout();

        $html = $this->from(route('login'))->post(route('login'), [
            'email' => '',
            'password' => '',
        ]);
        $this->assertNotSame(500, $html->getStatusCode());
        $html->assertSessionHasErrors(['email', 'password']);

        $json = $this->from(route('login'))->postJson(route('login'), [
            'email' => '',
            'password' => '',
        ], $this->ajaxHeaders());
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);

        $this->opsSnapshot()
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 0)
            ->assertJsonPath('auth.recent_logins', []);
        $this->assertNull($this->latestFailedLoginLog());
    }

    public function test_successful_login_does_not_count_as_failed_entry(): void
    {
        $this->asSuperadmin();
        $email = (string) $this->user->email;
        Auth::logout();

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $email,
            'password' => 'password',
        ]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
        $this->assertAuthenticated();

        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 0)
            ->assertJsonPath('auth.recent_logins', []);

        $this->assertNull($this->latestFailedLoginLog());
        $this->assertSame(
            1,
            MyLog::query()->where('event', AuditEvent::AuthLogin->value)->count()
        );
    }

    public function test_logged_in_guest_middleware_blocks_login_post_without_recording_failure(): void
    {
        $this->asSuperadmin();

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $this->user->email,
            'password' => 'wrong-while-already-in',
        ]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());

        $this->opsSnapshot()
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 0)
            ->assertJsonPath('auth.recent_logins', []);
        $this->assertNull($this->latestFailedLoginLog());
    }

    public function test_login_mutating_methods_other_than_post_are_not_empty_200(): void
    {
        Auth::logout();

        foreach (['PATCH', 'PUT', 'DELETE'] as $method) {
            $html = $this->call($method, route('login'), [
                'email' => 'x@example.test',
                'password' => 'y',
            ]);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML логин не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML логин не пустой 200');
            $this->assertTrue(
                $html->isRedirect() || in_array($html->getStatusCode(), [404, 405, 419], true),
                $method.' HTML логин: отказ, получено '.$html->getStatusCode()
            );

            $json = $this->json($method, route('login'), [
                'email' => 'x@example.test',
                'password' => 'y',
            ], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON логин не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON логин не пустой 200');
        }
    }

    public function test_guest_cannot_verify_two_factor_and_does_not_count(): void
    {
        Auth::logout();

        $json = $this->postJson(route('two-factor.verify'), ['code' => '000000'], $this->ajaxHeaders());
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertTrue($json->isRedirect() || $json->status() === 401);

        $html = $this->from(route('login'))->post(route('two-factor.verify'), ['code' => '000000']);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertTrue($html->isRedirect() || in_array($html->getStatusCode(), [401, 403, 419], true));

        $this->asSuperadmin();
        $this->opsSnapshot()
            ->assertOk()
            ->assertJsonPath('auth.failed_2fa', 0)
            ->assertJsonPath('auth.recent_2fa', []);
    }

    public function test_ajax_invalid_two_factor_code_returns_422_on_code_field(): void
    {
        $this->asSuperadmin();

        $missing = $this->postJson(route('two-factor.verify'), [], $this->ajaxHeaders());
        $this->assertNotSame(500, $missing->getStatusCode());
        $missing->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertSame('Введите код из SMS', $missing->json('errors.code.0'));

        $short = $this->postJson(route('two-factor.verify'), ['code' => '12'], $this->ajaxHeaders());
        $this->assertNotSame(500, $short->getStatusCode());
        $short->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertSame('Код должен состоять из 6 цифр', $short->json('errors.code.0'));

        $this->opsSnapshot()
            ->assertOk()
            ->assertJsonPath('auth.failed_2fa', 0)
            ->assertJsonPath('auth.recent_2fa', []);
    }

    public function test_wrong_two_factor_code_stores_typed_code_in_ops_not_in_my_logs(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_code' => Hash::make('123456'),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        $response = $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => '000000']);
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertSessionHasErrors('code');
        $this->assertSame('Неверный код.', session('errors')->first('code'));

        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_2fa', 1)
            ->assertJsonPath('auth.recent_2fa.0.email', $this->user->email)
            ->assertJsonPath('auth.recent_2fa.0.code', '000000');

        $this->assertSame(0, MyLog::query()->where('event', AuditEvent::AuthLoginFailed->value)->count());
        $descriptions = MyLog::query()->pluck('description')->implode(' ');
        $this->assertStringNotContainsString('000000', $descriptions);
    }

    public function test_expired_two_factor_code_does_not_keep_typed_code_in_hover_list(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_code' => Hash::make('123456'),
            'two_factor_expires_at' => now()->subMinute(),
        ])->save();

        $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('auth.failed_2fa', 0)
            ->assertJsonPath('auth.recent_2fa', []);
    }

    public function test_two_factor_verify_wrong_methods_are_not_empty_200(): void
    {
        $this->asSuperadmin();

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $html = $this->call($method, route('two-factor.verify'), ['code' => '000000']);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML 2FA не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML 2FA не пустой 200');

            $json = $this->json($method, route('two-factor.verify'), ['code' => '000000'], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON 2FA не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON 2FA не пустой 200');
        }
    }

    public function test_admin_without_monitors_permission_does_not_see_cached_typed_password(): void
    {
        $this->asSuperadmin();
        $typed = 'ops-forbidden-password-leak';
        Auth::logout();
        $this->from(route('login'))->post(route('login'), [
            'email' => $this->user->email,
            'password' => $typed,
        ]);

        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $json = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders());
        $json->assertForbidden();
        $this->assertStringNotContainsString($typed, (string) $json->getContent());
        $this->assertArrayNotHasKey('auth', $json->json() ?? []);
    }

    public function test_native_ops_get_includes_recent_logins_and_is_not_blank_html(): void
    {
        $this->asSuperadmin();
        OpsMonitor::recordFailedLogin([
            'email' => 'native-recent@example.test',
            'password' => 'native-secret',
            'ip' => '203.0.113.8',
            'user_found' => false,
        ]);

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->get($this->opsUrl());

        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('auth.window_hours', 72)
            ->assertJsonPath('auth.failed_logins', 1)
            ->assertJsonPath('auth.recent_logins.0.email', 'native-recent@example.test')
            ->assertJsonPath('auth.recent_logins.0.password', 'native-secret')
            ->assertJsonPath('auth.recent_logins.0.ip', '203.0.113.8');
        $this->assertStringContainsString('json', strtolower((string) $response->headers->get('content-type')));
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
        $this->assertStringNotContainsString('id="js-ops-monitors"', (string) $response->getContent());
    }

    public function test_auth_recent_older_than_72_hours_disappears_from_hover_list(): void
    {
        $this->asSuperadmin();
        $this->travelTo(now()->subHours(73));
        OpsMonitor::recordFailedLogin([
            'email' => 'stale-auth@example.test',
            'password' => 'stale-secret',
            'ip' => '203.0.113.1',
            'user_found' => false,
        ]);
        $this->travelBack();

        $this->opsSnapshot()
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 0)
            ->assertJsonPath('auth.recent_logins', []);
    }

    public function test_auth_recent_inside_72_hours_stays_in_hover_list(): void
    {
        $this->asSuperadmin();
        $this->travelTo(now()->subHours(25));
        OpsMonitor::recordFailedLogin([
            'email' => 'day-old@example.test',
            'password' => 'day-old-secret',
            'ip' => '203.0.113.2',
            'user_found' => true,
        ]);
        $this->travelBack();

        $this->opsSnapshot()
            ->assertOk()
            ->assertJsonPath('auth.failed_logins', 1)
            ->assertJsonPath('auth.recent_logins.0.email', 'day-old@example.test')
            ->assertJsonPath('auth.recent_logins.0.password', 'day-old-secret');
    }

    public function test_auth_recent_keeps_newest_40_attempts(): void
    {
        $this->asSuperadmin();
        for ($i = 1; $i <= 41; $i++) {
            OpsMonitor::recordFailedLogin([
                'email' => 'ring-'.$i.'@example.test',
                'password' => 'p'.$i,
                'ip' => '203.0.113.3',
                'user_found' => false,
            ]);
        }

        $response = $this->opsSnapshot()->assertOk();
        $recent = $response->json('auth.recent_logins');
        $this->assertIsArray($recent);
        $this->assertCount(OpsMonitor::AUTH_RECENT_LIMIT, $recent);
        $this->assertSame('ring-41@example.test', $recent[0]['email']);
        $emails = array_column($recent, 'email');
        $this->assertNotContains('ring-1@example.test', $emails);
        $this->assertContains('ring-2@example.test', $emails);
        $this->assertSame(41, $response->json('auth.failed_logins'));
    }

    public function test_typed_password_is_clipped_and_control_chars_stripped_in_ops_json(): void
    {
        $this->asSuperadmin();
        $long = str_repeat('a', OpsMonitor::AUTH_SECRET_LIMIT + 5);
        OpsMonitor::recordFailedLogin([
            'email' => "  clip@example.test \n",
            'password' => "ab\n\tcd\x00".$long,
            'ip' => '203.0.113.4',
            'user_found' => false,
        ]);

        $row = $this->opsSnapshot()->assertOk()->json('auth.recent_logins.0');
        $this->assertSame('clip@example.test', $row['email']);
        $this->assertStringStartsWith('ab cd', $row['password']);
        $this->assertSame(OpsMonitor::AUTH_SECRET_LIMIT, mb_strlen((string) $row['password']));
        $this->assertStringNotContainsString("\n", (string) $row['password']);
    }

    public function test_settings_logs_show_failed_login_without_password_and_hide_checkbox_covers_it(): void
    {
        $this->asSuperadmin();
        $this->grantViewingAllLogs();
        $email = (string) $this->user->email;
        $typed = 'logs-must-not-store';
        Auth::logout();
        $this->from(route('login'))->post(route('login'), [
            'email' => $email,
            'password' => $typed,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $shown = collect($this->getJson(route('settings.logs.data', array_merge(
            $this->auditLogsDataTableParams(),
            [
                'hide_authorizations' => '0',
                'hide_superadmin' => '0',
                'filter_partner_id' => 'all',
            ]
        )))->json('data'));

        $row = $shown->firstWhere('action', AuditEvent::AuthLoginFailed->label());
        $this->assertIsArray($row);
        $this->assertStringContainsString($email, (string) $row['description']);
        $this->assertStringNotContainsString($typed, (string) $row['description']);

        $hidden = collect($this->getJson(route('settings.logs.data', array_merge(
            $this->auditLogsDataTableParams(),
            [
                'hide_authorizations' => '1',
                'hide_superadmin' => '0',
                'filter_partner_id' => 'all',
            ]
        )))->json('data'))->pluck('description')->all();
        $this->assertNotContains($row['description'], $hidden);
    }

    private function opsSnapshot()
    {
        return $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders());
    }

    private function latestFailedLoginLog(): ?MyLog
    {
        return MyLog::query()
            ->where('event', AuditEvent::AuthLoginFailed->value)
            ->latest('id')
            ->first();
    }
}
