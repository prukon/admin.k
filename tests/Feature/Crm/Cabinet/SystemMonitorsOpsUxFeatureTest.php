<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Support\OpsMonitor;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * P1: UX оверлея «Пульт» — первый кадр «…», откат при ошибке fetch, опрос 5 с.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsUxFeatureTest extends SystemMonitorsTestCase
{
    public function test_first_open_shows_ellipsis_until_fetch(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        foreach ($this->opsDataRoles() as $role) {
            $this->assertMatchesRegularExpression(
                '/data-role="'.preg_quote($role, '/').'">…<\/span>/u',
                $html,
                'первый кадр должен быть «…» для '.$role
            );
        }
        $this->assertStringContainsString('data-kids-tooltip-hint', $html);
        $this->assertStringContainsString('Неуспешные Init оплаты', $html);
        $this->assertStringContainsString('>Очередь</span>', $html);
        $this->assertStringContainsString('>Касса</span>', $html);
        $this->assertStringContainsString('>500</span>', $html);
        $this->assertStringContainsString('>Шлюзы</span>', $html);
        $this->assertStringContainsString('>Вход</span>', $html);
        $this->assertStringContainsString('Неверный пароль или неизвестный email за 72 часа', $html);
        $this->assertStringContainsString('не в my_logs', $html);
        $this->assertStringContainsString('Неверный код 2FA за 72 часа', $html);
        $this->assertStringContainsString('data-role="auth-logins">…</span>', $html);
        $this->assertStringContainsString('data-role="auth-2fa">…</span>', $html);
        $this->assertStringContainsString('>Welcome</span>', $html);
        $this->assertStringContainsString('Воркер очереди', $html);
        $this->assertStringContainsString('Планировщик cron', $html);
        $this->assertStringContainsString('все школы', $html);
        $this->assertStringContainsString('После опроса здесь будет текст ошибки', $html);
        $this->assertStringNotContainsString('href="/admin/settings/queues"', $html);
        $this->assertStringNotContainsString('data-role="errors-recent"', $html);

        $fiveHundredStart = strpos($html, '>500</span>');
        $gatewaysStart = strpos($html, '>Шлюзы</span>');
        $this->assertNotFalse($fiveHundredStart);
        $this->assertNotFalse($gatewaysStart);
        $this->assertGreaterThan($fiveHundredStart, $gatewaysStart);
        $row = substr($html, $fiveHundredStart, $gatewaysStart - $fiveHundredStart);
        $this->assertStringNotContainsString('href=', $row);
        $this->assertStringNotContainsString('<a ', $row);
    }

    public function test_first_html_does_not_print_cached_last_message(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();
        OpsMonitor::recordException(new RuntimeException('ops-first-html-leak'));

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="js-ops-monitors"', $html);
        $this->assertStringContainsString('data-role="errors-last">…</span>', $html);
        $this->assertStringContainsString('После опроса здесь будет текст ошибки', $html);
        $this->assertStringNotContainsString('ops-first-html-leak', $html);
    }

    public function test_first_html_does_not_print_cached_typed_password(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();
        OpsMonitor::recordFailedLogin([
            'email' => 'first-html@example.test',
            'password' => 'ops-first-html-secret-xyz',
            'ip' => '203.0.113.7',
            'user_found' => false,
        ]);

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="js-ops-monitors"', $html);
        $this->assertStringContainsString('data-role="auth-logins">…</span>', $html);
        $this->assertStringContainsString('Неверный пароль или неизвестный email за 72 часа', $html);
        $this->assertStringNotContainsString('ops-first-html-secret-xyz', $html);
        $this->assertStringNotContainsString('first-html@example.test', $html);
    }

    public function test_monitors_off_keeps_overlay_in_dom_with_ellipsis_and_does_not_turn_body_on(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="js-ops-monitors"', $html);
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
        $this->assertStringContainsString('body:not(.system-monitors-on) .system-monitor', $html);
        foreach (['queue-jobs', 'errors-count', 'welcome-user', 'gw-tinkoff-ok'] as $role) {
            $this->assertMatchesRegularExpression(
                '/data-role="'.preg_quote($role, '/').'">…<\/span>/u',
                $html,
                $role
            );
        }
    }

    public function test_second_superadmin_does_not_get_overlay_from_another_users_flag(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();
        $other = $this->createUserWithRole('superadmin', $this->partner, [
            'system_monitors' => false,
        ]);

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders())
            ->assertOk();

        $mine = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $mine);
        $this->assertStringContainsString('id="js-ops-monitors"', $mine);

        $theirs = $this->actingInCurrentPartner($other)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="js-ops-monitors"', $theirs);
        $this->assertDoesNotMatchRegularExpression(
            '/<body[^>]*\bsystem-monitors-on\b/',
            $theirs,
            'чужой суперадмин не должен опрашивать пульт из-за чужого флага'
        );
        $this->assertMatchesRegularExpression('/data-role="queue-jobs">…<\/span>/u', $theirs);
    }

    public function test_overlay_is_on_cabinet_chat_and_settings_above_online(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        foreach ([route('dashboard'), route('chat.index'), route('admin.setting.setting')] as $url) {
            $html = $this->actingAs($this->user)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('id="js-ops-monitors"', $html, $url);
            $this->assertStringContainsString('id="js-online-users"', $html, $url);
            $this->assertLessThan(
                (int) strpos($html, 'id="js-online-users"'),
                (int) strpos($html, 'id="js-ops-monitors"'),
                $url
            );
        }
    }

    public function test_login_and_landing_do_not_render_ops_overlay(): void
    {
        Auth::logout();

        foreach ([route('login'), route('landing.home')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('id="js-ops-monitors"', $html, $url);
            $this->assertStringNotContainsString('cabinet.system-monitors.ops', $html, $url);
        }
    }

    public function test_admin_without_permission_does_not_see_overlay_even_if_flag_is_on(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="js-ops-monitors"', $html);
    }

    public function test_failed_fetch_clears_stale_numbers(): void
    {
        $result = $this->simulateOpsRenderThenFail();

        $this->assertSame('2', $result['jobs_after_ok']);
        $this->assertSame('—', $result['jobs_after_fail']);
        $this->assertSame('—', $result['errors_after_fail']);
    }

    public function test_failed_and_forbidden_refresh_clear_stale_numbers(): void
    {
        $result = $this->simulateOpsRefreshFailure();

        $this->assertSame('2', $result['jobs_after_ok']);
        $this->assertSame('1', $result['errors_after_ok']);
        $this->assertSame('—', $result['jobs_after_network']);
        $this->assertSame('—', $result['errors_after_network']);
        $this->assertSame('—', $result['jobs_after_forbidden']);
        $this->assertSame('—', $result['welcome_after_forbidden']);
        $this->assertGreaterThan(0, $result['interval_ms']);
        $this->assertSame(5000, $result['interval_ms']);
    }

    public function test_render_paints_worker_scheduler_ages_and_welcome_id(): void
    {
        $ok = $this->simulateOpsRenderSnapshot([
            'ok' => true,
            'queue' => [
                'worker' => ['code' => 'alive'],
                'scheduler' => ['code' => 'dead'],
                'jobs' => 3,
                'failed_jobs' => 0,
                'overdue_payouts' => 2,
            ],
            'till' => ['overdue_payouts' => 2, 'failed_intents' => 0, 'fiscal_errors' => 4],
            'errors' => [
                'count' => 1,
                'last_class' => 'RuntimeException',
                'top_class' => 'QueryException',
            ],
            'gateways' => [
                'tinkoff' => ['last_ok_age_seconds' => 0, 'last_fail_age_seconds' => null],
                'smsru' => ['last_ok_age_seconds' => 90, 'last_fail_age_seconds' => 4000],
                'cloudkassir' => ['last_ok_age_seconds' => null, 'last_fail_age_seconds' => null],
            ],
            'auth' => ['failed_logins' => 0, 'failed_2fa' => 1],
            'welcome' => ['missing_count' => 1, 'last_user_id' => 42],
        ]);

        $this->assertSame('жив', $ok['queue-worker']);
        $this->assertSame('cron!', $ok['queue-scheduler']);
        $this->assertSame('3', $ok['queue-jobs']);
        $this->assertSame('2', $ok['queue-overdue']);
        $this->assertSame('4', $ok['till-fiscal']);
        $this->assertSame('0с', $ok['gw-tinkoff-ok']);
        $this->assertSame('—', $ok['gw-tinkoff-fail']);
        $this->assertSame('1м', $ok['gw-smsru-ok']);
        $this->assertSame('1ч', $ok['gw-smsru-fail']);
        $this->assertSame('—', $ok['gw-cloudkassir-ok']);
        $this->assertSame('#42', $ok['welcome-user']);
        $this->assertSame('1', $ok['welcome-count']);
        $this->assertSame('1', $ok['auth-2fa']);
        $this->assertSame('0', $ok['auth-logins']);

        $empty = $this->simulateOpsRenderSnapshot([
            'ok' => true,
            'queue' => [
                'worker' => ['code' => 'no_data'],
                'scheduler' => ['code' => 'stale'],
                'jobs' => 0,
                'failed_jobs' => 0,
                'overdue_payouts' => 0,
            ],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => ['count' => 0, 'last_class' => null, 'top_class' => null],
            'gateways' => [],
            'auth' => ['failed_logins' => 0, 'failed_2fa' => 0],
            'welcome' => ['missing_count' => 0, 'last_user_id' => null],
        ]);
        $this->assertSame('—', $empty['queue-worker']);
        $this->assertSame('cron?', $empty['queue-scheduler']);
        $this->assertSame('—', $empty['welcome-user']);
        $this->assertSame('—', $empty['gw-tinkoff-ok']);
        $this->assertSame('—', $empty['errors-last']);
    }

    public function test_reenable_does_not_start_second_poller(): void
    {
        $result = $this->simulateDoubleOn();

        $this->assertSame(1, $result['live_intervals_after_second_on']);
    }

    public function test_xss_in_last_class_is_written_via_text_content(): void
    {
        $result = $this->simulateOpsRender([
            'ok' => true,
            'queue' => [
                'worker' => ['code' => 'alive'],
                'scheduler' => ['code' => 'alive'],
                'jobs' => 0,
                'failed_jobs' => 0,
                'overdue_payouts' => 0,
            ],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => [
                'count' => 1,
                'last_class' => '<img src=x>',
                'top_class' => 'QueryException',
            ],
            'gateways' => [],
            'auth' => ['failed_logins' => 0, 'failed_2fa' => 0],
            'welcome' => ['missing_count' => 0, 'last_user_id' => null],
        ]);

        $this->assertSame('<img src=x>', $result['errors_last']);
        $this->assertSame('textContent', $result['write_mode']);
    }

    public function test_last_message_is_written_to_hint_title_not_inner_html(): void
    {
        $result = $this->simulateOpsHintTitle([
            'ok' => true,
            'queue' => [
                'worker' => ['code' => 'alive'],
                'scheduler' => ['code' => 'alive'],
                'jobs' => 0,
                'failed_jobs' => 0,
                'overdue_payouts' => 0,
            ],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => [
                'count' => 1,
                'last_class' => 'ErrorException',
                'last_message' => '<img src=x onerror=alert(1)> boom',
                'top_class' => 'ErrorException',
            ],
            'gateways' => [],
            'auth' => ['failed_logins' => 0, 'failed_2fa' => 0],
            'welcome' => ['missing_count' => 0, 'last_user_id' => null],
        ]);

        $this->assertSame('<img src=x onerror=alert(1)> boom', $result['title_after_ok']);
        $this->assertSame('setAttribute', $result['title_write_mode']);
        $this->assertSame('', $result['inner_html']);
        $this->assertSame('Класс последней 500', $result['title_after_clear']);
    }

    public function test_refresh_writes_last_message_to_last_hint_only_and_restores_it_after_failed_poll(): void
    {
        $result = $this->simulateOpsHintRefreshCycle();

        $this->assertSame('Undefined variable $foo (View: resources/views/x.blade.php)', $result['title_after_ok']);
        $this->assertSame('Самый частый класс 500 за 24 часа', $result['top_title_after_ok']);
        $this->assertSame('Класс последней 500', $result['title_after_network']);
        $this->assertSame('—', $result['errors_last_after_network']);
        $this->assertSame('Класс последней 500', $result['title_after_forbidden']);
        $this->assertSame('Undefined variable $foo (View: resources/views/x.blade.php)', $result['title_after_ok_again']);
        $this->assertSame('', $result['inner_html']);
        $this->assertGreaterThan(0, $result['tooltip_init_calls']);
        $this->assertSame(5000, $result['interval_ms']);
    }

    public function test_empty_last_message_does_not_keep_previous_hint(): void
    {
        $result = $this->simulateOpsHintThenEmptyMessage();

        $this->assertSame('старый текст 500', $result['title_after_message']);
        $this->assertSame('Класс последней 500', $result['title_after_empty']);
    }

    public function test_auth_attempts_are_written_to_login_hint_title(): void
    {
        $result = $this->simulateOpsAuthHintTitle([
            'ok' => true,
            'queue' => [
                'worker' => ['code' => 'alive'],
                'scheduler' => ['code' => 'alive'],
                'jobs' => 0,
                'failed_jobs' => 0,
                'overdue_payouts' => 0,
            ],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => ['count' => 0, 'last_class' => null, 'top_class' => null],
            'gateways' => [],
            'auth' => [
                'failed_logins' => 1,
                'failed_2fa' => 1,
                'recent_logins' => [[
                    'email' => 'knock@example.test',
                    'password' => 'typed-secret',
                    'ip' => '203.0.113.9',
                    'user_found' => false,
                    'at' => 1700000000,
                ]],
                'recent_2fa' => [[
                    'email' => 'admin@example.test',
                    'code' => '000000',
                    'ip' => '203.0.113.10',
                    'at' => 1700000060,
                ]],
            ],
            'welcome' => ['missing_count' => 0, 'last_user_id' => null],
        ]);

        $this->assertStringContainsString('knock@example.test', $result['login_title_after_ok']);
        $this->assertStringContainsString('typed-secret', $result['login_title_after_ok']);
        $this->assertStringContainsString('203.0.113.9', $result['login_title_after_ok']);
        $this->assertStringContainsString('нет email', $result['login_title_after_ok']);
        $this->assertStringContainsString('000000', $result['twofa_title_after_ok']);
        $this->assertStringContainsString('admin@example.test', $result['twofa_title_after_ok']);
        $this->assertSame('setAttribute', $result['title_write_mode']);
        $this->assertSame('', $result['inner_html']);
        $this->assertSame('Неверный пароль или неизвестный email за 72 часа', $result['login_title_after_clear']);
    }

    public function test_found_user_login_hint_does_not_append_unknown_email_mark(): void
    {
        $result = $this->simulateOpsAuthHintTitle($this->authHintPayload([
            'email' => 'known@example.test',
            'password' => 'typed-known',
            'ip' => '203.0.113.11',
            'user_found' => true,
            'at' => 1700000000,
        ], null));

        $this->assertStringContainsString('known@example.test', $result['login_title_after_ok']);
        $this->assertStringContainsString('typed-known', $result['login_title_after_ok']);
        $this->assertStringNotContainsString('нет email', $result['login_title_after_ok']);
    }

    public function test_empty_typed_password_is_shown_as_placeholder_in_login_hint(): void
    {
        $result = $this->simulateOpsAuthHintTitle($this->authHintPayload([
            'email' => 'empty-pass@example.test',
            'password' => '',
            'ip' => '203.0.113.12',
            'user_found' => true,
            'at' => 1700000000,
        ], null));

        $this->assertStringContainsString('empty-pass@example.test', $result['login_title_after_ok']);
        $this->assertStringContainsString('∅', $result['login_title_after_ok']);
    }

    public function test_xss_in_typed_password_is_written_to_hint_title_not_inner_html(): void
    {
        $result = $this->simulateOpsAuthHintTitle($this->authHintPayload([
            'email' => 'xss@example.test',
            'password' => '<img src=x onerror=alert(1)>',
            'ip' => '203.0.113.13',
            'user_found' => false,
            'at' => 1700000000,
        ], null));

        $this->assertStringContainsString('<img src=x onerror=alert(1)>', $result['login_title_after_ok']);
        $this->assertSame('setAttribute', $result['title_write_mode']);
        $this->assertSame('', $result['inner_html']);
    }

    public function test_empty_recent_logins_do_not_keep_previous_password_in_hint(): void
    {
        $result = $this->simulateOpsAuthHintThenEmptyRecent();

        $this->assertStringContainsString('leftover-secret', $result['login_title_after_attempts']);
        $this->assertSame('Неверный пароль или неизвестный email за 72 часа', $result['login_title_after_empty']);
        $this->assertStringContainsString('111111', $result['twofa_title_after_attempts']);
        $this->assertSame('Неверный код 2FA за 72 часа', $result['twofa_title_after_empty']);
    }

    public function test_failed_poll_restores_auth_hint_and_does_not_leave_typed_password(): void
    {
        $result = $this->simulateOpsAuthHintRefreshFailure();

        $this->assertStringContainsString('poll-secret', $result['login_title_after_ok']);
        $this->assertSame('Неверный пароль или неизвестный email за 72 часа', $result['login_title_after_network']);
        $this->assertSame('Неверный пароль или неизвестный email за 72 часа', $result['login_title_after_forbidden']);
        $this->assertStringContainsString('poll-secret', $result['login_title_after_ok_again']);
        $this->assertSame('—', $result['auth_logins_after_network']);
        $this->assertSame('', $result['inner_html']);
        $this->assertSame(5000, $result['interval_ms']);
    }

    /**
     * @return array<string, string>
     */
    private function simulateOpsRenderThenFail(): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('function monitorsOn()');
const end = blade.indexOf('function refresh()');
if (start < 0 || end <= start) {
    throw new Error('ops render helpers not found');
}
const src = blade.slice(start, end);
const nodes = {};
function makeNode() {
    return {
        textContent: '…',
        classList: { remove: function () {}, add: function () {} }
    };
}
const root = {
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode();
        return nodes[m[1]];
    }
};
eval(src);
render({
    ok: true,
    queue: { worker: { code: 'alive' }, scheduler: { code: 'alive' }, jobs: 2, failed_jobs: 0, overdue_payouts: 0 },
    till: { overdue_payouts: 0, failed_intents: 0, fiscal_errors: 0 },
    errors: { count: 1, last_class: 'RuntimeException', top_class: 'RuntimeException' },
    gateways: {},
    auth: { failed_logins: 0, failed_2fa: 0 },
    welcome: { missing_count: 0, last_user_id: null }
});
const jobsAfterOk = nodes['queue-jobs'].textContent;
render({ ok: false });
process.stdout.write(JSON.stringify({
    jobs_after_ok: jobsAfterOk,
    jobs_after_fail: nodes['queue-jobs'].textContent,
    errors_after_fail: nodes['errors-count'].textContent
}));
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function simulateOpsRender(array $payload): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const payload = JSON.parse(process.argv[3]);
const start = blade.indexOf('function monitorsOn()');
const end = blade.indexOf('function refresh()');
if (start < 0 || end <= start) {
    throw new Error('ops render helpers not found');
}
const src = blade.slice(start, end);
const nodes = {};
let writeMode = 'unknown';
function makeNode() {
    return {
        _text: '…',
        set textContent(v) { writeMode = 'textContent'; this._text = v; },
        get textContent() { return this._text; },
        classList: { remove: function () {}, add: function () {} }
    };
}
const root = {
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode();
        return nodes[m[1]];
    }
};
eval(src);
render(payload);
process.stdout.write(JSON.stringify({
    errors_last: nodes['errors-last'] ? nodes['errors-last'].textContent : '',
    write_mode: writeMode
}));
JS;

        return $this->runNodeScript($script, [$bladePath, json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function simulateOpsHintTitle(array $payload): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const payload = JSON.parse(process.argv[3]);
const start = blade.indexOf('function monitorsOn()');
const end = blade.indexOf('function refresh()');
if (start < 0 || end <= start) {
    throw new Error('ops render helpers not found');
}
const src = blade.slice(start, end);
const nodes = {};
const attrs = {
    title: 'Класс последней 500',
    'aria-label': 'Класс последней 500'
};
let titleWriteMode = 'unknown';
let innerHtml = '';
const wrap = {
    parentElement: {},
    getAttribute: function (name) {
        return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : '';
    },
    setAttribute: function (name, value) {
        if (name === 'title') {
            titleWriteMode = 'setAttribute';
        }
        attrs[name] = String(value);
    },
    removeAttribute: function (name) {
        delete attrs[name];
    }
};
Object.defineProperty(wrap, 'innerHTML', {
    set: function (v) { innerHtml = String(v); },
    get: function () { return innerHtml; }
});
function makeNode(role) {
    return {
        textContent: '…',
        classList: { remove: function () {}, add: function () {} },
        closest: function () { return role === 'errors-last' ? wrap : null; }
    };
}
const root = {
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode(m[1]);
        return nodes[m[1]];
    }
};
eval(src);
render(payload);
const titleAfterOk = attrs.title;
render({ ok: false });
process.stdout.write(JSON.stringify({
    title_after_ok: titleAfterOk,
    title_write_mode: titleWriteMode,
    inner_html: innerHtml,
    title_after_clear: attrs.title || ''
}));
JS;

        return $this->runNodeScript($script, [$bladePath, json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function simulateOpsAuthHintTitle(array $payload): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const payload = JSON.parse(process.argv[3]);
const start = blade.indexOf('function monitorsOn()');
const end = blade.indexOf('function refresh()');
if (start < 0 || end <= start) {
    throw new Error('ops render helpers not found');
}
const src = blade.slice(start, end);
const nodes = {};
function makeWrap(defaultTitle) {
    const attrs = {
        title: defaultTitle,
        'aria-label': defaultTitle
    };
    let innerHtml = '';
    const wrap = {
        parentElement: {},
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : '';
        },
        setAttribute: function (name, value) {
            wrap._titleWriteMode = name === 'title' ? 'setAttribute' : wrap._titleWriteMode;
            attrs[name] = String(value);
        },
        removeAttribute: function (name) {
            delete attrs[name];
        },
        _titleWriteMode: 'unknown',
        _attrs: attrs
    };
    Object.defineProperty(wrap, 'innerHTML', {
        set: function (v) { innerHtml = String(v); wrap._innerHtml = innerHtml; },
        get: function () { return innerHtml; }
    });
    wrap._innerHtml = '';
    return wrap;
}
const loginWrap = makeWrap('Неверный пароль или неизвестный email за 72 часа');
const twofaWrap = makeWrap('Неверный код 2FA за 72 часа');
function makeNode(role) {
    return {
        textContent: '…',
        classList: { remove: function () {}, add: function () {} },
        closest: function () {
            if (role === 'auth-logins') return loginWrap;
            if (role === 'auth-2fa') return twofaWrap;
            return null;
        }
    };
}
const root = {
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode(m[1]);
        return nodes[m[1]];
    }
};
eval(src);
render(payload);
const loginTitleAfterOk = loginWrap._attrs.title;
const twofaTitleAfterOk = twofaWrap._attrs.title;
const titleWriteMode = loginWrap._titleWriteMode;
const innerHtml = loginWrap._innerHtml;
render({ ok: false });
process.stdout.write(JSON.stringify({
    login_title_after_ok: loginTitleAfterOk,
    twofa_title_after_ok: twofaTitleAfterOk,
    title_write_mode: titleWriteMode,
    inner_html: innerHtml,
    login_title_after_clear: loginWrap._attrs.title || ''
}));
JS;

        return $this->runNodeScript($script, [$bladePath, json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @param  array<string, mixed>|null  $login
     * @param  array<string, mixed>|null  $twofa
     * @return array<string, mixed>
     */
    private function authHintPayload(?array $login, ?array $twofa): array
    {
        return [
            'ok' => true,
            'queue' => [
                'worker' => ['code' => 'alive'],
                'scheduler' => ['code' => 'alive'],
                'jobs' => 0,
                'failed_jobs' => 0,
                'overdue_payouts' => 0,
            ],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => ['count' => 0, 'last_class' => null, 'top_class' => null],
            'gateways' => [],
            'auth' => [
                'failed_logins' => $login === null ? 0 : 1,
                'failed_2fa' => $twofa === null ? 0 : 1,
                'recent_logins' => $login === null ? [] : [$login],
                'recent_2fa' => $twofa === null ? [] : [$twofa],
            ],
            'welcome' => ['missing_count' => 0, 'last_user_id' => null],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateOpsAuthHintThenEmptyRecent(): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('function monitorsOn()');
const end = blade.indexOf('function refresh()');
if (start < 0 || end <= start) {
    throw new Error('ops render helpers not found');
}
const src = blade.slice(start, end);
const nodes = {};
function makeWrap(defaultTitle) {
    const attrs = { title: defaultTitle, 'aria-label': defaultTitle };
    const wrap = {
        parentElement: {},
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : '';
        },
        setAttribute: function (name, value) { attrs[name] = String(value); },
        removeAttribute: function (name) { delete attrs[name]; },
        _attrs: attrs
    };
    return wrap;
}
const loginWrap = makeWrap('Неверный пароль или неизвестный email за 72 часа');
const twofaWrap = makeWrap('Неверный код 2FA за 72 часа');
function makeNode(role) {
    return {
        textContent: '…',
        classList: { remove: function () {}, add: function () {} },
        closest: function () {
            if (role === 'auth-logins') return loginWrap;
            if (role === 'auth-2fa') return twofaWrap;
            return null;
        }
    };
}
const root = {
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode(m[1]);
        return nodes[m[1]];
    }
};
eval(src);
const base = {
    ok: true,
    queue: { worker: { code: 'alive' }, scheduler: { code: 'alive' }, jobs: 0, failed_jobs: 0, overdue_payouts: 0 },
    till: { overdue_payouts: 0, failed_intents: 0, fiscal_errors: 0 },
    errors: { count: 0, last_class: null, top_class: null },
    gateways: {},
    welcome: { missing_count: 0, last_user_id: null }
};
base.auth = {
    failed_logins: 1,
    failed_2fa: 1,
    recent_logins: [{ email: 'left@example.test', password: 'leftover-secret', ip: '1.1.1.1', user_found: false, at: 1 }],
    recent_2fa: [{ email: 'a@example.test', code: '111111', ip: '1.1.1.1', at: 1 }]
};
render(base);
const loginAfter = loginWrap._attrs.title;
const twofaAfter = twofaWrap._attrs.title;
base.auth = { failed_logins: 0, failed_2fa: 0, recent_logins: [], recent_2fa: [] };
render(base);
process.stdout.write(JSON.stringify({
    login_title_after_attempts: loginAfter,
    twofa_title_after_attempts: twofaAfter,
    login_title_after_empty: loginWrap._attrs.title || '',
    twofa_title_after_empty: twofaWrap._attrs.title || ''
}));
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateOpsAuthHintRefreshFailure(): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('(function () {');
const end = blade.lastIndexOf('})();');
if (start < 0 || end < 0) {
    throw new Error('ops monitors IIFE not found');
}
const src = blade.slice(start, end + 5);

const bodyClasses = new Set(['system-monitors-on']);
const intervals = [];
const listeners = {};
const nodes = {};
let innerHtml = '';
function makeWrap(defaultTitle) {
    const attrs = { title: defaultTitle, 'aria-label': defaultTitle };
    const wrap = {
        parentElement: {},
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : '';
        },
        setAttribute: function (name, value) { attrs[name] = String(value); },
        removeAttribute: function (name) { delete attrs[name]; },
        _attrs: attrs
    };
    Object.defineProperty(wrap, 'innerHTML', {
        set: function (v) { innerHtml = String(v); },
        get: function () { return innerHtml; }
    });
    return wrap;
}
const loginWrap = makeWrap('Неверный пароль или неизвестный email за 72 часа');
function makeNode(role) {
    return {
        textContent: '…',
        classList: { remove: function () {}, add: function () {} },
        closest: function () { return role === 'auth-logins' ? loginWrap : null; }
    };
}
const root = {
    getAttribute: function () { return '/cabinet/system-monitors/ops'; },
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode(m[1]);
        return nodes[m[1]];
    }
};
global.document = {
    getElementById: function (id) { return id === 'js-ops-monitors' ? root : null; },
    body: { classList: { contains: function (name) { return bodyClasses.has(name); } } },
    addEventListener: function (type, fn) { listeners[type] = fn; }
};
global.window = global;
global.KidsCrmTooltip = { dispose: function () {}, init: function () {} };
global.setInterval = function (fn, ms) {
    const id = intervals.length + 1;
    intervals.push({ id: id, cleared: false, fn: fn, ms: ms });
    return id;
};
global.clearInterval = function (id) {
    intervals.forEach(function (row) { if (row.id === id) row.cleared = true; });
};
const okPayload = {
    ok: true,
    queue: { worker: { code: 'alive' }, scheduler: { code: 'alive' }, jobs: 0, failed_jobs: 0, overdue_payouts: 0 },
    till: { overdue_payouts: 0, failed_intents: 0, fiscal_errors: 0 },
    errors: { count: 0, last_class: null, top_class: null },
    gateways: {},
    auth: {
        failed_logins: 1,
        failed_2fa: 0,
        recent_logins: [{ email: 'poll@example.test', password: 'poll-secret', ip: '1.1.1.1', user_found: false, at: 1 }],
        recent_2fa: []
    },
    welcome: { missing_count: 0, last_user_id: null }
};
let fetchMode = 'ok';
global.fetch = function () {
    if (fetchMode === 'ok') {
        return Promise.resolve({ ok: true, json: function () { return Promise.resolve(okPayload); } });
    }
    if (fetchMode === 'forbidden') {
        return Promise.resolve({ ok: false, status: 403, json: function () { return Promise.resolve({ message: 'Forbidden' }); } });
    }
    return Promise.reject(new Error('network down'));
};
(async function () {
    eval(src);
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const titleAfterOk = loginWrap._attrs.title;
    fetchMode = 'network';
    const tick = intervals.find(function (row) { return !row.cleared; });
    if (!tick || typeof tick.fn !== 'function') {
        throw new Error('ops poller not started');
    }
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const titleAfterNetwork = loginWrap._attrs.title;
    const authAfterNetwork = nodes['auth-logins'] ? nodes['auth-logins'].textContent : '';
    fetchMode = 'forbidden';
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const titleAfterForbidden = loginWrap._attrs.title;
    fetchMode = 'ok';
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    process.stdout.write(JSON.stringify({
        login_title_after_ok: titleAfterOk,
        login_title_after_network: titleAfterNetwork,
        login_title_after_forbidden: titleAfterForbidden,
        login_title_after_ok_again: loginWrap._attrs.title,
        auth_logins_after_network: authAfterNetwork,
        inner_html: innerHtml,
        interval_ms: tick.ms
    }));
})().catch(function (err) {
    process.stderr.write(String(err && err.stack ? err.stack : err));
    process.exit(1);
});
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * Реальный refresh(): ok с last_message → сеть → 403 → снова ok.
     * Ховер leftover и чужой errors-top не должны получать текст 500.
     *
     * @return array<string, mixed>
     */
    private function simulateOpsHintRefreshCycle(): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('(function () {');
const end = blade.lastIndexOf('})();');
if (start < 0 || end < 0) {
    throw new Error('ops monitors IIFE not found');
}
const src = blade.slice(start, end + 5);

const bodyClasses = new Set(['system-monitors-on']);
const intervals = [];
const listeners = {};
const nodes = {};
let innerHtml = '';
let tooltipInitCalls = 0;

function makeWrap(defaultTitle) {
    const attrs = { title: defaultTitle, 'aria-label': defaultTitle };
    const wrap = {
        parentElement: {},
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : '';
        },
        setAttribute: function (name, value) {
            attrs[name] = String(value);
        },
        removeAttribute: function (name) {
            delete attrs[name];
        }
    };
    Object.defineProperty(wrap, 'innerHTML', {
        set: function (v) { innerHtml = String(v); },
        get: function () { return innerHtml; }
    });
    wrap._attrs = attrs;
    return wrap;
}

