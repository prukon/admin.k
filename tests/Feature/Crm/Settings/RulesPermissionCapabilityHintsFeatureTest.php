<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Settings;

use App\Support\PermissionCapabilityHint;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Матрица «admin/settings/rules: иконка i и ховер возможностей права.
 */
final class RulesPermissionCapabilityHintsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_dashboard_view_row_includes_tooltip_hint_with_numbered_title(): void
    {
        $this->asAdmin();

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<code>dashboard\\.view<\\/code>\\s*<span[^>]*data-kids-tooltip-hint/s',
            $html
        );

        $title = PermissionCapabilityHint::title('dashboard.view');
        $this->assertNotSame('', $title);
        $this->assertStringContainsString('data-bs-container="body"', $html);
        $this->assertStringContainsString('data-bs-placement="right"', $html);
        $this->assertStringContainsString('fa fa-info-circle', $html);
        $this->assertStringContainsString(e($title), $html);
        $this->assertStringContainsString('partials.ui.tooltip-hint', file_get_contents(
            resource_path('views/admin/setting/rule.blade.php')
        ) ?: '');
    }

    public function test_rule_blade_includes_tooltip_hint_partial(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/setting/rule.blade.php'));

        $this->assertStringContainsString("@include('partials.ui.tooltip-hint'", $blade);
        $this->assertStringContainsString('PermissionCapabilityHint::title', $blade);
        $this->assertStringContainsString("'container' => 'body'", $blade);
    }

    public function test_tooltip_hint_partial_supports_body_container(): void
    {
        $blade = (string) file_get_contents(resource_path('views/partials/ui/tooltip-hint.blade.php'));

        $this->assertStringContainsString('data-bs-container', $blade);
        $this->assertStringContainsString('$hintContainer', $blade);
    }

    public function test_kids_tooltip_hint_init_honors_data_bs_container(): void
    {
        $js = (string) file_get_contents(resource_path('js/kids-tooltip.js'));

        $this->assertStringContainsString('function initHintElement', $js);
        $this->assertStringContainsString("el.getAttribute('data-bs-container')", $js);
        $this->assertStringContainsString('options.container = container', $js);
    }

    public function test_superadmin_sees_hint_on_hidden_permission(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<code>messages\\.threads\\.delete<\\/code>\\s*<span[^>]*data-kids-tooltip-hint/s',
            $html
        );

        $title = PermissionCapabilityHint::title('messages.threads.delete');
        $this->assertNotSame('', $title);
        $this->assertStringContainsString(e($title), $html);
    }

    public function test_empty_catalog_does_not_render_hint_icon(): void
    {
        $rendered = view('partials.ui.tooltip-hint', [
            'title' => '',
            'container' => 'body',
        ])->render();

        $this->assertSame('', trim($rendered));
        $this->assertStringNotContainsString('data-kids-tooltip-hint', $rendered);
    }
}
