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
        $this->assertStringContainsString('>Колонки</span>', $html);
        $this->assertStringContainsString('data-bs-target="#tbankPayoutSettingsModal"', $html);
        $this->assertStringContainsString('data-bs-target="#tbankCommissionCreateModal"', $html);
        $this->assertStringContainsString('data-bs-target="#tbankCommissionsFiltersCollapse"', $html);
        $this->assertStringContainsString('fa-gear payments-report-toolbar-icon', $html);
        $this->assertStringContainsString('fa-plus payments-report-toolbar-icon', $html);
        $this->assertStringContainsString('fa-sliders-h payments-report-toolbar-icon', $html);
        $this->assertStringContainsString('fa-table-columns payments-report-toolbar-icon', $html);
        $this->assertStringContainsString('id="tbankCommissionsColumnsDropdown"', $html);
        $this->assertStringContainsString('payments-report-columns-menu', $html);

        $this->assertStringContainsString('id="tbankPayoutSettingsModal"', $html);
        $this->assertStringContainsString('id="tbankCommissionCreateModal"', $html);
        $this->assertStringContainsString('id="tbankCommissionEditModal"', $html);
        $this->assertStringContainsString("linkClass: 'js-tbank-commission-edit'", $html);
        $this->assertStringContainsString("'.js-tbank-commission-edit'", $html);
        $this->assertStringContainsString('id="tbank-commissions-table"', $html);
        $this->assertStringContainsString('KidsCrmDataTable.create', $html);
        $this->assertStringContainsString('id="tbankCommissionsFiltersCollapse"', $html);
        $this->assertStringContainsString('id="tbank-commissions-filters-form"', $html);

        $this->assertStringNotContainsString('btn btn-primary btn-sm', $html);

        $payoutPos = strpos($html, '>Настройки выплат</span>');
        $addPos = strpos($html, '>Добавить комиссию</span>');
        $filtersPos = strpos($html, '>Фильтры</span>');
        $columnsPos = strpos($html, '>Колонки</span>');
        $this->assertNotFalse($payoutPos);
        $this->assertNotFalse($addPos);
        $this->assertNotFalse($filtersPos);
        $this->assertNotFalse($columnsPos);
        $this->assertLessThan($addPos, $payoutPos);
        $this->assertLessThan($filtersPos, $addPos);
        $this->assertLessThan($columnsPos, $filtersPos);
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

    public function test_edit_route_redirects_to_index_with_edit_flag(): void
    {
        $rule = TinkoffCommissionRule::create($this->rulePayload());

        $this->get(route('admin.setting.tbankCommissions.edit', ['id' => $rule->id]))
            ->assertRedirect(route('admin.setting.tbankCommissions', ['edit' => $rule->id]));

        $html = $this->get(route('admin.setting.tbankCommissions', ['edit' => $rule->id]))
            ->assertOk()
            ->assertViewHas('mode', 'list')
            ->getContent();

        $this->assertStringContainsString('payments-report-toolbar-actions--many', $html);
        $this->assertStringContainsString('id="tbankCommissionEditModal"', $html);
        $this->assertStringContainsString('#tbankCommissionEditModal .modal-dialog', $html);
        $this->assertStringContainsString('max-width: min(720px, 96vw)', $html);
        $editModalPos = strpos($html, 'id="tbankCommissionEditModal" tabindex');
        $this->assertNotFalse($editModalPos);
        $editDialogChunk = substr($html, $editModalPos, 400);
        $this->assertStringContainsString('class="modal-dialog"', $editDialogChunk);
        $this->assertStringNotContainsString('modal-lg', $editDialogChunk);
        $this->assertStringContainsString('fromEditRoute =', $html);
        $this->assertStringContainsString('Редактирование правила комиссии', $html);
        $this->assertStringContainsString('id="tbank_edit_partner_title"', $html);
        $this->assertStringContainsString('id="tbank_edit_method_label"', $html);

        $editModalHtml = substr($html, $editModalPos);
        $createModalPos = strpos($html, 'id="tbankCommissionCreateModal"');
        $this->assertNotFalse($createModalPos);
        $createModalHtml = substr($html, $createModalPos, $editModalPos > $createModalPos ? $editModalPos - $createModalPos : 8000);
        $this->assertStringContainsString('Партнёр (опционально)', $createModalHtml);
        $this->assertStringNotContainsString('Партнёр (опционально)', $editModalHtml);
        $this->assertStringNotContainsString('<select name="partner_id"', $editModalHtml);
        $this->assertStringNotContainsString('<select name="method"', $editModalHtml);
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