const lastWrap = makeWrap('Класс последней 500');
const topWrap = makeWrap('Самый частый класс 500 за 24 часа');

function makeNode(role) {
    return {
        textContent: '…',
        classList: { remove: function () {}, add: function () {} },
        closest: function () {
            if (role === 'errors-last') return lastWrap;
            if (role === 'errors-top') return topWrap;
            return null;
        }
    };
}

const root = {
    getAttribute: function () { return '/cabinet/system-monitors/ops'; },
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode(m[1]);
        return nodes[m[1]];
    }
};
global.document = {
    getElementById: function (id) { return id === 'js-ops-monitors' ? root : null; },
    body: { classList: { contains: function (name) { return bodyClasses.has(name); } } },
    addEventListener: function (type, fn) { listeners[type] = fn; }
};
global.window = global;
global.KidsCrmTooltip = {
    dispose: function () {},
    init: function () { tooltipInitCalls += 1; }
};
global.setInterval = function (fn, ms) {
    const id = intervals.length + 1;
    intervals.push({ id: id, cleared: false, fn: fn, ms: ms });
    return id;
};
global.clearInterval = function (id) {
    intervals.forEach(function (row) { if (row.id === id) row.cleared = true; });
};

const okPayload = {
    ok: true,
    queue: { worker: { code: 'alive' }, scheduler: { code: 'alive' }, jobs: 2, failed_jobs: 0, overdue_payouts: 0 },
    till: { overdue_payouts: 0, failed_intents: 0, fiscal_errors: 0 },
    errors: {
        count: 1,
        last_class: 'ErrorException',
        last_message: 'Undefined variable $foo (View: resources/views/x.blade.php)',
        top_class: 'ErrorException',
        recent: [{ class: 'ErrorException', message: 'Undefined variable $foo', route: 'dashboard', path: '/cabinet', at: 1 }]
    },
    gateways: {},
    auth: { failed_logins: 0, failed_2fa: 0 },
    welcome: { missing_count: 0, last_user_id: null }
};
let fetchMode = 'ok';
global.fetch = function () {
    if (fetchMode === 'ok') {
        return Promise.resolve({
            ok: true,
            json: function () { return Promise.resolve(okPayload); }
        });
    }
    if (fetchMode === 'forbidden') {
        return Promise.resolve({
            ok: false,
            status: 403,
            json: function () { return Promise.resolve({ message: 'Forbidden' }); }
        });
    }
    return Promise.reject(new Error('network down'));
};

