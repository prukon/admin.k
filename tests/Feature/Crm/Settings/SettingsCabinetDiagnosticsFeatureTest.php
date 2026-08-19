<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Settings;

use App\Models\Setting;
use App\Support\CabinetDiagnostics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Кнопка оверлея Reverb на /admin/settings: settings.reverbOverlay.manage, глобальный флаг.
 */
final class SettingsCabinetDiagnosticsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermissionToCurrentRole(string $permissionName): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permissionName),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $this->user->unsetRelation('role');
    }

    public function test_toggle_route_is_protected_by_reverb_overlay_permission_not_legacy_name(): void
    {
        $route = Route::getRoutes()->getByName('settings.cabinetDiagnostics');
        $this->assertNotNull($route);
        $this->assertTrue(
            in_array('can:settings.view', $route->gatherMiddleware(), true)
        );
        $this->assertTrue(
            in_array('can:settings.reverbOverlay.manage', $route->gatherMiddleware(), true)
        );
        $this->assertFalse(
            in_array('can:settings.cabinetDiagnostics.manage', $route->gatherMiddleware(), true)
        );
    }

    public function test_settings_page_hides_button_for_admin_and_shows_for_superadmin(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermissionToCurrentRole('settings.view');

        $htmlAdmin = $this->get(route('admin.setting.setting'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="rowCabinetDiagnostics"', $htmlAdmin);
        $this->assertStringNotContainsString('id="btnCabinetDiagnostics"', $htmlAdmin);

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $htmlSuper = $this->get(route('admin.setting.setting'))
            ->assertOk()
            ->assertViewHas('cabinetDiagnosticsEnabled', false)
            ->getContent();

        $this->assertStringContainsString('id="rowCabinetDiagnostics"', $htmlSuper);
        $this->assertStringContainsString('id="btnCabinetDiagnostics"', $htmlSuper);
        $this->assertStringContainsString('data-error-for="cabinetDiagnostics"', $htmlSuper);
        $this->assertStringContainsString('Оверлей статуса Reverb', $htmlSuper);
        $this->assertStringNotContainsString('Диагностика консоли', $htmlSuper);
    }

    public function test_toggle_forbidden_for_admin_even_with_settings_view(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermissionToCurrentRole('settings.view');

        $this->postJson(route('settings.cabinetDiagnostics'), ['cabinetDiagnostics' => 1])
            ->assertForbidden();

        $this->assertFalse(CabinetDiagnostics::isEnabled());
    }

    public function test_superadmin_toggle_updates_global_setting_and_returns_field_error_on_422(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('settings.cabinetDiagnostics'), [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.cabinetDiagnostics.0', 'Укажите состояние оверлея статуса Reverb.');

        $resp = $this->postJson(route('settings.cabinetDiagnostics'), ['cabinetDiagnostics' => 1]);
        $resp->assertOk()->assertJsonPath('success', true)->assertJsonPath('value', true);

        $this->assertDatabaseHas('settings', [
            'name' => CabinetDiagnostics::SETTING,
            'partner_id' => null,
            'status' => 1,
        ]);
        $this->assertTrue(Setting::getBool(CabinetDiagnostics::SETTING, false, null));

        $respOff = $this->postJson(route('settings.cabinetDiagnostics'), ['cabinetDiagnostics' => 0]);
        $respOff->assertOk()->assertJsonPath('success', true)->assertJsonPath('value', false);

        $this->assertDatabaseHas('settings', [
            'name' => CabinetDiagnostics::SETTING,
            'partner_id' => null,
            'status' => 0,
        ]);
    }

    public function test_guest_cannot_toggle_diagnostics(): void
    {
        auth()->logout();

        $response = $this->postJson(route('settings.cabinetDiagnostics'), ['cabinetDiagnostics' => 1]);
        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
    }
}
