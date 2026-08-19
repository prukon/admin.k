<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Settings;

use App\Models\Setting;
use App\Support\CabinetDiagnostics;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Settings\Concerns\CabinetDiagnosticsTestHelpers;

/**
 * AJAX JSON-контракт кнопки «Оверлей статуса Reverb»: 200/422 errors.cabinetDiagnostics,
 * «on»/«1» принимаются, партнёрская строка флаг не включает.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingsCabinetDiagnosticsAjaxContractFeatureTest extends CrmTestCase
{
    use CabinetDiagnosticsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperadmin();
        $this->withPartnerSession();
    }

    public function test_ajax_toggle_on_returns_success_json_and_persists_global_flag(): void
    {
        $response = $this->postJson($this->toggleUrl(), [
            'cabinetDiagnostics' => 1,
        ], $this->ajaxHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('value', true);

        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('errors', $payload);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseHas('settings', [
            'name' => CabinetDiagnostics::SETTING,
            'partner_id' => null,
            'status' => 1,
        ]);
        $this->assertTrue(CabinetDiagnostics::isEnabled());
    }

    public function test_ajax_toggle_off_returns_success_json_and_clears_flag(): void
    {
        Setting::setBool(CabinetDiagnostics::SETTING, true, null);

        $this->postJson($this->toggleUrl(), [
            'cabinetDiagnostics' => 0,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('value', false);

        $this->assertDatabaseHas('settings', [
            'name' => CabinetDiagnostics::SETTING,
            'partner_id' => null,
            'status' => 0,
        ]);
        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_ajax_missing_value_returns_422_under_cabinet_diagnostics_field(): void
    {
        $this->postJson($this->toggleUrl(), [], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.cabinetDiagnostics.0', 'Укажите состояние оверлея статуса Reverb.');

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_ajax_invalid_value_returns_422_under_cabinet_diagnostics_field(): void
    {
        $this->postJson($this->toggleUrl(), [
            'cabinetDiagnostics' => 'maybe',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.cabinetDiagnostics.0', 'Некорректное значение оверлея статуса Reverb.');

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_ajax_on_and_one_are_accepted_as_enabled(): void
    {
        $this->postJson($this->toggleUrl(), [
            'cabinetDiagnostics' => 'on',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('value', true);

        $this->assertTrue(CabinetDiagnostics::isEnabled());

        Setting::setBool(CabinetDiagnostics::SETTING, false, null);

        $this->postJson($this->toggleUrl(), [
            'cabinetDiagnostics' => '1',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('value', true);

        $this->assertTrue(CabinetDiagnostics::isEnabled());
    }

    public function test_partner_scoped_flag_does_not_enable_global_overlay(): void
    {
        Setting::setBool(CabinetDiagnostics::SETTING, true, $this->partner->id);

        $this->assertFalse(CabinetDiagnostics::isEnabled());

        $html = $this->get($this->settingsUrl())->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-reverb-status"', $html);
        $this->assertSame('выключен', $this->cabinetDiagnosticsLabelText($html));
        preg_match('/<input[^>]*id="cabinetDiagnostics"[^>]*>/', $html, $match);
        $this->assertNotEmpty($match);
        $this->assertStringNotContainsString('checked', $match[0]);
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
}