(async function () {
    eval(src);
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const titleAfterOk = lastWrap._attrs.title;
    const topTitleAfterOk = topWrap._attrs.title;
    fetchMode = 'network';
    const tick = intervals.find(function (row) { return !row.cleared; });
    if (!tick || typeof tick.fn !== 'function') {
        throw new Error('ops poller not started');
    }
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const titleAfterNetwork = lastWrap._attrs.title;
    const errorsLastAfterNetwork = nodes['errors-last'] ? nodes['errors-last'].textContent : '';
    fetchMode = 'forbidden';
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const titleAfterForbidden = lastWrap._attrs.title;
    fetchMode = 'ok';
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    process.stdout.write(JSON.stringify({
        title_after_ok: titleAfterOk,
        top_title_after_ok: topTitleAfterOk,
        title_after_network: titleAfterNetwork,
        errors_last_after_network: errorsLastAfterNetwork,
        title_after_forbidden: titleAfterForbidden,
        title_after_ok_again: lastWrap._attrs.title,
        inner_html: innerHtml,
        tooltip_init_calls: tooltipInitCalls,
        interval_ms: tick.ms
    }));
})().catch(function (err) {
    process.stderr.write(String(err && err.stack ? err.stack : err));
    process.exit(1);
});
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @return array<string, string>
     */
    private function simulateOpsHintThenEmptyMessage(): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('function monitorsOn()');
