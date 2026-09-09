<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Первый рендер «По месяцам»: плашки нет, правая колонка пустая, loading не в HTML.
 *
 * @see /docs/documentation/setting-prices-monthly-users.html
 */
final class SettingPricesMonthlyTeamSelectMarkupFeatureTest extends CrmTestCase
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
            'title' => 'Феникс Markup',
        ]);
    }

    public function test_monthly_first_open_has_empty_right_column_and_no_active_plaque(): void
    {
        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="'.$this->team->id.'"', $html);
        $this->assertMatchesRegularExpression('/id=[\'"]left_bar[\'"]/', $html);
        $this->assertMatchesRegularExpression('/id=[\'"]right_bar[\'"]/', $html);
        $this->assertStringContainsString('wrap-team setting-prices-team-row', $html);
        $this->assertStringContainsString('Феникс Markup', $html);

        $this->assertStringNotContainsString('wrap-team--active', $html);
        $this->assertStringNotContainsString('wrap-team--loading', $html);
        $this->assertStringNotContainsString('setting-prices-team-loading', $html);
        $this->assertStringNotContainsString('setting-prices-users-placeholder', $html);
        $this->assertStringNotContainsString('setting-prices-user-card', $html);

        $this->assertMatchesRegularExpression(
            '/<button[^>]*\bdisabled\b[^>]*id="set-price-all-users"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/class="row mb-2 wrap-users text-start\s*"><\/div>/',
            $html
        );

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/monthly.blade.php'));
        $this->assertStringContainsString("@vite(['resources/js/settings-prices.js'])", $blade);
        $this->assertStringNotContainsString('wrap-team--active', $blade);
        $this->assertStringNotContainsString('get-team-price', $blade);
    }

    public function test_users_tab_does_not_use_monthly_loading_placeholder(): void
    {
        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('setting-prices-users-placeholder', $html);
        $this->assertStringNotContainsString('wrap-team--loading', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/users.blade.php'));
        $this->assertStringNotContainsString('resources/js/settings-prices.js', $blade);
        $this->assertStringContainsString("row.addClass('wrap-team--active')", $blade);
        $this->assertStringNotContainsString('setTeamRowLoading', $blade);
        $this->assertStringNotContainsString('keepActiveHighlight', $blade);
    }
}
