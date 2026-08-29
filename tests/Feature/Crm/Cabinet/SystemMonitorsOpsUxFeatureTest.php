<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use Illuminate\Support\Facades\Auth;

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
        $this->assertStringContainsString('>Welcome</span>', $html);
        $this->assertStringContainsString('Воркер очереди', $html);
        $this->assertStringContainsString('Планировщик cron', $html);
        $this->assertStringContainsString('все школы', $html);
        $this->assertStringNotContainsString('href="/admin/settings/queues"', $html);
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
