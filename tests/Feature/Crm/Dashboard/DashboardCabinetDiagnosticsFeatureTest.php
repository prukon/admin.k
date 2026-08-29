<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * На /cabinet нет JSON-оверлея диагностики. Бейдж Reverb — через системные мониторы.
 */
final class DashboardCabinetDiagnosticsFeatureTest extends StudentTeamPivotTestCase
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

    public function test_cabinet_has_no_diagnostics_overlay_even_for_superadmin_when_monitors_on(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewMissing('cabinetDiagnostics')
            ->getContent();

        $this->assertStringNotContainsString('id="cabinet-diagnostics"', $html);
        $this->assertStringNotContainsString('data-cabinet-diagnostics="1"', $html);
        $this->assertStringNotContainsString('id="cabinet-diagnostics-json"', $html);
        $this->assertStringNotContainsString('Диагностика консоли', $html);
        $this->assertStringNotContainsString('refreshCabinetDiagnosticsPanel', $html);
        $this->assertStringContainsString('id="js-reverb-status"', $html);
        $this->assertStringContainsString('id="system-monitors-toggle"', $html);
        $this->assertMatchesRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
    }

    public function test_overlay_hidden_for_admin_without_permission(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="cabinet-diagnostics"', $html);
        $this->assertStringNotContainsString('Диагностика консоли', $html);
        $this->assertStringNotContainsString('id="js-reverb-status"', $html);
        $this->assertStringNotContainsString('id="system-monitors-toggle"', $html);
    }

    public function test_get_user_details_never_includes_cabinet_diagnostics_payload(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $with = $this->getJson(route('getUserDetails', ['userId' => $this->user->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();
        $this->assertArrayNotHasKey('cabinet_diagnostics', $with);

        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $without = $this->getJson(route('getUserDetails', ['userId' => $this->user->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();
        $this->assertArrayNotHasKey('cabinet_diagnostics', $without);
    }

    public function test_guest_cannot_open_cabinet_page(): void
    {
        Auth::logout();

        $response = $this->get(route('dashboard'));
        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
    }
}
