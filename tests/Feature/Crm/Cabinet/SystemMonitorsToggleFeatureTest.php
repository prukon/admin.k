<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\User;
use App\Support\SystemMonitors;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Переключатель системных мониторов: users.system_monitors, скрытое право,
 * оверлей Reverb в admin2, кнопка на /admin/settings снята.
 */
final class SystemMonitorsToggleFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function toggleUrl(): string
    {
        return route('cabinet.system-monitors.update');
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    private function grantPermissionToActor(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $actor->partner_id ?? $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId($permissionName),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $actor->unsetRelation('role');
    }

    public function test_legacy_cabinet_diagnostics_route_is_gone(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('settings.cabinetDiagnostics'));
    }

    public function test_toggle_route_is_protected_by_system_monitors_permission(): void
    {
        $route = Route::getRoutes()->getByName('cabinet.system-monitors.update');
        $this->assertNotNull($route);
        $this->assertTrue(
            in_array('can:settings.systemMonitors.view', $route->gatherMiddleware(), true)
        );
        $this->assertFalse(
            in_array('can:settings.reverbOverlay.manage', $route->gatherMiddleware(), true)
        );
    }

    public function test_guest_is_redirected_from_system_monitors_update(): void
    {
        Auth::logout();

        $response = $this->postJson($this->toggleUrl(), [
            'system_monitors' => 1,
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 401,
            'гость не должен сохранять системные мониторы'
        );
    }

    public function test_admin_without_permission_cannot_toggle_and_does_not_see_switch(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders())
            ->assertForbidden();

        $this->assertFalse((bool) $this->user->fresh()->system_monitors);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="system-monitors-toggle"', $html);
        $this->assertStringNotContainsString('id="js-reverb-status"', $html);
        $this->assertStringNotContainsString('id="rowCabinetDiagnostics"', $html);
        $this->assertStringNotContainsString('id="btnCabinetDiagnostics"', $html);
    }

    public function test_superadmin_sees_toggle_off_by_default_and_overlay_hidden(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="system-monitors-toggle"', $html);
        $this->assertStringContainsString('role="switch"', $html);
        $this->assertStringContainsString('ios-switch', $html);
        $this->assertStringContainsString(route('cabinet.system-monitors.update', [], false), $html);
        $this->assertStringContainsString('errors.system_monitors', $html);
        $this->assertStringContainsString('id="js-reverb-status"', $html);
        $this->assertStringContainsString('system-monitor', $html);
        $this->assertStringNotContainsString('id="rowCabinetDiagnostics"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<body[^>]*\bsystem-monitors-on\b/',
            $html,
            'по умолчанию body не должен иметь system-monitors-on'
        );
    }

    public function test_superadmin_can_enable_and_disable_own_system_monitors(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('system_monitors', true);

        $this->assertTrue((bool) $this->user->fresh()->system_monitors);

        $htmlOn = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $htmlOn);
        $this->assertStringContainsString('id="js-reverb-status"', $htmlOn);
        $this->assertStringContainsString('data-status-url="'.route('chat.api.reverb-status').'"', $htmlOn);

        $this->postJson($this->toggleUrl(), ['system_monitors' => 0], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('system_monitors', false);

        $this->assertFalse((bool) $this->user->fresh()->system_monitors);
    }

    public function test_validation_error_is_returned_under_system_monitors_field(): void
    {
        $this->asSuperadmin();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), [], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['system_monitors'])
            ->assertJsonPath('errors.system_monitors.0', 'Укажите состояние системных мониторов.');

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 'maybe'], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['system_monitors'])
            ->assertJsonPath('errors.system_monitors.0', 'Некорректное значение системных мониторов.');
    }

    public function test_update_does_not_change_another_users_system_monitors(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();
        $this->foreignUser->forceFill(['system_monitors' => false])->save();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders())
            ->assertOk();

        $this->assertTrue((bool) $this->user->fresh()->system_monitors);
        $this->assertFalse((bool) $this->foreignUser->fresh()->system_monitors);
    }

    public function test_granted_hidden_permission_opens_toggle_for_admin(): void
    {
        $this->asAdmin();
        $this->grantPermissionToActor($this->user, 'settings.view');
        $this->grantPermissionToActor($this->user, SystemMonitors::PERMISSION);
        $this->user->forceFill(['system_monitors' => false])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="system-monitors-toggle"', $html);

        $this->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('system_monitors', true);

        $this->assertTrue((bool) $this->user->fresh()->system_monitors);
    }

    public function test_native_post_saves_flag_without_empty_200(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->post($this->toggleUrl(), [
                'system_monitors' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'нативный POST не должен отдавать сырой JSON 200');
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status');
        $this->assertTrue((bool) $this->user->fresh()->system_monitors);
    }

    public function test_wrong_methods_are_not_silent_200(): void
    {
        $this->asSuperadmin();

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON не успешный 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON');
        }
    }

    public function test_settings_page_no_longer_has_reverb_overlay_button(): void
    {
        $this->asSuperadmin();
        $this->grantPermissionToActor($this->user, 'settings.view');

        $html = $this->actingAs($this->user)
            ->get(route('admin.setting.setting'))
            ->assertOk()
            ->assertViewMissing('cabinetDiagnosticsEnabled')
            ->getContent();

        $this->assertStringNotContainsString('id="rowCabinetDiagnostics"', $html);
        $this->assertStringNotContainsString('id="btnCabinetDiagnostics"', $html);
        $this->assertStringNotContainsString('id="cabinetDiagnosticsError"', $html);
        $this->assertStringContainsString('id="system-monitors-toggle"', $html);
    }

    public function test_landing_does_not_render_system_monitors_toggle(): void
    {
        Auth::logout();

        $html = $this->get(route('landing.home'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('id="system-monitors-toggle"', $html);
        $this->assertStringNotContainsString('cabinet.system-monitors.update', $html);
    }
}
