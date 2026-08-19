<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Settings;

use App\Models\Setting;
use App\Support\CabinetDiagnostics;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Settings\Concerns\CabinetDiagnosticsTestHelpers;

/**
 * Native POST без X-Requested-With: флаг сохраняется, не 500 и не пустой 200.
 * Кнопка AJAX-only: контроллер отвечает JSON (не обязательный 302).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingsCabinetDiagnosticsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use CabinetDiagnosticsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperadmin();
        $this->withPartnerSession();
    }

    public function test_native_post_persists_flag_and_is_not_empty_or_server_error(): void
    {
        $response = $this->from($this->settingsUrl())
            ->post($this->toggleUrl(), [
                '_token' => csrf_token(),
                'cabinetDiagnostics' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains(
            $response->getStatusCode(),
            [200, 302],
            'Успех native POST должен сохранить флаг (JSON 200 или redirect 302)'
        );

        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()), 'не пустой 200');
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('value', true);
        }

        if ($response->getStatusCode() === 302) {
            $response->assertRedirect($this->settingsUrl());
        }

        $this->assertDatabaseHas('settings', [
            'name' => CabinetDiagnostics::SETTING,
            'partner_id' => null,
            'status' => 1,
        ]);
        $this->assertTrue(CabinetDiagnostics::isEnabled());
    }

    public function test_native_post_off_clears_flag_without_white_screen(): void
    {
        Setting::setBool(CabinetDiagnostics::SETTING, true, null);

        $response = $this->from($this->settingsUrl())
            ->post($this->toggleUrl(), [
                '_token' => csrf_token(),
                'cabinetDiagnostics' => 0,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);

        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('value', false);
        }

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_native_post_without_value_is_not_success_and_shows_field_error(): void
    {
        $response = $this->from($this->settingsUrl())
            ->post($this->toggleUrl(), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация не должна давать пустой/успешный 200');

        if ($response->getStatusCode() === 302) {
            $response->assertSessionHasErrors(['cabinetDiagnostics']);
        } else {
            $response->assertStatus(422);
            $response->assertJsonPath('errors.cabinetDiagnostics.0', 'Укажите состояние оверлея статуса Reverb.');
            $this->assertNotSame('', trim((string) $response->getContent()));
        }

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_native_invalid_value_returns_field_error_not_500(): void
    {
        $response = $this->from($this->settingsUrl())
            ->post($this->toggleUrl(), [
                '_token' => csrf_token(),
                'cabinetDiagnostics' => 'maybe',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());

        if ($response->getStatusCode() === 302) {
            $response->assertSessionHasErrors(['cabinetDiagnostics']);
        } else {
            $response->assertStatus(422);
            $response->assertJsonPath(
                'errors.cabinetDiagnostics.0',
                'Некорректное значение оверлея статуса Reverb.'
            );
        }

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }
}
