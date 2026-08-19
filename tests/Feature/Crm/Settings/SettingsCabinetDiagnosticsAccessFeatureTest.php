<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Settings;

use App\Support\CabinetDiagnostics;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Settings\Concerns\CabinetDiagnosticsTestHelpers;

/**
 * Доступ к кнопке оверлея Reverb и POST /admin/settings/cabinet-diagnostics:
 * гость, без права, с правом, неверные методы — не 500 и не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingsCabinetDiagnosticsAccessFeatureTest extends CrmTestCase
{
    use CabinetDiagnosticsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withPartnerSession();
    }

    public function test_guest_cannot_open_settings_or_toggle_overlay(): void
    {
        Auth::logout();

        $page = $this->get($this->settingsUrl());
        $this->assertDeniedWithoutServerError($page, 'гость GET /admin/settings');
        $this->assertTrue($page->isRedirect() || in_array($page->getStatusCode(), [401, 403, 419], true));

        $pageJson = $this->getJson($this->settingsUrl());
        $this->assertDeniedWithoutServerError($pageJson, 'гость GET JSON /admin/settings');

        foreach (['web', 'json'] as $mode) {
            $response = $mode === 'json'
                ? $this->postJson($this->toggleUrl(), ['cabinetDiagnostics' => 1], $this->ajaxHeaders())
                : $this->post($this->toggleUrl(), [
                    '_token' => csrf_token(),
                    'cabinetDiagnostics' => 1,
                ]);

            $this->assertDeniedWithoutServerError($response, "гость POST [{$mode}]");
        }

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_guest_wrong_methods_on_toggle_are_not_server_error(): void
    {
        Auth::logout();

        foreach ($this->overlayToggleWrongMethods() as $method) {
            $json = $this->json($method, $this->toggleUrl(), ['cabinetDiagnostics' => 1]);
            $this->assertDeniedWithoutServerError($json, "гость {$method} JSON");

            $html = $this->call($method, $this->toggleUrl(), [
                '_token' => csrf_token(),
                'cabinetDiagnostics' => 1,
            ]);
            $this->assertDeniedWithoutServerError($html, "гость {$method} HTML");
        }
    }

    public function test_manager_without_settings_view_gets_403_on_settings_and_toggle(): void
    {
        $actor = $this->createUserWithoutPermission('settings.view', $this->partner);
        $this->actingAs($actor);
        $this->withPartnerSession($actor);

        $this->get($this->settingsUrl())->assertForbidden();
        $this->getJson($this->settingsUrl())->assertForbidden();

        $this->postJson($this->toggleUrl(), ['cabinetDiagnostics' => 1], $this->ajaxHeaders())
            ->assertForbidden();
        $this->post($this->toggleUrl(), [
            '_token' => csrf_token(),
            'cabinetDiagnostics' => 1,
        ])->assertForbidden();

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_trainer_with_settings_view_cannot_see_button_or_toggle(): void
    {
        $trainer = $this->createUserWithRole('trainer');
        $this->grantPermissionToActor($trainer, 'settings.view');
        $this->actingAs($trainer);
        $this->withPartnerSession($trainer);

        $html = $this->get($this->settingsUrl())->assertOk()->getContent();
        $this->assertStringNotContainsString('id="rowCabinetDiagnostics"', $html);
        $this->assertStringNotContainsString('id="btnCabinetDiagnostics"', $html);
        $this->assertStringNotContainsString('#btnCabinetDiagnostics', $html);

        $this->postJson($this->toggleUrl(), ['cabinetDiagnostics' => 1], $this->ajaxHeaders())
            ->assertForbidden();

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_regular_user_cannot_open_settings_or_toggle_overlay(): void
    {
        $client = $this->createUserWithRole('user');
        $this->actingAs($client);
        $this->withPartnerSession($client);

        $page = $this->get($this->settingsUrl());
        $this->assertNotSame(500, $page->getStatusCode());
        $this->assertContains($page->getStatusCode(), [403, 302]);

        $this->postJson($this->toggleUrl(), ['cabinetDiagnostics' => 1], $this->ajaxHeaders())
            ->assertForbidden();

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_admin_with_settings_view_gets_403_when_toggling_overlay(): void
    {
        $this->asAdmin();
        $this->withPartnerSession();
        $this->grantPermissionToCurrentRole('settings.view');

        $this->get($this->settingsUrl())->assertOk();

        $json = $this->postJson($this->toggleUrl(), ['cabinetDiagnostics' => 1], $this->ajaxHeaders());
        $json->assertForbidden();
        $this->assertNotSame('', trim((string) $json->getContent()));

        $html = $this->post($this->toggleUrl(), [
            '_token' => csrf_token(),
            'cabinetDiagnostics' => 1,
        ]);
        $html->assertForbidden();

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_superadmin_can_open_settings_and_toggle_overlay(): void
    {
        $this->asSuperadmin();
        $this->withPartnerSession();

        $this->get($this->settingsUrl())->assertOk();

        $this->postJson($this->toggleUrl(), ['cabinetDiagnostics' => 1], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('value', true);

        $this->assertTrue(CabinetDiagnostics::isEnabled());
    }

    public function test_admin_granted_overlay_permission_can_see_button_and_toggle(): void
    {
        $this->asAdmin();
        $this->withPartnerSession();
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole(CabinetDiagnostics::PERMISSION);

        $html = $this->get($this->settingsUrl())->assertOk()->getContent();
        $this->assertStringContainsString('id="btnCabinetDiagnostics"', $html);
        $this->assertStringContainsString('id="rowCabinetDiagnostics"', $html);

        $this->postJson($this->toggleUrl(), ['cabinetDiagnostics' => 1], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('value', true);

        $this->assertTrue(CabinetDiagnostics::isEnabled());
    }

    public function test_wrong_http_methods_on_toggle_are_not_empty_200(): void
    {
        $this->asSuperadmin();
        $this->withPartnerSession();

        foreach ($this->overlayToggleWrongMethods() as $method) {
            $json = $this->json($method, $this->toggleUrl(), ['cabinetDiagnostics' => 1], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON не пустой 200');
            $this->assertContains(
                $json->getStatusCode(),
                [404, 405],
                $method.' JSON должен быть 404/405, получено '.$json->getStatusCode()
            );

            $html = $this->call($method, $this->toggleUrl(), [
                '_token' => csrf_token(),
                'cabinetDiagnostics' => 1,
            ]);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertContains(
                $html->getStatusCode(),
                [404, 405],
                $method.' HTML должен быть 404/405, получено '.$html->getStatusCode()
            );
        }

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }
}
