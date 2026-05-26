<?php

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\TinkoffCommissionRule;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Страница «Настройки → Комиссии Т‑Банк» (/admin/settings/tbank-commissions):
 * тулбар, модалки, фильтры, DataTables.
 */
final class TbankCommissionsToolbarFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);
    }

    public function test_list_page_renders_toolbar_with_payout_add_and_filters_actions(): void
    {
        $html = $this->get(route('admin.setting.tbankCommissions'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'tbankCommissions')
            ->assertViewHas('mode', 'list')
            ->getContent();

        $this->assertStringContainsString('>Правила комиссий и выплат</h1>', $html);
        $this->assertStringContainsString('payments-report-surface', $html);
        $this->assertStringContainsString('admin-list-toolbar', $html);
        $this->assertStringContainsString('payments-report-toolbar-actions--many', $html);
        $this->assertStringContainsString('payments-report-toolbar-action', $html);

        $this->assertStringContainsString('>Настройки выплат</span>', $html);
        $this->assertStringContainsString('>Добавить комиссию</span>', $html);
        $this->assertStringContainsString('>Фильтры</span>', $html);
        $this->assertStringContainsString('data-bs-target="#tbankPayoutSettingsModal"', $html);
        $this->assertStringContainsString('data-bs-target="#tbankCommissionCreateModal"', $html);
        $this->assertStringContainsString('data-bs-target="#tbankCommissionsFiltersCollapse"', $html);
        $this->assertStringContainsString('fa-gear payments-report-toolbar-icon', $html);
        $this->assertStringContainsString('fa-plus payments-report-toolbar-icon', $html);
        $this->assertStringContainsString('fa-sliders-h payments-report-toolbar-icon', $html);

        $this->assertStringContainsString('id="tbankPayoutSettingsModal"', $html);
        $this->assertStringContainsString('id="tbankCommissionCreateModal"', $html);
        $this->assertStringContainsString('id="tbank-commissions-table"', $html);
        $this->assertStringContainsString('id="tbankCommissionsFiltersCollapse"', $html);
        $this->assertStringContainsString('id="tbank-commissions-filters-form"', $html);
        $this->assertStringContainsString('serverSide: true', $html);

        $this->assertStringNotContainsString('btn btn-primary btn-sm', $html);

        $payoutPos = strpos($html, '>Настройки выплат</span>');
        $addPos = strpos($html, '>Добавить комиссию</span>');
        $filtersPos = strpos($html, '>Фильтры</span>');
        $this->assertNotFalse($payoutPos);
        $this->assertNotFalse($addPos);
        $this->assertNotFalse($filtersPos);
        $this->assertLessThan($addPos, $payoutPos);
        $this->assertLessThan($filtersPos, $addPos);
    }

    public function test_filters_collapse_is_expanded_when_query_filters_are_present(): void
    {
        $html = $this->get(route('admin.setting.tbankCommissions', [
            'filter_partner_id' => $this->partner->id,
            'filter_method' => 'card',
        ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="collapse show mb-2 mb-md-3" id="tbankCommissionsFiltersCollapse"', $html);
        $this->assertStringContainsString('id="tbankCommissionsFiltersToggle"', $html);
        $this->assertStringContainsString('aria-expanded="true"', $html);
    }

    public function test_create_route_redirects_to_index_with_open_create_flag(): void
    {
        $this->get(route('admin.setting.tbankCommissions.create'))
            ->assertRedirect(route('admin.setting.tbankCommissions', ['open_create' => 1]));

        $this->get(route('admin.setting.tbankCommissions', ['open_create' => 1]))
            ->assertOk()
            ->assertSee('fromCreateRoute = true', false)
            ->assertSee('id="tbankCommissionCreateModal"', false);
    }

    public function test_edit_page_does_not_render_list_toolbar(): void
    {
        $rule = TinkoffCommissionRule::create($this->rulePayload());

        $html = $this->get(route('admin.setting.tbankCommissions.edit', ['id' => $rule->id]))
            ->assertOk()
            ->assertViewHas('mode', 'edit')
            ->getContent();

        $this->assertStringNotContainsString('payments-report-toolbar-actions--many', $html);
        $this->assertStringContainsString('Правка правила #' . $rule->id, $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rulePayload(array $overrides = []): array
    {
        return array_merge([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'acquiring_percent' => 2.5,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 1.2,
            'payout_min_fixed' => 0,
            'platform_percent' => 3.0,
            'platform_min_fixed' => 0,
            'min_fixed' => 0,
            'is_enabled' => true,
        ], $overrides);
    }
}
