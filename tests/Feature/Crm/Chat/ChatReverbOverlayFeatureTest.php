<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use Illuminate\Support\Facades\Auth;

/**
 * Оверлей Reverb (право settings.systemMonitors.view + персональный флаг) и GET /chat/api/reverb-status:
 * процесс = слушает ли внутренний порт, сокет = Echo.
 * UX: процесс down + сокет connecting — не «всё ок»; лишний wsPath ломает handshake.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatReverbOverlayFeatureTest extends ChatTestCase
{
    public function test_guest_json_request_to_reverb_status_is_unauthorized(): void
    {
        Auth::logout();

        $response = $this->getJson(route('chat.api.reverb-status'));
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertUnauthorized();
    }

    public function test_guest_html_request_to_reverb_status_is_redirected_to_login(): void
    {
        Auth::logout();

        $response = $this->get(route('chat.api.reverb-status'));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
        $this->assertGuest();
    }

    public function test_guest_mutating_reverb_status_does_not_return_server_error(): void
    {
        Auth::logout();

        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.reverb-status'));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость не 500');
            $this->assertContains($json->getStatusCode(), [401, 405, 419]);

            $html = $this->call($method, route('chat.api.reverb-status'));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML гость не 500');
            $this->assertTrue(
                $html->isRedirect() || in_array($html->getStatusCode(), [401, 405, 419], true),
                $method.' HTML гость: редирект/401/405/419, получено '.$html->getStatusCode()
            );
        }
    }

    public function test_manager_without_permission_gets_403_on_reverb_status(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $this->getJson(route('chat.api.reverb-status'))->assertForbidden();
        $this->get(route('chat.api.reverb-status'))->assertForbidden();
    }

    public function test_user_with_messages_view_still_cannot_read_reverb_status(): void
    {
        $this->getJson(route('chat.api.reverb-status'))->assertForbidden();
        $this->get(route('chat.api.reverb-status'))->assertForbidden();
    }

    public function test_admin_and_trainer_cannot_read_reverb_status(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $this->getJson(route('chat.api.reverb-status'))->assertForbidden();

        $trainer = $this->createUserWithRole('trainer');
        $this->actingInPartner($trainer);
        $this->getJson(route('chat.api.reverb-status'))->assertForbidden();
    }

    public function test_superadmin_reads_reverb_status_even_without_messages_view(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');
        $this->actingInPartner($superadmin);
        $this->configureClosedReverbPort();

        $this->getJson(route('chat.api.reverb-status'))
            ->assertOk()
            ->assertJsonPath('listening', false);
    }

    public function test_superadmin_reverb_status_json_names_closed_process_without_empty_200(): void
    {
        $this->actingAsSuperadmin();
        $this->configureClosedReverbPort();

        $response = $this->getJson(route('chat.api.reverb-status'));
        $response
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('listening', false)
            ->assertJsonPath('host', '127.0.0.1')
            ->assertJsonPath('port', 1)
            ->assertJsonPath('driver', 'reverb');
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_superadmin_html_get_still_returns_json_status_not_blank_page(): void
    {
        $this->actingAsSuperadmin();
        $this->configureClosedReverbPort();

        $response = $this->get(route('chat.api.reverb-status'));
        $response
            ->assertOk()
            ->assertJsonPath('listening', false);
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
    }

    public function test_superadmin_mutating_reverb_status_is_not_a_silent_200(): void
    {
        $this->actingAsSuperadmin();

        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $response = $this->json($method, route('chat.api.reverb-status'));
            $this->assertNotSame(500, $response->getStatusCode(), $method.' не 500');
            $this->assertNotSame(200, $response->getStatusCode(), $method.' не пустой 200');
            $this->assertSame(405, $response->getStatusCode(), $method.' должен быть 405');
        }
    }

    public function test_reverb_status_reports_listening_when_internal_port_is_open(): void
    {
        $this->actingAsSuperadmin();
        [$server, $port] = $this->listenOnEphemeralPort();

        try {
            config([
                'broadcasting.default' => 'reverb',
                'broadcasting.connections.reverb.options.host' => '127.0.0.1',
                'broadcasting.connections.reverb.options.port' => $port,
            ]);

            $this->getJson(route('chat.api.reverb-status'))
                ->assertOk()
                ->assertJsonPath('listening', true)
                ->assertJsonPath('ok', true)
                ->assertJsonPath('host', '127.0.0.1')
                ->assertJsonPath('port', $port)
                ->assertJsonPath('driver', 'reverb');
        } finally {
            fclose($server);
        }
    }

    public function test_process_check_uses_internal_server_port_not_public_client_host(): void
    {
        $this->actingAsSuperadmin();
        [$server, $openPort] = $this->listenOnEphemeralPort();

        try {
            config([
                'broadcasting.default' => 'reverb',
                'broadcasting.connections.reverb.options.host' => '127.0.0.1',
                'broadcasting.connections.reverb.options.port' => 1,
                'reverb.apps.apps.0.options.host' => '127.0.0.1',
                'reverb.apps.apps.0.options.port' => $openPort,
                'reverb.apps.apps.0.options.scheme' => 'http',
            ]);

            $this->getJson(route('chat.api.reverb-status'))
                ->assertOk()
                ->assertJsonPath('listening', false)
                ->assertJsonPath('port', 1);
        } finally {
            fclose($server);
        }
    }

    public function test_empty_host_or_zero_port_is_reported_as_process_down(): void
    {
        $this->actingAsSuperadmin();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.options.host' => '',
            'broadcasting.connections.reverb.options.port' => 0,
        ]);

        $this->getJson(route('chat.api.reverb-status'))
            ->assertOk()
            ->assertJsonPath('listening', false)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('host', '')
            ->assertJsonPath('port', 0);
    }

    public function test_regular_user_does_not_see_reverb_overlay_on_chat_or_dashboard(): void
    {
        $chat = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-reverb-status"', $chat);

        $dashboard = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-reverb-status"', $dashboard);
    }

    public function test_admin_with_messages_view_does_not_see_reverb_overlay(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->grantPermission($admin, 'messages.view');
        $this->actingInPartner($admin);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-reverb-status"', $html);
        $this->assertStringNotContainsString('data-role="process-dot"', $html);
    }

    public function test_guest_login_page_does_not_render_reverb_overlay(): void
    {
        Auth::logout();

        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-reverb-status"', $html);
    }

    public function test_superadmin_does_not_show_reverb_overlay_when_monitors_are_off(): void
    {
        $this->actingAsSuperadmin();
        $actor = auth()->user();
        $this->assertNotNull($actor);
        $actor->forceFill(['system_monitors' => false])->save();

        $dashboard = $this->get(route('dashboard'))->assertOk()->getContent();
        $chat = $this->get(route('chat.index'))->assertOk()->getContent();
        $settings = $this->get(route('admin.setting.setting'))->assertOk()->getContent();

        foreach ([$dashboard, $chat, $settings] as $html) {
            $this->assertStringContainsString('id="js-reverb-status"', $html);
            $this->assertStringContainsString('system-monitor', $html);
            $this->assertStringContainsString('id="system-monitors-toggle"', $html);
            $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
            $this->assertStringNotContainsString('id="btnCabinetDiagnostics"', $html);
        }
    }

    public function test_superadmin_sees_reverb_overlay_on_dashboard_and_chat_above_other_windows(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');
        $superadmin->forceFill(['system_monitors' => true])->save();
        $this->actingInPartner($superadmin);

        $dashboard = $this->get(route('dashboard'))->assertOk()->getContent();
        $chat = $this->get(route('chat.index'))->assertOk()->getContent();
        $settings = $this->get(route('admin.setting.setting'))->assertOk()->getContent();

        foreach ([$dashboard, $chat, $settings] as $html) {
            $this->assertMatchesRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
            $this->assertStringContainsString('id="js-reverb-status"', $html);
            $this->assertStringContainsString('data-status-url="'.route('chat.api.reverb-status').'"', $html);
            $this->assertStringContainsString('data-role="process-dot"', $html);
            $this->assertStringContainsString('data-role="socket-dot"', $html);
            $this->assertStringContainsString('data-role="copy"', $html);
            $this->assertStringContainsString('z-index: 20000', $html);
            $this->assertStringContainsString('position: fixed', $html);
            $this->assertStringContainsString('>процесс<', $html);
            $this->assertStringContainsString('>сокет<', $html);
        }

        $this->assertStringContainsString('sidebar-mini layout-fixed', $settings);
        $this->assertStringContainsString('id="system-monitors-toggle"', $settings);
        $this->assertStringNotContainsString('id="btnCabinetDiagnostics"', $settings);
    }

    public function test_echo_client_on_crm_pages_does_not_set_wspath_that_breaks_handshake(): void
    {
        $this->actingAsSuperadmin();
        config(['broadcasting.connections.reverb.key' => 'overlay-test-key']);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString("broadcaster: 'reverb'", $html);
        $this->assertStringContainsString('overlay-test-key', $html);
        $this->assertStringContainsString('wsHost: window.location.hostname', $html);
        $this->assertStringContainsString('forceTLS: true', $html);
        $this->assertStringContainsString('wssPort: 443', $html);
        $this->assertStringNotContainsString("wsPath: '/app'", $html);
        $this->assertStringNotContainsString('wsPath: "/app"', $html);
        $this->assertStringNotContainsString("wsPath:'/app'", $html);
    }

    public function test_overlay_script_is_rendered_after_echo_so_it_can_bind_socket_state(): void
    {
        $this->actingAsSuperadmin();
        $actor = auth()->user();
        $this->assertNotNull($actor);
        $actor->forceFill(['system_monitors' => true])->save();
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $echoPos = strpos($html, "broadcaster: 'reverb'");
        $overlayPos = strpos($html, 'id="js-reverb-status"');
        $this->assertNotFalse($echoPos);
        $this->assertNotFalse($overlayPos);
        $this->assertLessThan(
            $overlayPos,
            $echoPos,
            'Echo должен создаться до оверлея, иначе state_change не повесится'
        );
    }

    public function test_overlay_does_not_treat_connecting_socket_as_healthy_when_process_is_down(): void
    {
        $result = $this->simulateOverlayPaint();

        $downConnecting = $result['down_connecting'];
        $this->assertSame('down', $downConnecting['process']);
        $this->assertSame('connecting', $downConnecting['socket']);
        $this->assertContains('is-warn', $downConnecting['root']);
        $this->assertNotContains('is-ok', $downConnecting['root']);
        $this->assertContains('is-bad', $downConnecting['process_dot']);
        $this->assertContains('is-warn', $downConnecting['socket_dot']);
        $this->assertStringContainsString('процесс: down', $downConnecting['copy']);
        $this->assertStringContainsString('127.0.0.1:6009', $downConnecting['copy']);
        $this->assertStringContainsString('сокет: connecting', $downConnecting['copy']);
    }

    public function test_overlay_is_ok_only_when_process_is_up_and_socket_is_connected(): void
    {
        $result = $this->simulateOverlayPaint();

        $this->assertContains('is-ok', $result['up_connected']['root']);
        $this->assertSame('up', $result['up_connected']['process']);
        $this->assertSame('connected', $result['up_connected']['socket']);

        $this->assertContains('is-warn', $result['up_connecting']['root']);
        $this->assertNotContains('is-ok', $result['up_connecting']['root']);
        $this->assertSame('connecting', $result['up_connecting']['socket']);

        $this->assertContains('is-warn', $result['up_initialized']['root']);
        $this->assertNotContains('is-ok', $result['up_initialized']['root']);

        $this->assertContains('is-bad', $result['down_failed']['root']);
        $this->assertNotContains('is-ok', $result['down_failed']['root']);
        $this->assertSame('failed', $result['down_failed']['socket']);

        $this->assertContains('is-bad', $result['no_echo']['root']);
        $this->assertSame('не создан', $result['no_echo']['socket']);
    }

    public function test_overlay_fetch_contract_polls_process_separately_from_socket(): void
    {
        $blade = (string) file_get_contents(resource_path('views/includes/chat/reverb_status.blade.php'));

        $this->assertStringContainsString("credentials: 'same-origin'", $blade);
        $this->assertStringContainsString("'Accept': 'application/json'", $blade);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $blade);
        $this->assertStringContainsString('setInterval(refreshProcess, 3000)', $blade);
        $this->assertStringContainsString('setInterval(paint, 1000)', $blade);
        $this->assertStringContainsString("connection.bind('state_change'", $blade);
        $this->assertStringContainsString('data.listening', $blade);
        $this->assertStringContainsString('data.host + \':\' + data.port', $blade);
        $this->assertStringContainsString('SystemMonitors::canView(auth()->user())', $blade);
        $this->assertStringNotContainsString('@can(', $blade);
        $this->assertStringNotContainsString('messages.view', $blade);
    }

    private function actingAsSuperadmin(): void
    {
        $this->actingInPartner($this->createUserWithRole('superadmin'));
    }

    private function configureClosedReverbPort(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
        ]);
    }

    /**
     * @return array{0: resource, 1: int}
     */
    private function listenOnEphemeralPort(): array
    {
        $errno = 0;
        $errstr = '';
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server, 'Не удалось открыть локальный порт: '.$errstr);
        $name = stream_socket_get_name($server, false);
        $this->assertNotFalse($name);
        $port = (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);
        $this->assertGreaterThan(0, $port);

        return [$server, $port];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function simulateOverlayPaint(): array
    {
        $bladePath = resource_path('views/includes/chat/reverb_status.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('function toneClass(kind)');
const end = blade.indexOf('function refreshProcess()');
if (start < 0 || end < 0 || end <= start) {
    throw new Error('overlay paint helpers not found');
}
const src = blade.slice(start, end);

function classList() {
    const s = new Set();
    return {
        values: s,
        remove: function () { Array.prototype.forEach.call(arguments, (c) => s.delete(c)); },
        add: function (c) { s.add(c); }
    };
}

function run(opts) {
    const rootList = classList();
    const root = { classList: rootList };
    const processDot = { classList: classList() };
    const socketDot = { classList: classList() };
    const processText = { textContent: '…', attrs: {} };
    processText.setAttribute = function (k, v) { this.attrs[k] = v; };
    const socketText = { textContent: '…' };
    const copyBtn = { classList: classList(), title: '' };
    copyBtn.setAttribute = function (k, v) { this[k] = v; };
    let listening = !!opts.listening;
    let processMeta = opts.processMeta || '';
    const window = global;
    global.window = window;
    if (opts.echo === false) {
        window.Echo = undefined;
    } else {
        window.Echo = {
            private: function () {},
            connector: {
                pusher: {
                    connection: { state: opts.socket || 'unknown' }
                }
            }
        };
    }
    eval(src);
    paint();
    return {
        process: processText.textContent,
        socket: socketText.textContent,
        root: Array.from(rootList.values),
        process_dot: Array.from(processDot.classList.values),
        socket_dot: Array.from(socketDot.classList.values),
        copy: statusText()
    };
}

const out = {
    down_connecting: run({
        listening: false,
        socket: 'connecting',
        processMeta: '127.0.0.1:6009, reverb'
    }),
    up_connecting: run({ listening: true, socket: 'connecting' }),
    up_connected: run({ listening: true, socket: 'connected' }),
    up_initialized: run({ listening: true, socket: 'initialized' }),
    down_failed: run({ listening: false, socket: 'failed' }),
    no_echo: run({ listening: false, echo: false })
};
process.stdout.write(JSON.stringify(out));
JS;

        $path = sys_get_temp_dir().'/reverb-overlay-paint-'.uniqid('', true).'.cjs';
        file_put_contents($path, $script);

        try {
            $output = [];
            $exitCode = 0;
            exec(
                'node '.escapeshellarg($path).' '.escapeshellarg($bladePath).' 2>&1',
                $output,
                $exitCode
            );
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
