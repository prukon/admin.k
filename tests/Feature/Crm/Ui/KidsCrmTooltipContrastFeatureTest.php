<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui;

use Tests\Feature\Crm\Cabinet\SystemMonitorsTestCase;

/**
 * P1: пузырёк KidsCrmTooltip непрозрачный — текст читается поверх оверлея «Пульт».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class KidsCrmTooltipContrastFeatureTest extends SystemMonitorsTestCase
{
    public function test_tooltip_css_forces_opaque_dark_background_and_white_text(): void
    {
        $css = (string) file_get_contents(resource_path('css/kids-tooltip.css'));

        $this->assertStringContainsString('.tooltip.ulp-assignment-paid-tooltip', $css);
        $this->assertStringContainsString('--bs-tooltip-opacity: 1', $css);
        $this->assertStringContainsString('--bs-tooltip-bg: #111827', $css);
        $this->assertStringContainsString('--bs-tooltip-color: #ffffff', $css);
        $this->assertStringContainsString('z-index: 20050', $css);
        $this->assertStringContainsString('background-color: #111827', $css);
        $this->assertStringContainsString('color: #ffffff', $css);
        $this->assertStringNotContainsString('--bs-tooltip-opacity: 0.9', $css);
    }

    public function test_admin_layout_duplicates_opaque_tooltip_without_waiting_for_vite(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/admin2.blade.php'));

        $this->assertStringContainsString('--bs-tooltip-opacity: 1', $layout);
        $this->assertStringContainsString('--bs-tooltip-bg: #111827', $layout);
        $this->assertStringContainsString('z-index: 20050', $layout);
        $this->assertStringContainsString('#system-monitors-stack', $layout);
    }

    public function test_cabinet_html_serves_opaque_tooltip_rules_with_ops_overlay(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="js-ops-monitors"', $html);
        $this->assertStringContainsString('data-kids-tooltip-hint', $html);
        $this->assertStringContainsString('--bs-tooltip-opacity: 1', $html);
        $this->assertStringContainsString('background-color: #111827', $html);
        $this->assertStringContainsString('color: #ffffff', $html);
        $this->assertStringContainsString('z-index: 20050', $html);
    }
}
