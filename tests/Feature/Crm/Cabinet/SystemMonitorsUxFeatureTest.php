<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use Illuminate\Support\Facades\Auth;

/**
 * P1: UX переключателя — слева от колокольчика, дефолт выкл, персональный экран,
 * CSS-скрытие оверлея, откат при 422, опрос Reverb только пока включено.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsUxFeatureTest extends SystemMonitorsTestCase
{
    public function test_toggle_is_rendered_left_of_the_notification_bell(): void
    {
        $this->asSuperadmin();
        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $togglePos = strpos($html, 'id="system-monitors-toggle-wrap"');
        $bellPos = strpos($html, 'id="inAppNotificationBell"');
        $this->assertNotFalse($togglePos, 'переключатель должен быть в шапке');
        $this->assertNotFalse($bellPos, 'колокольчик должен быть в шапке');
        $this->assertLessThan(
            $bellPos,
            $togglePos,
            'переключатель должен стоять слева от колокольчика (раньше в ml-auto)'
        );

        $layout = (string) file_get_contents(resource_path('views/layouts/admin2.blade.php'));
        $includeToggle = strpos($layout, "includes.system_monitors_toggle");
        $includeBell = strpos($layout, "includes.in_app_notifications.bell");
        $this->assertNotFalse($includeToggle);
        $this->assertNotFalse($includeBell);
        $this->assertLessThan($includeBell, $includeToggle);
    }

    public function test_first_open_shows_switch_off_without_forcing_overlays(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');
        $this->assertFalse((bool) $superadmin->system_monitors, 'фабрика не должна включать мониторы');

        $html = $this->actingInCurrentPartner($superadmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="system-monitors-toggle"', $html);
        $this->assertStringContainsString('role="switch"', $html);
        $this->assertStringContainsString('ios-switch', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="system-monitors-toggle"[^>]*\bdisabled\b/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="system-monitors-toggle"[^>]*data-on="0"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="system-monitors-toggle"[^>]*aria-checked="false"/',
            $html
        );
        $this->assertTrue(
            (bool) preg_match(
                '/<button[^>]*id="system-monitors-toggle"[^>]*>.*?<\/button>/s',
                $html,
                $buttonMatch
            )
        );
        $buttonHtml = $buttonMatch[0];
        $this->assertStringContainsString('Показать системные мониторы', $buttonHtml);
        $this->assertStringNotContainsString('Скрыть системные мониторы', $buttonHtml);
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
        $this->assertStringContainsString('id="js-reverb-status"', $html);
        $this->assertStringContainsString('class="reverb-status system-monitor"', $html);
        $this->assertStringContainsString('body:not(.system-monitors-on) .system-monitor', $html);
        $this->assertStringContainsString('display: none !important', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="system-monitors-error"[^>]*is-visible/',
            $html
        );
    }

    public function test_enabled_flag_marks_switch_on_and_shows_overlays_on_cabinet_chat_and_settings(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        foreach ([route('dashboard'), route('chat.index'), route('admin.setting.setting')] as $url) {
            $html = $this->actingAs($this->user)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html, $url);
            $this->assertMatchesRegularExpression(
                '/id="system-monitors-toggle"[^>]*data-on="1"/',
                $html,
                $url
            );
            $this->assertMatchesRegularExpression(
                '/id="system-monitors-toggle"[^>]*aria-checked="true"/',
                $html,
                $url
            );
            $this->assertStringContainsString('Скрыть системные мониторы', $html);
            $this->assertStringContainsString('id="js-reverb-status"', $html);
            $this->assertStringContainsString('data-status-url="'.route('chat.api.reverb-status').'"', $html);
        }
    }

    public function test_admin_without_permission_does_not_see_switch_even_if_flag_is_on(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="system-monitors-toggle"', $html);
        $this->assertStringNotContainsString('id="js-reverb-status"', $html);
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
        $this->assertTrue(
            (bool) $this->user->fresh()->system_monitors,
            'личный флаг в БД не должен сбрасываться только из-за отсутствия права'
        );
    }

    public function test_enabling_monitors_does_not_turn_them_on_for_another_superadmin(): void
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
        $this->assertMatchesRegularExpression(
            '/id="system-monitors-toggle"[^>]*data-on="1"/',
            $mine
        );

        $theirs = $this->actingInCurrentPartner($other)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/<body[^>]*\bsystem-monitors-on\b/',
            $theirs,
            'чужой суперадмин не должен видеть оверлеи из-за чужого флага'
        );
        $this->assertMatchesRegularExpression(
            '/id="system-monitors-toggle"[^>]*data-on="0"/',
            $theirs
        );
        $this->assertFalse((bool) $other->fresh()->system_monitors);
    }

    public function test_login_and_landing_do_not_render_the_switch(): void
    {
        Auth::logout();

        foreach ([route('login'), route('landing.home')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('id="system-monitors-toggle"', $html, $url);
            $this->assertStringNotContainsString('cabinet.system-monitors.update', $html, $url);
            $this->assertStringNotContainsString('id="js-reverb-status"', $html, $url);
        }
    }

    public function test_settings_page_has_header_switch_instead_of_reverb_overlay_button(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $html = $this->actingAs($this->user)
            ->get(route('admin.setting.setting'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="system-monitors-toggle"', $html);
        $this->assertStringNotContainsString('id="rowCabinetDiagnostics"', $html);
        $this->assertStringNotContainsString('id="btnCabinetDiagnostics"', $html);
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
    }

    public function test_failed_save_rolls_back_switch_and_hides_overlays(): void
    {
        $result = $this->simulateToggleClick([
            'initialOn' => false,
            'responseOk' => false,
            'payload' => [
                'success' => false,
                'message' => 'Некорректное значение системных мониторов.',
                'errors' => ['system_monitors' => ['Некорректное значение системных мониторов.']],
            ],
        ]);

        $this->assertTrue($result['optimistic_on'], 'клик сразу включает переключатель');
        $this->assertTrue($result['optimistic_body'], 'клик сразу вешает class на body');
        $this->assertFalse($result['final_on'], '422 должен вернуть переключатель в выкл');
        $this->assertFalse($result['final_body'], '422 должен снять system-monitors-on — иначе оверлей останется');
        $this->assertSame(
            'Некорректное значение системных мониторов.',
            $result['error']
        );
        $this->assertFalse($result['disabled'], 'кнопка не должна остаться заблокированной');
        $this->assertSame([true, false], $result['events']);
    }

    public function test_successful_save_keeps_server_flag_not_the_optimistic_guess(): void
    {
        $result = $this->simulateToggleClick([
            'initialOn' => false,
            'responseOk' => true,
            'payload' => [
                'success' => true,
                'system_monitors' => true,
            ],
        ]);

        $this->assertTrue($result['final_on']);
        $this->assertTrue($result['final_body']);
        $this->assertSame('', $result['error']);
        $this->assertSame([true, true], $result['events']);
    }

    public function test_second_click_while_saving_is_ignored(): void
    {
        $result = $this->simulateToggleClick([
            'initialOn' => false,
            'responseOk' => true,
            'payload' => ['success' => true, 'system_monitors' => true],
            'doubleClick' => true,
        ]);

        $this->assertSame(1, $result['fetch_count'], 'пока идёт сохранение второй клик не шлёт POST');
        $this->assertTrue($result['final_on']);
    }

    public function test_overlay_does_not_poll_reverb_while_monitors_are_off(): void
    {
        $result = $this->simulateOverlayWatching();

        $this->assertSame(0, $result['fetch_on_init_off'], 'выключенный переключатель не должен дергать /reverb-status');
        $this->assertSame(0, $result['intervals_on_init_off']);
        $this->assertGreaterThan(0, $result['fetch_after_on'], 'включение должно запустить опрос процесса');
        $this->assertGreaterThan(0, $result['intervals_after_on']);
        $this->assertSame(
            $result['fetch_after_on'],
            $result['fetch_after_off'],
            'выключение должно остановить setInterval, а не продолжать опрос скрытого оверлея'
        );
        $this->assertSame(0, $result['live_intervals_after_off']);
    }

    /**
     * @param  array{initialOn: bool, responseOk: bool, payload: array<string, mixed>, doubleClick?: bool}  $opts
     * @return array<string, mixed>
     */
    private function simulateToggleClick(array $opts): array
    {
        $bladePath = resource_path('views/includes/system_monitors_toggle.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const opts = JSON.parse(process.argv[3]);
const start = blade.indexOf('(function() {');
const end = blade.lastIndexOf('})();');
if (start < 0 || end < 0) {
    throw new Error('toggle IIFE not found');
}
const src = blade.slice(start, end + 5);

const events = [];
const hiddenLabel = { textContent: opts.initialOn ? 'Скрыть системные мониторы' : 'Показать системные мониторы' };
const errorBox = { textContent: '', classList: { values: new Set(), add(c) { this.values.add(c); }, remove(c) { this.values.delete(c); } } };
const bodyClasses = new Set(opts.initialOn ? ['system-monitors-on'] : []);
const button = {
    disabled: false,
    attrs: {
        'data-on': opts.initialOn ? '1' : '0',
        'aria-checked': opts.initialOn ? 'true' : 'false',
        title: opts.initialOn ? 'Скрыть системные мониторы' : 'Показать системные мониторы',
        'data-url': '/cabinet/system-monitors',
        'data-csrf': 'token'
    },
    listeners: {},
    getAttribute(name) { return this.attrs[name]; },
    setAttribute(name, value) { this.attrs[name] = String(value); },
    querySelector() { return hiddenLabel; },
    addEventListener(type, fn) { this.listeners[type] = fn; }
};

let fetchCount = 0;
let optimisticOn = null;
let optimisticBody = null;
const snapshotAfterFirstApply = { done: false };

global.document = {
    getElementById(id) {
        if (id === 'system-monitors-toggle') return button;
        if (id === 'system-monitors-error') return errorBox;
        return null;
    },
    body: {
        classList: {
            toggle(name, on) {
                if (on) bodyClasses.add(name); else bodyClasses.delete(name);
            },
            contains(name) { return bodyClasses.has(name); }
        }
    },
    dispatchEvent(event) {
        events.push(!!(event && event.detail && event.detail.on));
        if (!snapshotAfterFirstApply.done && events.length === 1) {
            optimisticOn = button.attrs['data-on'] === '1';
            optimisticBody = bodyClasses.has('system-monitors-on');
            snapshotAfterFirstApply.done = true;
        }
    }
};
global.CustomEvent = function (name, init) {
    this.type = name;
    this.detail = init && init.detail;
};
global.fetch = function () {
    fetchCount += 1;
    return Promise.resolve({
        ok: !!opts.responseOk,
        json: function () { return Promise.resolve(opts.payload); }
    });
};
global.URLSearchParams = URLSearchParams;

eval(src);
button.listeners.click();
if (opts.doubleClick) {
    button.listeners.click();
}

setTimeout(function () {
    process.stdout.write(JSON.stringify({
        optimistic_on: optimisticOn,
        optimistic_body: optimisticBody,
        final_on: button.attrs['data-on'] === '1',
        final_body: bodyClasses.has('system-monitors-on'),
        error: errorBox.textContent || '',
        disabled: !!button.disabled,
        events: events,
        fetch_count: fetchCount
    }));
}, 50);
JS;

        return $this->runNodeScript($script, [$bladePath, json_encode($opts, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @return array<string, int>
     */
    private function simulateOverlayWatching(): array
    {
        $bladePath = resource_path('views/includes/chat/reverb_status.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('(function () {');
const end = blade.lastIndexOf('})();');
if (start < 0 || end < 0) {
    throw new Error('overlay IIFE not found');
}
const src = blade.slice(start, end + 5);

const bodyClasses = new Set();
const intervals = [];
let fetchCount = 0;
const listeners = {};

function classList() {
    const s = new Set();
    return {
        remove: function () { Array.prototype.forEach.call(arguments, (c) => s.delete(c)); },
        add: function (c) { s.add(c); }
    };
}

const root = {
    classList: classList(),
    getAttribute: function () { return '/chat/api/reverb-status'; },
    querySelector: function (sel) {
        if (sel === '[data-role="copy"]') {
            return { classList: classList(), setAttribute: function () {}, addEventListener: function () {} };
        }
        return {
            classList: classList(),
            textContent: '…',
            setAttribute: function () {}
        };
    }
};

global.document = {
    getElementById: function (id) { return id === 'js-reverb-status' ? root : null; },
    body: {
        classList: {
            contains: function (name) { return bodyClasses.has(name); }
        }
    },
    addEventListener: function (type, fn) { listeners[type] = fn; }
};
global.window = global;
global.Echo = undefined;
global.setInterval = function (fn) {
    const id = intervals.length + 1;
    intervals.push({ id: id, fn: fn, cleared: false });
    return id;
};
global.clearInterval = function (id) {
    intervals.forEach(function (row) { if (row.id === id) row.cleared = true; });
};
global.fetch = function () {
    fetchCount += 1;
    return Promise.resolve({
        ok: true,
        json: function () { return Promise.resolve({ listening: true, host: '127.0.0.1', port: 6009 }); }
    });
};

eval(src);
const fetchOnInitOff = fetchCount;
const intervalsOnInitOff = intervals.filter(function (row) { return !row.cleared; }).length;

bodyClasses.add('system-monitors-on');
listeners['system-monitors:change']({ detail: { on: true } });
const fetchAfterOn = fetchCount;
const intervalsAfterOn = intervals.filter(function (row) { return !row.cleared; }).length;

listeners['system-monitors:change']({ detail: { on: false } });
const fetchAfterOff = fetchCount;
const liveAfterOff = intervals.filter(function (row) { return !row.cleared; }).length;

process.stdout.write(JSON.stringify({
    fetch_on_init_off: fetchOnInitOff,
    intervals_on_init_off: intervalsOnInitOff,
    fetch_after_on: fetchAfterOn,
    intervals_after_on: intervalsAfterOn,
    fetch_after_off: fetchAfterOff,
    live_intervals_after_off: liveAfterOff
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
        $path = sys_get_temp_dir().'/system-monitors-ux-'.uniqid('', true).'.cjs';
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
