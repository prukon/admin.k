<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Settings;

use App\Models\Setting;
use App\Support\CabinetDiagnostics;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Settings\Concerns\CabinetDiagnosticsTestHelpers;

/**
 * Разметка кнопки и бейджа Reverb: первый paint чекбокса, оверлей на /admin/settings
 * (layout-fixed), @can на строке и обработчике, JS: откат чекбокса и 403 без английского message.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingsCabinetDiagnosticsUiContractsFeatureTest extends CrmTestCase
{
    use CabinetDiagnosticsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withPartnerSession();
    }

    public function test_checkbox_starts_unchecked_and_label_off_when_flag_is_off(): void
    {
        Setting::setBool(CabinetDiagnostics::SETTING, false, null);

        $this->asSuperadmin();
        $this->withPartnerSession();

        $html = $this->get($this->settingsUrl())
            ->assertOk()
            ->assertViewHas('cabinetDiagnosticsEnabled', false)
            ->getContent();

        $input = $this->cabinetDiagnosticsInputTag($html);
        $this->assertStringNotContainsString('checked', $input);
        $this->assertSame('выключен', $this->cabinetDiagnosticsLabelText($html));
    }

    public function test_checkbox_starts_checked_and_label_on_when_flag_is_on(): void
    {
        Setting::setBool(CabinetDiagnostics::SETTING, true, null);

        $this->asSuperadmin();
        $this->withPartnerSession();

        $html = $this->get($this->settingsUrl())
            ->assertOk()
            ->assertViewHas('cabinetDiagnosticsEnabled', true)
            ->getContent();

        $input = $this->cabinetDiagnosticsInputTag($html);
        $this->assertStringContainsString('checked', $input);
        $this->assertSame('включён', $this->cabinetDiagnosticsLabelText($html));
    }

    public function test_overlay_is_absent_on_settings_page_when_flag_is_off(): void
    {
        Setting::setBool(CabinetDiagnostics::SETTING, false, null);

        $this->asSuperadmin();
        $this->withPartnerSession();

        $html = $this->get($this->settingsUrl())->assertOk()->getContent();
        $this->assertStringContainsString('sidebar-mini layout-fixed', $html);
        $this->assertStringContainsString('id="btnCabinetDiagnostics"', $html);
        $this->assertStringNotContainsString('id="js-reverb-status"', $html);
        $this->assertStringNotContainsString('data-role="process-dot"', $html);
    }

    public function test_overlay_appears_on_settings_page_when_flag_is_on(): void
    {
        Setting::setBool(CabinetDiagnostics::SETTING, true, null);

        $this->asSuperadmin();
        $this->withPartnerSession();

        $html = $this->get($this->settingsUrl())->assertOk()->getContent();
        $this->assertStringContainsString('sidebar-mini layout-fixed', $html);
        $this->assertStringContainsString('id="js-reverb-status"', $html);
        $this->assertStringContainsString('data-status-url="'.route('chat.api.reverb-status').'"', $html);
        $this->assertStringContainsString('id="btnCabinetDiagnostics"', $html);
    }

    public function test_toggling_off_removes_overlay_from_settings_and_cabinet(): void
    {
        $this->asSuperadmin();
        $this->withPartnerSession();

        $this->postJson($this->toggleUrl(), ['cabinetDiagnostics' => 1], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('value', true);

        $settingsOn = $this->get($this->settingsUrl())->assertOk()->getContent();
        $cabinetOn = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('id="js-reverb-status"', $settingsOn);
        $this->assertStringContainsString('id="js-reverb-status"', $cabinetOn);
        $this->assertStringNotContainsString('id="cabinet-diagnostics"', $cabinetOn);

        $this->postJson($this->toggleUrl(), ['cabinetDiagnostics' => 0], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('value', false);

        $settingsOff = $this->get($this->settingsUrl())->assertOk()->getContent();
        $cabinetOff = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('id="btnCabinetDiagnostics"', $settingsOff);
        $this->assertStringNotContainsString('id="js-reverb-status"', $settingsOff);
        $this->assertStringNotContainsString('id="js-reverb-status"', $cabinetOff);
        $this->assertStringNotContainsString('id="cabinet-diagnostics"', $cabinetOff);
    }

    public function test_admin_and_trainer_do_not_get_overlay_when_flag_is_on(): void
    {
        Setting::setBool(CabinetDiagnostics::SETTING, true, null);

        $this->asAdmin();
        $this->withPartnerSession();
        $this->grantPermissionToCurrentRole('settings.view');

        $adminSettings = $this->get($this->settingsUrl())->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-reverb-status"', $adminSettings);
        $this->assertStringNotContainsString('id="rowCabinetDiagnostics"', $adminSettings);
        $this->assertStringNotContainsString('#btnCabinetDiagnostics', $adminSettings);

        $adminCabinet = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-reverb-status"', $adminCabinet);

        $trainer = $this->createUserWithRole('trainer');
        $this->grantPermissionToActor($trainer, 'settings.view');
        $this->actingAs($trainer);
        $this->withPartnerSession($trainer);

        $trainerSettings = $this->get($this->settingsUrl())->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-reverb-status"', $trainerSettings);
        $this->assertStringNotContainsString('id="btnCabinetDiagnostics"', $trainerSettings);
    }

    public function test_click_handler_is_gated_by_same_can_as_the_row(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/setting/setting.blade.php'));

        $this->assertSame(
            2,
            substr_count($blade, "@can('settings.reverbOverlay.manage')"),
            'Строка кнопки и click-handler должны быть за одним @can'
        );
        $this->assertSame(1, substr_count($blade, "click', '#btnCabinetDiagnostics'"));
        $this->assertStringNotContainsString('canManageCabinetDiagnostics', $blade);
        $this->assertStringNotContainsString("@can('settings.cabinetDiagnostics.manage')", $blade);

        $rowPos = strpos($blade, 'id="rowCabinetDiagnostics"');
        $handlerPos = strpos($blade, "click', '#btnCabinetDiagnostics'");
        $this->assertNotFalse($rowPos);
        $this->assertNotFalse($handlerPos);

        $this->assertTrue(
            str_contains(substr($blade, max(0, $rowPos - 250), 250), "@can('settings.reverbOverlay.manage')"),
            'Строка кнопки должна быть внутри @can'
        );
        $this->assertTrue(
            str_contains(substr($blade, max(0, $handlerPos - 250), 250), "@can('settings.reverbOverlay.manage')"),
            'Click-handler должен быть внутри своего @can, иначе admin получит мёртвый JS'
        );
    }

    public function test_ajax_error_reverts_checkbox_and_shows_russian_403_not_laravel_english(): void
    {
        $result = $this->simulateToggleAjax([
            'checked' => true,
            'branch' => 'error',
            'xhr' => [
                'status' => 403,
                'responseJSON' => ['message' => 'This action is unauthorized.'],
            ],
        ]);

        $this->assertFalse($result['checked'], 'При 403 чекбокс должен откатиться');
        $this->assertSame(
            'Оверлей статуса Reverb доступен только суперадмину.',
            $result['error']
        );
        $this->assertStringNotContainsString('This action is unauthorized', $result['error']);
        $this->assertStringNotContainsString('unauthorized', $result['error']);
    }

    public function test_ajax_422_puts_field_error_under_checkbox(): void
    {
        $result = $this->simulateToggleAjax([
            'checked' => true,
            'branch' => 'error',
            'xhr' => [
                'status' => 422,
                'responseJSON' => [
                    'errors' => [
                        'cabinetDiagnostics' => ['Укажите состояние оверлея статуса Reverb.'],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($result['checked']);
        $this->assertSame('Укажите состояние оверлея статуса Reverb.', $result['error']);
    }

    public function test_success_sets_masculine_labels_and_does_not_revert_checkbox(): void
    {
        $on = $this->simulateToggleAjax([
            'checked' => true,
            'branch' => 'success',
            'resp' => ['success' => true, 'value' => true],
        ]);
        $this->assertTrue($on['checked'], 'Успех не должен сбрасывать чекбокс');
        $this->assertSame('включён', $on['label']);
        $this->assertSame('', $on['error']);
        $this->assertSame(1, $on['sent']);

        $off = $this->simulateToggleAjax([
            'checked' => false,
            'branch' => 'success',
            'resp' => ['success' => true, 'value' => false],
        ]);
        $this->assertFalse($off['checked']);
        $this->assertSame('выключен', $off['label']);
        $this->assertSame(0, $off['sent']);
        $this->assertStringNotContainsString('включена', $off['label']);
        $this->assertStringNotContainsString('выключена', $off['label']);
    }

    public function test_click_clears_previous_field_error_before_request(): void
    {
        $result = $this->simulateToggleAjax([
            'checked' => true,
            'staleError' => 'старая ошибка',
            'branch' => 'inspect',
        ]);

        $this->assertSame('', $result['error_before_ajax']);
        $this->assertSame('POST', $result['method']);
        $this->assertSame('XMLHttpRequest', $result['requested_with']);
        $this->assertArrayHasKey('X-CSRF-TOKEN', $result['headers']);
    }

    private function cabinetDiagnosticsInputTag(string $html): string
    {
        $this->assertSame(1, preg_match('/<input[^>]*id="cabinetDiagnostics"[^>]*>/', $html, $match));

        return $match[0];
    }

    private function cabinetDiagnosticsLabelText(string $html): string
    {
        $this->assertSame(1, preg_match(
            '/id="cabinetDiagnosticsLabel"[^>]*>\s*([^<]+)\s*</u',
            $html,
            $match
        ));

        return trim($match[1]);
    }

    /**
     * @param  array{checked: bool, branch: string, xhr?: array<string, mixed>, resp?: array<string, mixed>, staleError?: string}  $opts
     * @return array<string, mixed>
     */
    private function simulateToggleAjax(array $opts): array
    {
        $bladePath = resource_path('views/admin/setting/setting.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const opts = JSON.parse(process.argv[3]);
const start = blade.indexOf("$(document).on('click', '#btnCabinetDiagnostics'");
const end = blade.indexOf('@endcan', start);
if (start < 0 || end < 0) {
    throw new Error('btnCabinetDiagnostics handler not found');
}
const src = blade.slice(start, end)
    .replace(/'\{\{[\s\S]*?\}\}'/g, "'/admin/settings/cabinet-diagnostics'")
    .replace(/\{\{[\s\S]*?\}\}/g, "'/admin/settings/cabinet-diagnostics'");
const vm = {
    checked: !!opts.checked,
    label: opts.checked ? 'включён' : 'выключен',
    error: opts.staleError || '',
    ajax: null,
    click: null
};
global.document = {};
global.alert = function () {};
global.setTimeout = function (fn) { return 0; };
function $(sel) {
    if (sel === document) {
        return {
            on: function (_ev, _selector, fn) {
                vm.click = fn;
                return this;
            }
        };
    }
    const map = {
        '#rowCabinetDiagnostics': {
            addClass: function () { return this; },
            removeClass: function () { return this; }
        },
        '#cabinetDiagnostics': {
            is: function (q) { return q === ':checked' ? vm.checked : false; },
            prop: function (k, v) {
                if (k === 'checked') {
                    vm.checked = !!v;
                }
                return this;
            }
        },
        '#cabinetDiagnosticsLabel': {
            text: function (t) {
                if (t === undefined) {
                    return vm.label;
                }
                vm.label = String(t);
                return this;
            }
        },
        '#cabinetDiagnosticsError': {
            text: function (t) {
                if (t === undefined) {
                    return vm.error;
                }
                vm.error = String(t);
                return this;
            }
        }
    };
    if (!map[sel]) {
        throw new Error('unexpected selector ' + sel);
    }
    return map[sel];
}
$.ajax = function (cfg) { vm.ajax = cfg; };
const token = 'test-csrf';
eval(src);
if (typeof vm.click !== 'function') {
    throw new Error('click handler was not registered');
}
vm.click();
const snapshot = {
    error_before_ajax: vm.error,
    sent: vm.ajax && vm.ajax.data ? vm.ajax.data.cabinetDiagnostics : null,
    method: vm.ajax ? vm.ajax.method : null,
    requested_with: vm.ajax && vm.ajax.headers ? vm.ajax.headers['X-Requested-With'] : null,
    headers: vm.ajax ? vm.ajax.headers : {}
};
if (opts.branch === 'error') {
    vm.ajax.error(opts.xhr);
} else if (opts.branch === 'success') {
    vm.ajax.success(opts.resp);
}
process.stdout.write(JSON.stringify({
    checked: vm.checked,
    label: vm.label,
    error: vm.error,
    sent: snapshot.sent,
    method: snapshot.method,
    requested_with: snapshot.requested_with,
    headers: snapshot.headers,
    error_before_ajax: snapshot.error_before_ajax
}));
JS;

        $path = sys_get_temp_dir().'/cabinet-diagnostics-ajax-'.uniqid('', true).'.cjs';
        file_put_contents($path, $script);

        try {
            $output = [];
            $exitCode = 0;
            exec(
                'node '.escapeshellarg($path).' '.escapeshellarg($bladePath).' '.escapeshellarg(json_encode($opts)).' 2>&1',
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
