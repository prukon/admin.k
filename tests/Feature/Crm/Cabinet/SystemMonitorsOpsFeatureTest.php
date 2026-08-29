<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Оверлей «Пульт»: шесть строк, GET /cabinet/system-monitors/ops, тот же тоггл.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsFeatureTest extends SystemMonitorsTestCase
{
    public function test_guest_cannot_read_ops(): void
    {
        Auth::logout();

        $json = $this->getJson($this->opsUrl());
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertTrue($json->isRedirect() || $json->status() === 401);
    }

    public function test_admin_without_permission_gets_403_and_does_not_see_overlay(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertForbidden();

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-ops-monitors"', $html);
        $this->assertStringNotContainsString('cabinet.system-monitors.ops', $html);
    }

    public function test_trainer_and_student_without_permission_get_403(): void
    {
        foreach (['trainer', 'user'] as $roleName) {
            $actor = $this->createUserWithRole($roleName, $this->partner);
            $this->actingInCurrentPartner($actor);
            $this->getJson($this->opsUrl(), $this->ajaxHeaders())->assertForbidden();
        }
    }

    public function test_superadmin_reads_ops_json(): void
    {
        $this->asSuperadmin();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('window_hours', 24);
    }

    public function test_html_get_still_returns_json_not_blank_page(): void
    {
        $this->asSuperadmin();

        $response = $this->actingAs($this->user)->get($this->opsUrl());
        $response
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertStringContainsString('json', strtolower((string) $response->headers->get('content-type')));
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_wrong_methods_are_not_silent_200(): void
    {
        $this->asSuperadmin();

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->opsUrl(), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON не 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON');
        }
    }

    public function test_route_is_protected_by_system_monitors_permission(): void
    {
        $route = Route::getRoutes()->getByName('cabinet.system-monitors.ops');
        $this->assertNotNull($route);
        $this->assertContains('can:settings.systemMonitors.view', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_dashboard_renders_ops_overlay_in_stack_above_online_and_reverb(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="system-monitors-stack"', $html);
        $this->assertStringContainsString('id="js-ops-monitors"', $html);
        $this->assertStringContainsString('id="js-online-users"', $html);
        $this->assertStringContainsString('id="js-reverb-status"', $html);
        $this->assertStringContainsString('class="ops-monitors system-monitor"', $html);
        $this->assertStringContainsString(route('cabinet.system-monitors.ops', [], false), $html);
        $this->assertStringContainsString('Пульт', $html);
        $this->assertStringContainsString('data-kids-tooltip-hint', $html);
        $this->assertMatchesRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);

        $opsPos = strpos($html, 'id="js-ops-monitors"');
        $onlinePos = strpos($html, 'id="js-online-users"');
        $reverbPos = strpos($html, 'id="js-reverb-status"');
        $this->assertNotFalse($opsPos);
        $this->assertNotFalse($onlinePos);
        $this->assertNotFalse($reverbPos);
        $this->assertLessThan($onlinePos, $opsPos, 'пульт должен быть выше онлайна');
        $this->assertLessThan($reverbPos, $onlinePos, 'онлайн должен быть выше Reverb');
    }

    public function test_overlay_stays_in_dom_when_monitors_are_off_but_hidden_by_css(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $html = $this->actingAs($this->user)
            ->get(route('chat.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="js-ops-monitors"', $html);
        $this->assertStringContainsString('body:not(.system-monitors-on) .system-monitor', $html);
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
    }

    public function test_login_page_does_not_render_ops_overlay(): void
    {
        Auth::logout();
        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-ops-monitors"', $html);
        $this->assertStringNotContainsString('cabinet.system-monitors.ops', $html);
    }

    public function test_overlay_script_does_not_poll_while_monitors_are_off(): void
    {
        $result = $this->simulateOpsWatching();

        $this->assertSame(0, $result['fetch_on_init_off']);
        $this->assertSame(0, $result['intervals_on_init_off']);
        $this->assertGreaterThan(0, $result['fetch_after_on']);
        $this->assertSame(
            $result['fetch_after_on'],
            $result['fetch_after_off'],
            'выключение должно остановить опрос пульта'
        );
        $this->assertSame(0, $result['live_intervals_after_off']);
    }

    /**
     * @return array<string, int>
     */
    private function simulateOpsWatching(): array
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

const bodyClasses = new Set();
const intervals = [];
let fetchCount = 0;
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
global.setInterval = function (fn) {
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
        json: function () { return Promise.resolve({ ok: true, queue: {}, till: {}, errors: {}, gateways: {}, auth: {}, welcome: {} }); }
    });
};

eval(src);
const fetchOnInitOff = fetchCount;
const intervalsOnInitOff = intervals.filter(function (row) { return !row.cleared; }).length;

bodyClasses.add('system-monitors-on');
listeners['system-monitors:change']({ detail: { on: true } });
const fetchAfterOn = fetchCount;

listeners['system-monitors:change']({ detail: { on: false } });
process.stdout.write(JSON.stringify({
    fetch_on_init_off: fetchOnInitOff,
    intervals_on_init_off: intervalsOnInitOff,
    fetch_after_on: fetchAfterOn,
    fetch_after_off: fetchCount,
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
        $path = sys_get_temp_dir().'/ops-monitors-overlay-'.uniqid('', true).'.cjs';
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
