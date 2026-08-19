<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

use App\Models\Setting;
use App\Support\CabinetDiagnostics;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * На /cabinet нет JSON-оверлея диагностики: флаг cabinet_diagnostics только для бейджа Reverb.
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

    private function enableDiagnosticsSetting(): void
    {
        Setting::setBool(CabinetDiagnostics::SETTING, true, null);
    }

    public function test_cabinet_has_no_diagnostics_overlay_even_for_superadmin_when_setting_on(): void
    {
        $this->enableDiagnosticsSetting();
        $this->asSuperadmin();
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
    }

    public function test_overlay_hidden_for_admin_when_setting_on(): void
    {
        $this->enableDiagnosticsSetting();
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="cabinet-diagnostics"', $html);
        $this->assertStringNotContainsString('Диагностика консоли', $html);
    }

    public function test_get_user_details_never_includes_cabinet_diagnostics_payload(): void
    {
        $this->enableDiagnosticsSetting();
        $this->asSuperadmin();
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
        $this->enableDiagnosticsSetting();
        Auth::logout();

        $response = $this->get(route('dashboard'));
        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
    }
}
