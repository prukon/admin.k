<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Массовая кнопка «Применить» слева на «По месяцам» видна только с setPrices.applyAllTeams.manage.
 *
 * @see SettingPricesApplyAllTeamsAccessFeatureTest
 */
final class SettingPricesApplyAllTeamsMarkupFeatureTest extends CrmTestCase
{
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Феникс Apply',
        ]);
    }

    public function test_monthly_hides_bulk_apply_without_permission_and_keeps_row_apply(): void
    {
        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringNotContainsString('id="set-price-all-teams"', $html);
        $this->assertStringNotContainsString('set-price-all-teams', $html);
        $this->assertStringContainsString('setting-prices-team-ok', $html);
        $this->assertStringContainsString('value="Применить"', $html);
        $this->assertStringContainsString('Феникс Apply', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/monthly.blade.php'));
        $this->assertStringContainsString("@can('setPrices.applyAllTeams.manage')", $blade);
        $this->assertStringContainsString('id="set-price-all-teams"', $blade);
    }

    public function test_monthly_shows_bulk_apply_when_permission_is_granted(): void
    {
        $this->grantPartnerRolePermission($this->user, 'setPrices.applyAllTeams.manage');

        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="set-price-all-teams"', $html);
        $this->assertStringContainsString('set-price-all-teams', $html);
        $this->assertStringContainsString('setting-prices-team-ok', $html);
        $this->assertMatchesRegularExpression(
            '/<button[^>]*id="set-price-all-teams"[^>]*>\s*Применить\s*<\/button>/',
            $html
        );
    }
}