const end = blade.indexOf('function refresh()');
if (start < 0 || end <= start) {
    throw new Error('ops render helpers not found');
}
const src = blade.slice(start, end);
const nodes = {};
const attrs = { title: 'Класс последней 500' };
const wrap = {
    parentElement: {},
    getAttribute: function (name) {
        return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : '';
    },
    setAttribute: function (name, value) { attrs[name] = String(value); },
    removeAttribute: function (name) { delete attrs[name]; }
};
function makeNode(role) {
    return {
        textContent: '…',
        classList: { remove: function () {}, add: function () {} },
        closest: function () { return role === 'errors-last' ? wrap : null; }
    };
}
const root = {
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode(m[1]);
        return nodes[m[1]];
    }
};
eval(src);
const base = {
    ok: true,
    queue: { worker: { code: 'alive' }, scheduler: { code: 'alive' }, jobs: 0, failed_jobs: 0, overdue_payouts: 0 },
    till: { overdue_payouts: 0, failed_intents: 0, fiscal_errors: 0 },
    gateways: {},
    auth: { failed_logins: 0, failed_2fa: 0 },
    welcome: { missing_count: 0, last_user_id: null }
};
base.errors = { count: 1, last_class: 'ErrorException', last_message: 'старый текст 500', top_class: 'ErrorException' };
render(base);
const titleAfterMessage = attrs.title;
base.errors = { count: 1, last_class: 'ErrorException', last_message: '', top_class: 'ErrorException' };
render(base);
process.stdout.write(JSON.stringify({
    title_after_message: titleAfterMessage,
    title_after_empty: attrs.title || ''
}));
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @return array<string, int>
     */
    private function simulateDoubleOn(): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('(function () {');
