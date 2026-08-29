<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use Illuminate\Support\Facades\Auth;

/**
 * P1: UX оверлея «Онлайн» — первый кадр без списка, XSS, откат при ошибке fetch,
 * опрос только пока тоггл включён, стек над Reverb на всех страницах CRM.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOnlineUsersUxFeatureTest extends SystemMonitorsTestCase
{
    public function test_first_open_does_not_prefill_the_list_until_fetch(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Иванов',
            'name' => 'Иван',
            'last_seen_at' => now(),
        ]);

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertTrue(
            (bool) preg_match(
                '/<div class="online-users__list" data-role="list">(.*?)<\/div>/s',
                $html,
                $listMatch
            ),
            'первый HTML не должен содержать готовый список — его рисует JS после fetch'
        );
        $this->assertSame(
            '',
            trim($listMatch[1]),
            'сервер не должен заранее вставить людей в список'
        );
        $this->assertStringNotContainsString('Иванов', $listMatch[1]);
        $this->assertMatchesRegularExpression(
            '/data-role="total">…<\/span>/u',
            $html
        );
        $this->assertStringContainsString('aria-label="Копировать список"', $html);
        $this->assertStringContainsString('title="Копировать список"', $html);
    }

    public function test_monitors_off_does_not_server_render_online_names(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Скрытый',
            'name' => 'Онлайн',
            'last_seen_at' => now(),
        ]);

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertTrue(
            (bool) preg_match(
                '/<div class="online-users__list" data-role="list">(.*?)<\/div>/s',
                $html,
                $listMatch
            )
        );
        $this->assertSame('', trim($listMatch[1]));
        $this->assertStringNotContainsString('Скрытый Онлайн', $listMatch[1]);
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
        $this->assertStringContainsString('body:not(.system-monitors-on) .system-monitor', $html);
    }

    public function test_overlay_is_on_cabinet_chat_and_settings_above_reverb(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        foreach ([route('dashboard'), route('chat.index'), route('admin.setting.setting')] as $url) {
            $html = $this->actingAs($this->user)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('id="system-monitors-stack"', $html, $url);
            $this->assertStringContainsString('id="js-online-users"', $html, $url);
            $this->assertStringContainsString('id="js-reverb-status"', $html, $url);
            $this->assertStringContainsString('position: fixed', $html, $url);
            $this->assertStringContainsString('z-index: 20000', $html, $url);
            $this->assertLessThan(
                (int) strpos($html, 'id="js-reverb-status"'),
                (int) strpos($html, 'id="js-online-users"'),
                $url
            );
        }
    }

    public function test_login_and_landing_do_not_render_online_overlay(): void
    {
        Auth::logout();

        foreach ([route('login'), route('landing.home')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('id="js-online-users"', $html, $url);
            $this->assertStringNotContainsString('cabinet.system-monitors.online-users', $html, $url);
            $this->assertStringNotContainsString('id="system-monitors-stack"', $html, $url);
        }
    }

    public function test_admin_without_permission_does_not_see_overlay_even_if_flag_is_on(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true, 'last_seen_at' => now()])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="js-online-users"', $html);
        $this->assertStringNotContainsString('id="system-monitors-stack"', $html);
    }

    public function test_malicious_names_are_escaped_in_the_overlay_html(): void
    {
        $result = $this->simulateOnlineRender([
            'ok' => true,
            'total' => 1,
            'partners' => [
                [
                    'id' => 1,
                    'title' => '<img src=x onerror=alert(1)>',
                    'count' => 1,
                    'users' => [
                        ['id' => 9, 'name' => '<script>alert(1)</script>'],
                    ],
                ],
            ],
        ]);

        $this->assertStringNotContainsString('<script>', $result['html']);
        $this->assertStringNotContainsString('<img src=x', $result['html']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $result['html']);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $result['html']);
    }

    public function test_failed_refresh_clears_stale_people_from_the_overlay(): void
    {
        $result = $this->simulateOnlineRefreshFailure();

        $this->assertStringContainsString('Иванов Иван', $result['html_after_ok']);
        $this->assertStringNotContainsString('Иванов Иван', $result['html_after_fail']);
        $this->assertStringContainsString('Никого нет онлайн', $result['html_after_fail']);
        $this->assertSame('0', $result['total_after_fail']);
        $this->assertStringNotContainsString('Иванов Иван', $result['html_after_forbidden']);
        $this->assertStringContainsString('Никого нет онлайн', $result['html_after_forbidden']);
    }

    public function test_client_uses_missing_partner_fallback_when_title_absent(): void
    {
        $result = $this->simulateOnlineRender([
            'ok' => true,
            'total' => 1,
            'partners' => [
                [
                    'id' => null,
                    'title' => '',
                    'count' => 1,
                    'users' => [
                        ['id' => 3, 'name' => ''],
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('Без партнёра (1)', $result['html']);
        $this->assertStringNotContainsString('undefined', $result['html']);
        $this->assertStringContainsString('Без партнёра (1)', $result['copy']);
    }

    public function test_turning_monitors_on_twice_does_not_start_a_second_poller(): void
    {
        $result = $this->simulateOnlineWatching();

        $this->assertSame(0, $result['fetch_on_init_off']);
        $this->assertSame(1, $result['intervals_after_on']);
        $this->assertSame(1, $result['intervals_after_second_on']);
        $this->assertSame(0, $result['live_intervals_after_off']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function simulateOnlineRender(array $payload): array
    {
        $bladePath = resource_path('views/includes/system_monitors/online_users.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const payload = JSON.parse(process.argv[3]);
const start = blade.indexOf('function escapeHtml(value)');
const end = blade.indexOf('function fallbackCopy(text)');
if (start < 0 || end <= start) {
    throw new Error('online render helpers not found');
}
const src = blade.slice(start, end);
const totalEl = { textContent: '…' };
const listEl = { innerHTML: '' };
let lastSnapshot = { total: 0, partners: [] };
eval(src);
render(payload);
process.stdout.write(JSON.stringify({
    total: totalEl.textContent,
    html: listEl.innerHTML,
    copy: statusText()
}));
JS;

        return $this->runNodeScript($script, [$bladePath, json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @return array<string, string>
     */
    private function simulateOnlineRefreshFailure(): array
    {
        $bladePath = resource_path('views/includes/system_monitors/online_users.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('(function () {');
const end = blade.lastIndexOf('})();');
if (start < 0 || end < 0) {
    throw new Error('online users IIFE not found');
}
const src = blade.slice(start, end + 5);

const bodyClasses = new Set(['system-monitors-on']);
const intervals = [];
const listeners = {};
const totalEl = { textContent: '…' };
const listEl = { innerHTML: '' };
const root = {
    getAttribute: function () { return '/cabinet/system-monitors/online-users'; },
    querySelector: function (sel) {
        if (sel === '[data-role="total"]') return totalEl;
        if (sel === '[data-role="list"]') return listEl;
        if (sel === '[data-role="copy"]') {
            return { classList: { add: function () {}, remove: function () {} }, setAttribute: function () {}, addEventListener: function () {} };
        }
        return null;
    }
};

global.document = {
    getElementById: function (id) { return id === 'js-online-users' ? root : null; },
    body: { classList: { contains: function (name) { return bodyClasses.has(name); } } },
    addEventListener: function (type, fn) { listeners[type] = fn; },
    createElement: function () { return { value: '', setAttribute: function () {}, style: {}, select: function () {} }; }
};
global.window = global;
global.navigator = {};
global.setInterval = function (fn) {
    const id = intervals.length + 1;
    intervals.push({ id: id, cleared: false, fn: fn });
    return id;
};
global.clearInterval = function (id) {
    intervals.forEach(function (row) { if (row.id === id) row.cleared = true; });
};

const okPayload = {
    ok: true,
    total: 1,
    partners: [{
        id: 1,
        title: 'Партнер 1',
        count: 1,
        users: [{ id: 1, name: 'Иванов Иван' }]
    }]
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
        return Promise.resolve({ ok: false, json: function () { return Promise.resolve({ message: 'Forbidden' }); } });
    }
    return Promise.reject(new Error('network'));
};

(async function () {
    eval(src);
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const htmlAfterOk = listEl.innerHTML;
    const tick = intervals.find(function (row) { return !row.cleared; });
    if (!tick || typeof tick.fn !== 'function') {
        throw new Error('poller was not started');
    }
    fetchMode = 'fail';
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const htmlAfterFail = listEl.innerHTML;
    const totalAfterFail = totalEl.textContent;
    fetchMode = 'ok';
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    fetchMode = 'forbidden';
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    process.stdout.write(JSON.stringify({
        html_after_ok: htmlAfterOk,
        html_after_fail: htmlAfterFail,
        total_after_fail: totalAfterFail,
        html_after_forbidden: listEl.innerHTML
    }));
})().catch(function (err) {
    process.stderr.write(String(err && err.stack ? err.stack : err));
    process.exit(1);
});
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @return array<string, int>
     */
    private function simulateOnlineWatching(): array
    {
        $bladePath = resource_path('views/includes/system_monitors/online_users.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('(function () {');
const end = blade.lastIndexOf('})();');
if (start < 0 || end < 0) {
    throw new Error('online users IIFE not found');
}
const src = blade.slice(start, end + 5);

const bodyClasses = new Set();
const intervals = [];
let fetchCount = 0;
const listeners = {};
const totalEl = { textContent: '…' };
const listEl = { innerHTML: '' };
const root = {
    getAttribute: function () { return '/cabinet/system-monitors/online-users'; },
    querySelector: function (sel) {
        if (sel === '[data-role="total"]') return totalEl;
        if (sel === '[data-role="list"]') return listEl;
        if (sel === '[data-role="copy"]') {
            return { classList: { add: function () {}, remove: function () {} }, setAttribute: function () {}, addEventListener: function () {} };
        }
        return null;
    }
};

global.document = {
    getElementById: function (id) { return id === 'js-online-users' ? root : null; },
    body: { classList: { contains: function (name) { return bodyClasses.has(name); } } },
    addEventListener: function (type, fn) { listeners[type] = fn; },
    createElement: function () { return { value: '', setAttribute: function () {}, style: {}, select: function () {} }; }
};
global.window = global;
global.navigator = {};
global.setInterval = function () {
    const id = intervals.length + 1;
    intervals.push({ id: id, cleared: false });
    return id;
};
global.clearInterval = function (id) {
    intervals.forEach(function (row) { if (row.id === id) row.cleared = true; });
};
global.fetch = function () {
    fetchCount += 1;
    return Promise.resolve({
        ok: true,
        json: function () { return Promise.resolve({ ok: true, total: 0, partners: [] }); }
    });
};

eval(src);
const fetchOnInitOff = fetchCount;

bodyClasses.add('system-monitors-on');
listeners['system-monitors:change']({ detail: { on: true } });
const intervalsAfterOn = intervals.filter(function (row) { return !row.cleared; }).length;
listeners['system-monitors:change']({ detail: { on: true } });
const intervalsAfterSecondOn = intervals.filter(function (row) { return !row.cleared; }).length;
listeners['system-monitors:change']({ detail: { on: false } });

process.stdout.write(JSON.stringify({
    fetch_on_init_off: fetchOnInitOff,
    intervals_after_on: intervalsAfterOn,
    intervals_after_second_on: intervalsAfterSecondOn,
    live_intervals_after_off: intervals.filter(function (row) { return !row.cleared; }).length
}));
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @param  list<string>  $args
     * @return array<string, mixed>
     */
    private function runNodeScript(string $script, array $args): array
    {
        $path = sys_get_temp_dir().'/online-users-ux-'.uniqid('', true).'.cjs';
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
