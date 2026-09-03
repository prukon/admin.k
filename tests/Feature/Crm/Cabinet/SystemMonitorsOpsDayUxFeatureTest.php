<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Payment;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * UX строк «Сегодня» / «Вчера»: первый кадр «…», 0 не «—», leftover после 403/сети,
 * без пробелов тысяч, порядок сразу под «Пульт».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsDayUxFeatureTest extends SystemMonitorsTestCase
{
    public function test_first_open_today_and_yesterday_are_ellipsis_not_database_numbers(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);
        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);
        $student = $this->createUserWithRole('user', $this->partner);
        $this->seedTbankPayment($student, 15_000_000, $now, 'deal-html-today');

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/data-role="day-turnover">…<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/data-role="day-commission">…<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/data-role="day-count">…<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/data-role="yesterday-turnover">…<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/data-role="yesterday-commission">…<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/data-role="yesterday-count">…<\/span>/u', $html);
        $this->assertStringNotContainsString('data-role="day-turnover">150000', $html);
        $this->assertStringNotContainsString('data-role="day-commission">600', $html);
        $this->assertStringNotContainsString('data-role="day-count">1', $html);
        $this->assertStringContainsString('>Сегодня</span>', $html);
        $this->assertStringContainsString('>Вчера</span>', $html);
        $this->assertStringContainsString('Оборотка T‑Bank за текущий календарный день', $html);
        $this->assertStringContainsString('Оборотка T‑Bank за вчерашний календарный день', $html);

        $titlePos = strpos($html, '>Пульт</div>');
        $todayPos = strpos($html, '>Сегодня</span>');
        $yesterdayPos = strpos($html, '>Вчера</span>');
        $queuePos = strpos($html, '>Очередь</span>');
        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($todayPos);
        $this->assertNotFalse($yesterdayPos);
        $this->assertNotFalse($queuePos);
        $this->assertLessThan($todayPos, $titlePos);
        $this->assertLessThan($yesterdayPos, $todayPos);
        $this->assertLessThan($queuePos, $yesterdayPos);

        $todayBlock = substr($html, $todayPos, $yesterdayPos - $todayPos);
        $this->assertStringContainsString('ops-monitors__slash', $todayBlock);
        $this->assertStringNotContainsString('href=', $todayBlock);
        $yesterdayBlock = substr($html, $yesterdayPos, $queuePos - $yesterdayPos);
        $this->assertStringContainsString('ops-monitors__slash', $yesterdayBlock);
        $this->assertStringNotContainsString('href=', $yesterdayBlock);
    }

    public function test_login_page_does_not_render_today_or_yesterday_row(): void
    {
        Auth::logout();

        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-ops-monitors"', $html);
        $this->assertStringNotContainsString('data-role="day-turnover"', $html);
        $this->assertStringNotContainsString('data-role="yesterday-turnover"', $html);
        $this->assertStringNotContainsString('>Сегодня</span>', $html);
        $this->assertStringNotContainsString('>Вчера</span>', $html);
    }

    public function test_overlay_on_chat_and_settings_still_starts_with_ellipsis(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $this->seedTbankPayment(
            $this->createUserWithRole('user', $this->partner),
            15_000_000,
            Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow'),
            'deal-html-other-pages'
        );

        foreach ([route('chat.index'), route('admin.setting.setting')] as $url) {
            $html = $this->actingAs($this->user)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression(
                '/data-role="day-turnover">…<\/span>/u',
                $html,
                $url
            );
            $this->assertMatchesRegularExpression(
                '/data-role="yesterday-count">…<\/span>/u',
                $html,
                $url
            );
            $this->assertStringNotContainsString('data-role="day-turnover">150000', $html, $url);
        }
    }

    public function test_admin_without_permission_does_not_see_today_or_yesterday_row(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="js-ops-monitors"', $html);
        $this->assertStringNotContainsString('data-role="day-turnover"', $html);
        $this->assertStringNotContainsString('data-role="yesterday-turnover"', $html);
        $this->assertStringNotContainsString('>Сегодня</span>', $html);
        $this->assertStringNotContainsString('>Вчера</span>', $html);
    }

    public function test_monitors_off_first_html_is_still_ellipsis_when_payments_exist(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);
        $this->seedTbankPayment(
            $this->createUserWithRole('user', $this->partner),
            15_000_000,
            $now,
            'deal-html-flag-off'
        );

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="js-ops-monitors"', $html);
        $this->assertMatchesRegularExpression('/data-role="day-turnover">…<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/data-role="yesterday-turnover">…<\/span>/u', $html);
        $this->assertStringNotContainsString('data-role="day-turnover">150000', $html);
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
    }

    public function test_overlay_paints_zero_not_dash_when_day_snapshot_is_empty(): void
    {
        $painted = $this->simulateDayPaint([
            'ok' => true,
            'day' => ['turnover' => 0, 'commission' => 0, 'payments_count' => 0],
            'yesterday' => ['turnover' => 0, 'commission' => 0, 'payments_count' => 0],
        ]);

        $this->assertSame('0', $painted['day-turnover']);
        $this->assertSame('0', $painted['day-commission']);
        $this->assertSame('0', $painted['day-count']);
        $this->assertSame('0', $painted['yesterday-turnover']);
        $this->assertSame('0', $painted['yesterday-commission']);
        $this->assertSame('0', $painted['yesterday-count']);
        $this->assertNotSame('—', $painted['day-turnover']);
        $this->assertNotSame('—', $painted['yesterday-count']);
    }

    public function test_overlay_paints_plain_integers_without_thousand_spaces(): void
    {
        $painted = $this->simulateDayPaint([
            'ok' => true,
            'day' => ['turnover' => 150000, 'commission' => 600, 'payments_count' => 30],
            'yesterday' => ['turnover' => 80000, 'commission' => 320, 'payments_count' => 12],
        ]);

        $this->assertSame('150000', $painted['day-turnover']);
        $this->assertSame('600', $painted['day-commission']);
        $this->assertSame('30', $painted['day-count']);
        $this->assertSame('80000', $painted['yesterday-turnover']);
        $this->assertSame('320', $painted['yesterday-commission']);
        $this->assertSame('12', $painted['yesterday-count']);
        $this->assertStringNotContainsString(' ', $painted['day-turnover']);
        $this->assertStringNotContainsString(' ', $painted['yesterday-turnover']);
        $this->assertSame('textContent', $painted['write_mode']);
    }

    public function test_forbidden_and_network_refresh_clear_stale_today_and_yesterday(): void
    {
        $result = $this->simulateDayRefreshFailure();

        $this->assertSame('150000', $result['day_after_ok']);
        $this->assertSame('30', $result['count_after_ok']);
        $this->assertSame('80000', $result['yesterday_after_ok']);
        $this->assertSame('—', $result['day_after_network']);
        $this->assertSame('—', $result['yesterday_after_network']);
        $this->assertSame('—', $result['day_after_forbidden']);
        $this->assertSame('—', $result['yesterday_after_forbidden']);
        $this->assertSame('—', $result['count_after_forbidden']);
    }

    public function test_payload_without_day_keys_does_not_keep_previous_numbers(): void
    {
        $result = $this->simulateDayThenPartialPayload();

        $this->assertSame('150000', $result['day_after_full']);
        $this->assertSame('80000', $result['yesterday_after_full']);
        $this->assertSame('—', $result['day_after_partial']);
        $this->assertSame('—', $result['yesterday_after_partial']);
    }

    public function test_xss_string_in_turnover_is_written_via_text_content(): void
    {
        $result = $this->simulateDayPaint([
            'ok' => true,
            'day' => ['turnover' => '<img src=x>', 'commission' => 0, 'payments_count' => 0],
            'yesterday' => ['turnover' => 0, 'commission' => 0, 'payments_count' => 0],
        ]);

        $this->assertSame('<img src=x>', $result['day-turnover']);
        $this->assertSame('textContent', $result['write_mode']);
    }

    private function seedTbankPayment(
        User $student,
        int $summCents,
        Carbon $operationAt,
        string $dealId
    ): void {
        Payment::factory()->forUser($student)->create([
            'partner_id' => $student->partner_id,
            'summ_cents' => $summCents,
            'operation_date' => $operationAt->format('Y-m-d H:i:s'),
            'deal_id' => $dealId,
            'payment_id' => 'pid-'.$dealId,
            'payment_status' => 'paid',
        ]);
        TinkoffPayment::query()->create([
            'order_id' => 'order-'.$dealId,
            'partner_id' => (int) $student->partner_id,
            'amount' => $summCents,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => $dealId,
            'tinkoff_payment_id' => (string) random_int(300_000_000, 2_000_000_000),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function simulateDayPaint(array $payload): array
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
let writeMode = '';
function makeNode() {
    let stored = '…';
    return {
        classList: { remove: function () {}, add: function () {} },
        set textContent(value) {
            writeMode = 'textContent';
            stored = value;
        },
        get textContent() { return stored; }
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
    'day-turnover': nodes['day-turnover'].textContent,
    'day-commission': nodes['day-commission'].textContent,
    'day-count': nodes['day-count'].textContent,
    'yesterday-turnover': nodes['yesterday-turnover'].textContent,
    'yesterday-commission': nodes['yesterday-commission'].textContent,
    'yesterday-count': nodes['yesterday-count'].textContent,
    write_mode: writeMode
}));
JS;

        return $this->runNodeScript($script, [$bladePath, json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateDayRefreshFailure(): array
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
    queue: { worker: { code: 'alive' }, scheduler: { code: 'alive' }, jobs: 0, failed_jobs: 0, overdue_payouts: 0 },
    till: { overdue_payouts: 0, failed_intents: 0, fiscal_errors: 0 },
    errors: { count: 0 },
    gateways: {},
    auth: { failed_logins: 0, failed_2fa: 0 },
    welcome: { missing_count: 0, last_user_id: null },
    day: { turnover: 150000, commission: 600, payments_count: 30 },
    yesterday: { turnover: 80000, commission: 320, payments_count: 12 }
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
    const dayAfterOk = nodes['day-turnover'].textContent;
    const countAfterOk = nodes['day-count'].textContent;
    const yesterdayAfterOk = nodes['yesterday-turnover'].textContent;
    fetchMode = 'network';
    const tick = intervals.find(function (row) { return !row.cleared; });
    if (!tick || typeof tick.fn !== 'function') {
        throw new Error('ops poller not started');
    }
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const dayAfterNetwork = nodes['day-turnover'].textContent;
    const yesterdayAfterNetwork = nodes['yesterday-turnover'].textContent;
    nodes['day-turnover'].textContent = '150000';
    nodes['yesterday-turnover'].textContent = '80000';
    nodes['day-count'].textContent = '30';
    fetchMode = 'forbidden';
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    process.stdout.write(JSON.stringify({
        day_after_ok: dayAfterOk,
        count_after_ok: countAfterOk,
        yesterday_after_ok: yesterdayAfterOk,
        day_after_network: dayAfterNetwork,
        yesterday_after_network: yesterdayAfterNetwork,
        day_after_forbidden: nodes['day-turnover'].textContent,
        yesterday_after_forbidden: nodes['yesterday-turnover'].textContent,
        count_after_forbidden: nodes['day-count'].textContent
    }));
})().catch(function (err) {
    process.stderr.write(String(err && err.stack ? err.stack : err));
    process.exit(1);
});
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateDayThenPartialPayload(): array
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
    return { textContent: '…', classList: { remove: function () {}, add: function () {} } };
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
    day: { turnover: 150000, commission: 600, payments_count: 30 },
    yesterday: { turnover: 80000, commission: 320, payments_count: 12 }
});
const dayAfterFull = nodes['day-turnover'].textContent;
const yesterdayAfterFull = nodes['yesterday-turnover'].textContent;
render({
    ok: true,
    queue: {},
    till: {},
    errors: {},
    gateways: {},
    auth: {},
    welcome: {}
});
process.stdout.write(JSON.stringify({
    day_after_full: dayAfterFull,
    yesterday_after_full: yesterdayAfterFull,
    day_after_partial: nodes['day-turnover'].textContent,
    yesterday_after_partial: nodes['yesterday-turnover'].textContent
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
        $path = sys_get_temp_dir().'/ops-day-ux-'.uniqid('', true).'.cjs';
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
