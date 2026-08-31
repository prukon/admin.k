<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\SchoolLead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * UX строки Welcome: первый кадр «…», после опроса не «9» если письма ушли,
 * #id без email, 403 сбрасывает leftover.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsWelcomeAccountingUxFeatureTest extends SystemMonitorsTestCase
{
    public function test_first_open_welcome_row_is_ellipsis_not_stale_nine(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();
        $student = $this->seedMissingClient('welcome-first-html@example.test');

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Welcome</span>', $html);
        $this->assertMatchesRegularExpression('/data-role="welcome-count">…<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/data-role="welcome-user">…<\/span>/u', $html);
        $this->assertStringContainsString('Лид → клиент за 24 часа без успешно отправленного welcome-письма', $html);
        $this->assertStringContainsString('только #id, не email', $html);
        $this->assertStringNotContainsString('data-role="welcome-user">#'.$student->id, $html);
        $this->assertStringNotContainsString('data-role="welcome-count">9', $html);
        $this->assertStringNotContainsString('welcome-first-html@example.test', $html);

        $loginPos = strpos($html, '>Вход</span>');
        $welcomePos = strpos($html, '>Welcome</span>');
        $this->assertNotFalse($loginPos);
        $this->assertNotFalse($welcomePos);
        $this->assertGreaterThan($loginPos, $welcomePos);
    }

    public function test_login_page_does_not_render_welcome_row(): void
    {
        Auth::logout();

        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-ops-monitors"', $html);
        $this->assertStringNotContainsString('data-role="welcome-count"', $html);
    }

    public function test_overlay_paints_zero_not_nine_when_legacy_sent_snapshot_arrives(): void
    {
        $painted = $this->simulateWelcomePaint([
            'ok' => true,
            'queue' => ['jobs' => 0, 'failed_jobs' => 0, 'overdue_payouts' => 0],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => ['count' => 0],
            'gateways' => [],
            'auth' => ['failed_logins' => 0, 'failed_2fa' => 0],
            'welcome' => ['missing_count' => 0, 'last_user_id' => null, 'email' => 'should-not-appear@example.test'],
        ]);

        $this->assertSame('0', $painted['welcome-count']);
        $this->assertSame('is-ok', $painted['welcome-count-tone']);
        $this->assertSame('—', $painted['welcome-user']);
        $this->assertSame('is-muted', $painted['welcome-user-tone']);
        $this->assertStringNotContainsString('should-not-appear@example.test', $painted['welcome-user']);
        $this->assertNotSame('9', $painted['welcome-count']);
        $this->assertNotSame('#470', $painted['welcome-user']);
    }

    public function test_overlay_paints_nine_and_user_id_when_welcome_really_missing(): void
    {
        $painted = $this->simulateWelcomePaint([
            'ok' => true,
            'queue' => ['jobs' => 0, 'failed_jobs' => 0, 'overdue_payouts' => 0],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => ['count' => 0],
            'gateways' => [],
            'auth' => ['failed_logins' => 0, 'failed_2fa' => 0],
            'welcome' => ['missing_count' => 9, 'last_user_id' => 470],
        ]);

        $this->assertSame('9', $painted['welcome-count']);
        $this->assertSame('is-bad', $painted['welcome-count-tone']);
        $this->assertSame('#470', $painted['welcome-user']);
        $this->assertSame('is-warn', $painted['welcome-user-tone']);
    }

    public function test_forbidden_refresh_clears_stale_welcome_nine(): void
    {
        $result = $this->simulateWelcomeForbiddenRefresh();

        $this->assertSame('9', $result['count_after_ok']);
        $this->assertSame('#470', $result['user_after_ok']);
        $this->assertSame('—', $result['count_after_forbidden']);
        $this->assertSame('—', $result['user_after_forbidden']);
    }

    public function test_overlay_does_not_turn_zero_missing_into_dash_on_welcome_count(): void
    {
        $painted = $this->simulateWelcomePaint([
            'ok' => true,
            'queue' => ['jobs' => 0, 'failed_jobs' => 0, 'overdue_payouts' => 0],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => ['count' => 0],
            'gateways' => [],
            'auth' => ['failed_logins' => 0, 'failed_2fa' => 0],
            'welcome' => ['missing_count' => 0, 'last_user_id' => null],
        ]);

        $this->assertSame('0', $painted['welcome-count']);
        $this->assertNotSame('—', $painted['welcome-count']);
        $this->assertSame('—', $painted['welcome-user']);
    }

    private function seedMissingClient(string $email): User
    {
        $student = $this->createUserWithRole('user', $this->partner, [
            'email' => $email,
            'created_at' => now()->subHour(),
        ]);
        SchoolLead::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Лид',
            'phone' => '+7 900 202-02-02',
            'parent_email' => $email,
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id' => $student->id,
        ]);

        return $student;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function simulateWelcomePaint(array $payload): array
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
    const classes = new Set();
    return {
        textContent: '…',
        classList: {
            remove: function () {
                Array.prototype.forEach.call(arguments, function (name) { classes.delete(name); });
            },
            add: function (name) { classes.add(name); }
        },
        tone: function () { return Array.from(classes).join(' '); }
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
    'welcome-count': nodes['welcome-count'].textContent,
    'welcome-count-tone': nodes['welcome-count'].tone(),
    'welcome-user': nodes['welcome-user'].textContent,
    'welcome-user-tone': nodes['welcome-user'].tone()
}));
JS;

        return $this->runNodeScript($script, [$bladePath, json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateWelcomeForbiddenRefresh(): array
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
    errors: { count: 0, last_class: null, top_class: null },
    gateways: {},
    auth: { failed_logins: 0, failed_2fa: 0 },
    welcome: { missing_count: 9, last_user_id: 470 }
};
let fetchMode = 'ok';
global.fetch = function () {
    if (fetchMode === 'ok') {
        return Promise.resolve({
            ok: true,
            json: function () { return Promise.resolve(okPayload); }
        });
    }
    return Promise.resolve({
        ok: false,
        status: 403,
        json: function () { return Promise.resolve({ message: 'Forbidden' }); }
    });
};

(async function () {
    eval(src);
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const countAfterOk = nodes['welcome-count'].textContent;
    const userAfterOk = nodes['welcome-user'].textContent;
    fetchMode = 'forbidden';
    const tick = intervals.find(function (row) { return !row.cleared; });
    if (!tick || typeof tick.fn !== 'function') {
        throw new Error('ops poller not started');
    }
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    process.stdout.write(JSON.stringify({
        count_after_ok: countAfterOk,
        user_after_ok: userAfterOk,
        count_after_forbidden: nodes['welcome-count'].textContent,
        user_after_forbidden: nodes['welcome-user'].textContent
    }));
})().catch(function (err) {
    process.stderr.write(String(err && err.stack ? err.stack : err));
    process.exit(1);
});
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @param  list<string>  $args
     * @return array<string, mixed>
     */
    private function runNodeScript(string $script, array $args): array
    {
        $path = sys_get_temp_dir().'/ops-welcome-ux-'.uniqid('', true).'.cjs';
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