const end = blade.lastIndexOf('})();');
const src = blade.slice(start, end + 5);
const bodyClasses = new Set(['system-monitors-on']);
const intervals = [];
const listeners = {};
const nodes = {};
function makeNode() {
    return { textContent: '…', classList: { remove: function () {}, add: function () {} } };
}
const root = {
    getAttribute: function () { return '/cabinet/system-monitors/ops'; },
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode();
        return nodes[m[1]];
    }
};
global.document = {
    getElementById: function (id) { return id === 'js-ops-monitors' ? root : null; },
    body: { classList: { contains: function (name) { return bodyClasses.has(name); } } },
    addEventListener: function (type, fn) { listeners[type] = fn; }
};
global.window = global;
global.setInterval = function () {
    const id = intervals.length + 1;
    intervals.push({ id: id, cleared: false });
    return id;
};
global.clearInterval = function (id) {
    intervals.forEach(function (row) { if (row.id === id) row.cleared = true; });
};
global.fetch = function () {
    return Promise.resolve({ ok: true, json: function () { return Promise.resolve({ ok: true }); } });
};
eval(src);
listeners['system-monitors:change']({ detail: { on: true } });
process.stdout.write(JSON.stringify({
    live_intervals_after_second_on: intervals.filter(function (row) { return !row.cleared; }).length
}));
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @return list<string>
     */
    private function opsDataRoles(): array
    {
        return [
            'queue-worker',
            'queue-scheduler',
            'queue-jobs',
            'queue-failed',
            'queue-overdue',
            'till-overdue',
            'till-intents',
            'till-fiscal',
            'errors-count',
            'errors-last',
            'errors-top',
            'gw-tinkoff-ok',
            'gw-tinkoff-fail',
            'gw-smsru-ok',
            'gw-smsru-fail',
            'gw-cloudkassir-ok',
            'gw-cloudkassir-fail',
            'auth-logins',
            'auth-2fa',
            'welcome-count',
            'welcome-user',
        ];
    }

    /**
     * Реальный путь refresh(): ok → network throw → HTTP 403.
     * Не вызывать render({ok:false}) напрямую — регрессия как у онлайна.
     *
     * @return array<string, mixed>
     */
    private function simulateOpsRefreshFailure(): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('(function () {');
