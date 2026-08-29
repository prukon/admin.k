<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Partner;
use App\Models\User;
use App\Services\Chat\UserPresence;
use App\Support\OnlineUsersMonitor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Оверлей «Онлайн»: все партнёры и роли, окно last_seen_at 120 с,
 * GET /cabinet/system-monitors/online-users, тот же тоггл что у Reverb.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOnlineUsersFeatureTest extends SystemMonitorsTestCase
{
    public function test_guest_cannot_read_online_users(): void
    {
        Auth::logout();

        $json = $this->getJson($this->onlineUsersUrl());
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertTrue($json->isRedirect() || $json->status() === 401);

        $html = $this->get($this->onlineUsersUrl());
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertTrue($html->isRedirect() || in_array($html->getStatusCode(), [401, 403, 419], true));
    }

    public function test_admin_without_permission_gets_403_and_does_not_see_overlay(): void
    {
        $this->asAdmin();
        $this->user->forceFill([
            'system_monitors' => true,
            'last_seen_at' => now(),
        ])->save();

        $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertForbidden();

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-online-users"', $html);
        $this->assertStringNotContainsString('id="system-monitors-stack"', $html);
    }

    public function test_trainer_and_student_without_permission_get_403(): void
    {
        foreach (['trainer', 'user'] as $roleName) {
            $actor = $this->createUserWithRole($roleName, $this->partner, [
                'last_seen_at' => now(),
            ]);
            $this->actingInCurrentPartner($actor);
            $this->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())->assertForbidden();
        }
    }

    public function test_superadmin_reads_grouped_online_users_json(): void
    {
        $this->asSuperadmin();
        $alpha = Partner::factory()->create(['title' => 'Альфа-школа']);
        $beta = Partner::factory()->create(['title' => 'Бета-школа']);

        $ivan = $this->createUserWithRole('user', $alpha, [
            'lastname' => 'Иванов',
            'name' => 'Иван',
            'last_seen_at' => now(),
            'is_enabled' => 1,
        ]);
        $petr = $this->createUserWithRole('trainer', $alpha, [
            'lastname' => 'Петров',
            'name' => 'Петр',
            'last_seen_at' => now()->subSeconds(10),
            'is_enabled' => 0,
        ]);
        $this->createUserWithRole('admin', $alpha, [
            'lastname' => 'Оффлайнов',
            'name' => 'Олег',
            'last_seen_at' => now()->subSeconds(UserPresence::ONLINE_WITHIN_SECONDS + 5),
        ]);
        $igor = $this->createUserWithRole('admin', $beta, [
            'lastname' => 'Лебедев',
            'name' => 'Игорь',
            'last_seen_at' => now(),
        ]);
        $orphan = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Сирота',
            'name' => 'Саша',
            'partner_id' => null,
            'last_seen_at' => now(),
        ]);
        $gone = $this->createUserWithRole('user', $alpha, [
            'lastname' => 'Удалённый',
            'name' => 'Юзер',
            'last_seen_at' => now(),
        ]);
        $gone->delete();

        $response = $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('online_within_seconds', UserPresence::ONLINE_WITHIN_SECONDS)
            ->assertJsonPath('total', 4)
            ->assertJsonPath('partners.0.title', 'Альфа-школа')
            ->assertJsonPath('partners.0.count', 2)
            ->assertJsonPath('partners.0.users.0.name', 'Иванов Иван')
            ->assertJsonPath('partners.0.users.1.name', 'Петров Петр')
            ->assertJsonPath('partners.1.title', 'Бета-школа')
            ->assertJsonPath('partners.1.count', 1)
            ->assertJsonPath('partners.1.users.0.name', 'Лебедев Игорь')
            ->assertJsonPath('partners.2.title', OnlineUsersMonitor::MISSING_PARTNER_TITLE)
            ->assertJsonPath('partners.2.count', 1)
            ->assertJsonPath('partners.2.users.0.name', 'Сирота Саша');

        $ids = collect($response->json('partners.0.users'))->pluck('id')->all();
        $this->assertContains($ivan->id, $ids);
        $this->assertContains($petr->id, $ids);
        $this->assertNotContains($gone->id, $ids);
        $this->assertSame($igor->id, (int) $response->json('partners.1.users.0.id'));
        $this->assertNull($response->json('partners.2.id'));
        $this->assertSame($orphan->id, (int) $response->json('partners.2.users.0.id'));
    }

    public function test_html_get_still_returns_json_not_blank_page(): void
    {
        $this->asSuperadmin();

        $response = $this->actingAs($this->user)->get($this->onlineUsersUrl());
        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 0)
            ->assertJsonPath('partners', []);
        $this->assertStringContainsString('json', strtolower((string) $response->headers->get('content-type')));
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_wrong_methods_are_not_silent_200(): void
    {
        $this->asSuperadmin();

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->onlineUsersUrl(), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON не 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON');
        }
    }

    public function test_route_is_protected_by_system_monitors_permission(): void
    {
        $route = Route::getRoutes()->getByName('cabinet.system-monitors.online-users');
        $this->assertNotNull($route);
        $this->assertContains('can:settings.systemMonitors.view', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_dashboard_renders_online_overlay_in_stack_above_reverb(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="system-monitors-stack"', $html);
        $this->assertStringContainsString('id="js-online-users"', $html);
        $this->assertStringContainsString('id="js-reverb-status"', $html);
        $this->assertStringContainsString('class="online-users system-monitor"', $html);
        $this->assertStringContainsString(route('cabinet.system-monitors.online-users', [], false), $html);
        $this->assertStringContainsString('Онлайн (', $html);
        $this->assertStringContainsString('data-role="total"', $html);
        $this->assertStringContainsString('data-role="list"', $html);
        $this->assertStringContainsString('Никого нет онлайн', $html);
        $this->assertMatchesRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);

        $onlinePos = strpos($html, 'id="js-online-users"');
        $reverbPos = strpos($html, 'id="js-reverb-status"');
        $this->assertNotFalse($onlinePos);
        $this->assertNotFalse($reverbPos);
        $this->assertLessThan($reverbPos, $onlinePos, 'онлайн должен быть выше Reverb в колонке справа');
    }

    public function test_overlay_stays_in_dom_when_monitors_are_off_but_hidden_by_css(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $html = $this->actingAs($this->user)
            ->get(route('chat.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="js-online-users"', $html);
        $this->assertStringContainsString('body:not(.system-monitors-on) .system-monitor', $html);
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
    }

    public function test_login_page_does_not_render_online_overlay(): void
    {
        Auth::logout();
        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-online-users"', $html);
        $this->assertStringNotContainsString('cabinet.system-monitors.online-users', $html);
    }

    public function test_overlay_script_does_not_poll_while_monitors_are_off(): void
    {
        $result = $this->simulateOnlineWatching();

        $this->assertSame(0, $result['fetch_on_init_off']);
        $this->assertSame(0, $result['intervals_on_init_off']);
        $this->assertGreaterThan(0, $result['fetch_after_on']);
        $this->assertSame(
            $result['fetch_after_on'],
            $result['fetch_after_off'],
            'выключение должно остановить опрос списка онлайн'
        );
        $this->assertSame(0, $result['live_intervals_after_off']);
    }

    public function test_overlay_render_groups_partners_like_the_agreed_layout(): void
    {
        $result = $this->simulateOnlineRender([
            'ok' => true,
            'total' => 4,
            'partners' => [
                [
                    'id' => 1,
                    'title' => 'Партнер 1',
                    'count' => 3,
                    'users' => [
                        ['id' => 1, 'name' => 'Иванов Иван'],
                        ['id' => 2, 'name' => 'Петров Петр'],
                        ['id' => 3, 'name' => 'Сидоров Ярослав'],
                    ],
                ],
                [
                    'id' => 2,
                    'title' => 'Партнер 2',
                    'count' => 1,
                    'users' => [
                        ['id' => 4, 'name' => 'Лебедев Игорь'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('4', $result['total']);
        $this->assertStringContainsString('Партнер 1 (3)', $result['html']);
        $this->assertStringContainsString('Иванов Иван', $result['html']);
        $this->assertStringContainsString('Петров Петр', $result['html']);
        $this->assertStringContainsString('Сидоров Ярослав', $result['html']);
        $this->assertStringContainsString('Партнер 2 (1)', $result['html']);
        $this->assertStringContainsString('Лебедев Игорь', $result['html']);
        $this->assertLessThan(
            (int) strpos($result['html'], 'Партнер 2 (1)'),
            (int) strpos($result['html'], 'Партнер 1 (3)')
        );
        $this->assertStringContainsString('Онлайн (4)', $result['copy']);
        $this->assertStringContainsString("Партнер 1 (3)\nИванов Иван", $result['copy']);
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
        json: function () { return Promise.resolve({ ok: true, total: 0, partners: [] }); }
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
     * @param  list<string>  $args
     * @return array<string, mixed>
     */
    private function runNodeScript(string $script, array $args): array
    {
        $path = sys_get_temp_dir().'/online-users-overlay-'.uniqid('', true).'.cjs';
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
