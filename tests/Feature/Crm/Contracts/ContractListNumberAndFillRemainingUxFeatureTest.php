<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\UserTableSetting;

/**
 * UX: колонка номера после «№», дефолт сортировки по id не съезжает,
 * вторая строка срока не рисуется без остатка, повтор create=1 не сбрасывает колонки.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractListNumberAndFillRemainingUxFeatureTest extends ContractListNumberAndFillRemainingTestCase
{
    public function test_first_open_renders_number_column_after_rownum_and_sorts_by_id(): void
    {
        $this->actingAsContractsViewer();

        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<th>№<\/th>\s*<th>Номер договора<\/th>\s*<th>Имя<\/th>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-column-key="contract_number"[^>]*>[\s\S]*data-column-key="user_name"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/class="form-check-input column-toggle"[^>]*data-column-key="contract_number"[^>]*checked/',
            $html
        );
        $this->assertStringContainsString('for="colContractNumber">Номер договора</label>', $html);
        $this->assertStringContainsString('contract_number: true', $html);
        $this->assertStringContainsString("key: 'contract_number'", $html);
        $this->assertStringContainsString("data: 'id'", $html);
        $this->assertStringContainsString("order: [[1, 'desc']]", $html);
        $this->assertStringNotContainsString("order: [[8, 'desc']]", $html);
        $this->assertStringNotContainsString("order: [[9, 'desc']]", $html);
        $this->assertStringContainsString('placeholder="Имя, телефон, email, номер"', $html);
        $this->assertStringNotContainsString('<th>Срок подписания</th>', $html);
    }

    public function test_first_open_with_fill_expires_permission_keeps_default_sort_on_number_column(): void
    {
        $this->actingAsFillExpiresViewer();

        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<th>№<\/th>\s*<th>Номер договора<\/th>\s*<th>Имя<\/th>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<th>Обновлён<\/th>\s*<th>Срок подписания<\/th>\s*<th>Действия<\/th>/',
            $html
        );
        $this->assertStringContainsString("order: [[1, 'desc']]", $html);
        $this->assertStringNotContainsString("order: [[10, 'desc']]", $html);
        $this->assertStringNotContainsString("order: [[9, 'desc']]", $html);
        $this->assertStringNotContainsString("order: [[8, 'desc']]", $html);
        $this->assertStringContainsString('when: canSeeFillExpiresAt', $html);
        $this->assertStringContainsString('row.fill_expires_remaining', $html);
    }

    public function test_reopening_create_modal_does_not_drop_number_column_or_id_sort(): void
    {
        $this->actingAsContractsViewer();

        $html = $this->get(route('contracts.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('id="createContractModal"', false)
            ->getContent();

        $this->assertStringContainsString('<th>Номер договора</th>', $html);
        $this->assertStringContainsString('data-column-key="contract_number"', $html);
        $this->assertStringContainsString("key: 'contract_number'", $html);
        $this->assertStringContainsString("data: 'id'", $html);
        $this->assertStringContainsString("order: [[1, 'desc']]", $html);
        $this->assertStringContainsString('contract_number: true', $html);
    }

    public function test_remaining_days_cell_skips_second_line_when_empty_and_does_not_use_cabinet_wording(): void
    {
        $this->actingAsFillExpiresViewer();

        $html = $this->get(route('contracts.index'))->assertOk()->getContent();

        $numberPos = strpos($html, "key: 'contract_number'");
        $namePos = strpos($html, "key: 'user_name'");
        $fillPos = strpos($html, "key: 'fill_expires_at'");
        $this->assertNotFalse($numberPos);
        $this->assertNotFalse($namePos);
        $this->assertNotFalse($fillPos);
        $this->assertGreaterThan($numberPos, $namePos);
        $this->assertGreaterThan($namePos, $fillPos);

        $numberChunk = substr($html, $numberPos, 180);
        $this->assertStringContainsString("type: 'text'", $numberChunk);
        $this->assertStringContainsString("data: 'id'", $numberChunk);
        $this->assertStringNotContainsString("type: 'rownum'", $numberChunk);
        $this->assertStringNotContainsString('orderable: false', $numberChunk);

        $renderStart = strpos($html, "key: 'fill_expires_at'");
        $this->assertNotFalse($renderStart);
        $render = substr($html, $renderStart, 2200);

        $this->assertStringContainsString("if (type !== 'display')", $render);
        $this->assertStringContainsString('return data || \'\'', $render);
        $this->assertStringContainsString("const remaining = escapeHtml(row.fill_expires_remaining || '')", $render);
        $this->assertStringContainsString('if (!remaining)', $render);
        $this->assertStringContainsString('return date;', $render);
        $this->assertStringContainsString("row.fill_expires_remaining_warn ? ' text-danger' : ' text-muted'", $render);
        $this->assertStringContainsString("date + '<div class=\"small' + warnClass + '\">'", $render);
        $this->assertStringNotContainsString('Осталось', $render);
        $this->assertStringNotContainsString('clientFillRemainingDaysLabel', $render);
    }

    public function test_saved_hidden_number_column_is_not_forced_visible_on_settings_reload(): void
    {
        $this->actingAsContractsViewer();

        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => [
                'contract_number' => false,
                'user_name'       => true,
            ],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('contracts.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('contract_number', false);

        $html = $this->get(route('contracts.index'))->assertOk()->getContent();
        $this->assertStringContainsString('contract_number: true', $html);
        $this->assertStringContainsString('data-column-key="contract_number"', $html);
        $this->assertStringContainsString("order: [[1, 'desc']]", $html);
    }

    public function test_legacy_saved_columns_without_contract_number_do_not_store_it_as_false(): void
    {
        $this->actingAsContractsViewer();

        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => [
                'user_name' => true,
                'actions'   => false,
            ],
        ], $this->ajaxHeaders())->assertOk();

        $saved = $this->getJson(route('contracts.columns-settings.get'))
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('contract_number', $saved);
        $this->assertFalse($saved['actions'] ?? true);

        $html = $this->get(route('contracts.index'))->assertOk()->getContent();
        $this->assertStringContainsString('contract_number: true', $html);

        UserTableSetting::query()
            ->where('user_id', auth()->id())
            ->where('table_key', 'contracts_index')
            ->delete();
    }

    public function test_without_fill_expires_permission_remaining_render_stays_gated_and_does_not_leak_column(): void
    {
        $this->actingAsContractsViewer();

        $html = $this->get(route('contracts.index'))->assertOk()->getContent();

        $this->assertStringContainsString('const canSeeFillExpiresAt = false;', $html);
        $this->assertStringContainsString('when: canSeeFillExpiresAt', $html);
        $this->assertStringContainsString('row.fill_expires_remaining', $html);
        $this->assertStringNotContainsString('<th>Срок подписания</th>', $html);
        $this->assertStringNotContainsString('id="colFillExpiresAt"', $html);
        $this->assertStringContainsString('<th>Номер договора</th>', $html);
        $this->assertStringContainsString("order: [[1, 'desc']]", $html);
    }
}