const end = blade.lastIndexOf('})();');
if (start < 0 || end < 0) {
    throw new Error('ops monitors IIFE not found');
}
const src = blade.slice(start, end + 5);

const bodyClasses = new Set(['system-monitors-on']);
const intervals = [];
const listeners = {};
const nodes = {};
function makeNode() {
    return { textContent: '…', classList: { remove: function () {}, add: function () {} } };
}
const root = {
    getAttribute: function () { return '/cabinet/system-monitors/ops'; },
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode();
        return nodes[m[1]];
    }
};
global.document = {
    getElementById: function (id) { return id === 'js-ops-monitors' ? root : null; },
    body: { classList: { contains: function (name) { return bodyClasses.has(name); } } },
    addEventListener: function (type, fn) { listeners[type] = fn; }
};
global.window = global;
global.setInterval = function (fn, ms) {
    const id = intervals.length + 1;
    intervals.push({ id: id, cleared: false, fn: fn, ms: ms });
    return id;
};
global.clearInterval = function (id) {
    intervals.forEach(function (row) { if (row.id === id) row.cleared = true; });
};

const okPayload = {
    ok: true,
    queue: { worker: { code: 'alive' }, scheduler: { code: 'alive' }, jobs: 2, failed_jobs: 0, overdue_payouts: 0 },
    till: { overdue_payouts: 0, failed_intents: 0, fiscal_errors: 0 },
    errors: { count: 1, last_class: 'RuntimeException', top_class: 'RuntimeException' },
    gateways: {},
    auth: { failed_logins: 0, failed_2fa: 0 },
    welcome: { missing_count: 0, last_user_id: null }
};
let fetchMode = 'ok';
global.fetch = function () {
    if (fetchMode === 'ok') {
        return Promise.resolve({
            ok: true,
            json: function () { return Promise.resolve(okPayload); }
        });
    }
    if (fetchMode === 'forbidden') {
        return Promise.resolve({
            ok: false,
            status: 403,
            json: function () { return Promise.resolve({ message: 'Forbidden' }); }
        });
    }
    return Promise.reject(new Error('network down'));
};

