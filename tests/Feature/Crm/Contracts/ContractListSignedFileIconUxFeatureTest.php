<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\UserTableSetting;

/**
 * UX колонки «Договор»: порядок после «Статус», пустая ячейка без файла,
 * клик не уходит на карточку в той же вкладке, сортировка «Обновлён» не съезжает на иконку.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractListSignedFileIconUxFeatureTest extends ContractListSignedFileIconTestCase
{
    public function test_first_open_renders_signed_file_column_between_status_and_updated_at(): void
    {
        $this->actingAsContractsViewer();

        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<th>Статус<\/th>\s*<th>Договор<\/th>\s*<th>Обновлён<\/th>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-column-key="status_label"[^>]*>[\s\S]*data-column-key="signed_file"[^>]*>[\s\S]*data-column-key="updated_at"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/class="form-check-input column-toggle"[^>]*data-column-key="signed_file"[^>]*checked/',
            $html
        );
        $this->assertStringContainsString('signed_file: true', $html);
        $this->assertStringContainsString("order: [[8, 'desc']]", $html);
        $this->assertStringNotContainsString("order: [[7, 'desc']]", $html);
    }

    public function test_reopening_create_modal_does_not_drop_signed_file_column(): void
    {
        $this->actingAsContractsViewer();

        $html = $this->get(route('contracts.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('id="createContractModal"', false)
            ->getContent();

        $this->assertStringContainsString('<th>Договор</th>', $html);
        $this->assertStringContainsString('data-column-key="signed_file"', $html);
        $this->assertStringContainsString('renderSignedContractFileCell', $html);
        $this->assertStringContainsString("order: [[8, 'desc']]", $html);
    }

    public function test_signed_file_cell_opens_download_in_new_tab_and_stays_empty_without_url(): void
    {
        $this->actingAsContractsViewer();

        $html = $this->get(route('contracts.index'))->assertOk()->getContent();

        $statusPos = strpos($html, "key: 'status_label'");
        $signedPos = strpos($html, "key: 'signed_file'");
        $updatedPos = strpos($html, "key: 'updated_at'");
        $this->assertNotFalse($statusPos);
        $this->assertNotFalse($signedPos);
        $this->assertNotFalse($updatedPos);
        $this->assertGreaterThan($statusPos, $signedPos);
        $this->assertGreaterThan($signedPos, $updatedPos);

        $fnStart = strpos($html, 'function renderSignedContractFileCell');
        $this->assertNotFalse($fnStart);
        $cell = substr($html, $fnStart, 900);

        $this->assertStringContainsString('if (!data)', $cell);
        $this->assertStringContainsString("return '';", $cell);
        $this->assertStringContainsString('KidsCrmDataTable.renderIcon', $cell);
        $this->assertStringContainsString('fa-solid fa-file-pdf', $cell);
        $this->assertStringContainsString('#0d6efd', $cell);
        $this->assertStringContainsString('Скачать подписанный договор', $cell);
        $this->assertStringContainsString('href: data', $cell);
        $this->assertStringNotContainsString('js-dt-nav-link', $cell);
        $this->assertStringNotContainsString('/client-contracts/\' + row.id + \'"', $cell);

        $colChunk = substr($html, $signedPos, 420);
        $this->assertStringContainsString("type: 'icon'", $colChunk);
        $this->assertStringContainsString("data: 'download_signed_url'", $colChunk);
        $this->assertStringContainsString('orderable: false', $colChunk);
        $this->assertStringContainsString('render: renderSignedContractFileCell', $colChunk);
        $this->assertStringNotContainsString("type: 'link'", $colChunk);
        $this->assertStringNotContainsString('js-dt-nav-link', $colChunk);
    }

    public function test_name_column_still_navigates_to_card_in_same_tab(): void
    {
        $this->actingAsContractsViewer();

        $html = $this->get(route('contracts.index'))->assertOk()->getContent();
        $namePos = strpos($html, "key: 'user_name'");
        $this->assertNotFalse($namePos);
        $nameChunk = substr($html, $namePos, 450);

        $this->assertStringContainsString("type: 'link'", $nameChunk);
        $this->assertStringContainsString("linkClass: 'js-dt-nav-link'", $nameChunk);
        $this->assertStringContainsString("data-href=\"/client-contracts/' + row.id + '\"", $nameChunk);
    }

    public function test_saved_hidden_signed_file_is_not_forced_visible_on_settings_reload(): void
    {
        $this->actingAsContractsViewer();

        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => [
                'signed_file' => false,
                'user_name'   => true,
            ],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('contracts.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('signed_file', false);

        $html = $this->get(route('contracts.index'))->assertOk()->getContent();
        $this->assertStringContainsString('signed_file: true', $html);
        $this->assertStringContainsString('data-column-key="signed_file"', $html);
    }

    public function test_legacy_saved_columns_without_signed_file_do_not_store_it_as_false(): void
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

        $this->assertArrayNotHasKey('signed_file', $saved);
        $this->assertFalse($saved['actions'] ?? true);

        UserTableSetting::query()
            ->where('user_id', auth()->id())
            ->where('table_key', 'contracts_index')
            ->delete();
    }
}