(async function () {
    eval(src);
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const jobsAfterOk = nodes['queue-jobs'].textContent;
    const errorsAfterOk = nodes['errors-count'].textContent;
    fetchMode = 'network';
    const tick = intervals.find(function (row) { return !row.cleared; });
    if (!tick || typeof tick.fn !== 'function') {
        throw new Error('ops poller not started');
    }
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const jobsAfterNetwork = nodes['queue-jobs'].textContent;
    const errorsAfterNetwork = nodes['errors-count'].textContent;
    nodes['queue-jobs'].textContent = '2';
    nodes['welcome-user'].textContent = '#9';
    fetchMode = 'forbidden';
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    process.stdout.write(JSON.stringify({
        jobs_after_ok: jobsAfterOk,
        errors_after_ok: errorsAfterOk,
        jobs_after_network: jobsAfterNetwork,
        errors_after_network: errorsAfterNetwork,
        jobs_after_forbidden: nodes['queue-jobs'].textContent,
        welcome_after_forbidden: nodes['welcome-user'].textContent,
        interval_ms: tick.ms
    }));
})().catch(function (err) {
    process.stderr.write(String(err && err.stack ? err.stack : err));
    process.exit(1);
});
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function simulateOpsRenderSnapshot(array $payload): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const payload = JSON.parse(process.argv[3]);
const start = blade.indexOf('function monitorsOn()');
const end = blade.indexOf('function refresh()');
if (start < 0 || end <= start) {
    throw new Error('ops render helpers not found');
}
const src = blade.slice(start, end);
const nodes = {};
function makeNode() {
    return {
        textContent: '…',
        classList: { remove: function () {}, add: function () {} }
    };
}
const root = {
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode();
        return nodes[m[1]];
    }
};
eval(src);
render(payload);
const texts = {};
Object.keys(nodes).forEach(function (key) { texts[key] = nodes[key].textContent; });
process.stdout.write(JSON.stringify(texts));
JS;

        return $this->runNodeScript($script, [$bladePath, json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @param  list<string>  $args
     * @return array<string, mixed>
     */
    private function runNodeScript(string $script, array $args): array
    {
        $path = sys_get_temp_dir().'/ops-monitors-ux-'.uniqid('', true).'.cjs';
        file_put_contents($path, $script);

        try {
            $cmd = 'node '.escapeshellarg($path);
            foreach ($args as $arg) {
                $cmd .= ' '.escapeshellarg($arg);
            }
            $output = [];
            $exitCode = 0;
            exec($cmd.' 2>&1', $output, $exitCode);
            $raw = implode("\n", $output);
            $this->assertSame(0, $exitCode, $raw);
            $decoded = json_decode($raw, true);
            $this->assertIsArray($decoded, $raw);

            return $decoded;
        } finally {
            @unlink($path);
        }
    }
}
