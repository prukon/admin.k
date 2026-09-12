<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-list-fill-expires-at-index совпадает со списком договоров:
 * колонка «Срок подписания» только при скрытом contracts.fillExpiresAt.view.
 */
final class ContractListFillExpiresAtDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_list_fill_expires_at_column(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-list-fill-expires-at-index"', $html);
        $start = strpos($html, 'id="contract-list-fill-expires-at-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="account-documents-fill-remaining-days-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/client-contracts', $chunk);
        $this->assertStringContainsString('колонка «Срок подписания»', $chunk);
        $this->assertStringContainsString('contracts.fillExpiresAt.view', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('fill_expires_at', $chunk);
        $this->assertStringContainsString('d.m.Y H:i:s', $chunk);
        $this->assertStringContainsString('#colFillExpiresAt', $chunk);
        $this->assertStringContainsString('Колонки', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('Права и роли', $chunk);
        $this->assertStringContainsString('role_base_permissions', $chunk);
        $this->assertStringContainsString('Срок заполнения', $chunk);
        $this->assertStringContainsString('без секунд', $chunk);
        $this->assertStringContainsString('contracts.view', $chunk);
        $this->assertStringContainsString('account-documents-fill-remaining-days-index', $chunk);
        $this->assertStringContainsString('contracts §1.4', $chunk);
        $this->assertStringContainsString('partners-permissions#optional-admin-permissions', $chunk);
        $this->assertStringContainsString('contract-card-fill-expires', $chunk);
        $this->assertStringContainsString('ContractsTableAndColumnsTest', $chunk);
        $this->assertStringContainsString('ContractListFillExpiresAtUxFeatureTest', $chunk);
        $this->assertStringContainsString('ContractsFillExpiresAtPermissionCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListFillExpiresAtDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#contract-list-fill-expires-at-index', $chunk);
        $this->assertStringNotContainsString('npm run build', $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_describe_column(): void
    {
        $contracts = $this->docFile('contracts.html');
        $groups = $this->docFile('settings-permission-groups.html');
        $partners = $this->docFile('partners-permissions.html');

        $this->assertStringContainsString('/doc#contract-list-fill-expires-at-index', $contracts);
        $this->assertStringContainsString('id="contract-list-fill-expires-at"', $contracts);
        $this->assertStringContainsString('#colFillExpiresAt', $contracts);
        $this->assertStringContainsString('contracts.fillExpiresAt.view', $contracts);
        $this->assertStringContainsString('data-column-key="fill_expires_at"', $contracts);
        $this->assertStringContainsString('when: canSeeFillExpiresAt', $contracts);
        $this->assertStringContainsString('id="contract-card-fill-expires"', $contracts);
        $this->assertStringContainsString('Срок заполнения', $contracts);
        $this->assertStringContainsString('d.m.Y H:i', $contracts);

        $this->assertStringContainsString('contracts.fillExpiresAt.view', $groups);
        $this->assertStringContainsString('2026_09_12_062900_add_contracts_fill_expires_at_view_permission', $groups);

        $this->assertStringContainsString('contracts.fillExpiresAt.view', $partners);
        $this->assertStringContainsString('/doc#contract-list-fill-expires-at-index', $partners);
        $this->assertStringContainsString('id="optional-admin-permissions"', $partners);
    }

    public function test_catalog_and_controller_title_mention_fill_expires_column(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#contract-list-fill-expires-at-index', $index);
        $this->assertStringContainsString('колонка «Срок подписания»', $index);

        $this->assertStringContainsString('колонка «Срок подписания»', $controller);
        $this->assertStringContainsString('contracts.fillExpiresAt.view', $controller);
    }

    public function test_table_controller_and_views_match_documented_fill_expires_column(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractTableController.php');
        $index = (string) file_get_contents($root.'/resources/views/contracts/index.blade.php');
        $seeder = (string) file_get_contents($root.'/database/seeders/PermissionSeeder.php');
        $auth = (string) file_get_contents($root.'/app/Providers/AuthServiceProvider.php');
        $baseRoles = (string) file_get_contents($root.'/config/role_base_permissions.php');

        $this->assertStringContainsString("viewerCanSeeFillExpiresAt()", $controller);
        $this->assertStringContainsString("'fill_expires_at'", $controller);
        $this->assertStringContainsString('fill_expires_remaining', $controller);
        $this->assertStringContainsString('contracts.fill_expires_at is null, contracts.fill_expires_at', $controller);
        $this->assertStringContainsString("format('d.m.Y H:i:s')", $controller);

        $this->assertStringContainsString('<th>Срок подписания</th>', $index);
        $this->assertStringContainsString('data-column-key="fill_expires_at"', $index);
        $this->assertStringContainsString('for="colFillExpiresAt">Срок подписания</label>', $index);
        $this->assertStringContainsString('@if($canSeeFillExpiresAt)', $index);
        $this->assertStringContainsString('when: canSeeFillExpiresAt', $index);
        $this->assertStringContainsString('fill_expires_at: canSeeFillExpiresAt', $index);

        $show = (string) file_get_contents($root.'/resources/views/contracts/show.blade.php');
        $this->assertStringContainsString('Срок заполнения', $show);
        $this->assertStringContainsString("format('d.m.Y H:i')", $show);
        $this->assertStringContainsString('isTemplateMode() && $contract->fill_expires_at', $show);

        $this->assertStringContainsString(
            "'name' => 'contracts.fillExpiresAt.view',   'description' => 'Договоры: колонка «Срок подписания»'",
            $seeder
        );
        $this->assertMatchesRegularExpression(
            "/'name' => 'contracts\\.fillExpiresAt\\.view'.{0,160}'is_visible' => 0/s",
            $seeder
        );

        $this->assertStringContainsString("Gate::define('contracts.fillExpiresAt.view'", $auth);
        $this->assertStringContainsString('Superadmin проходит Gate::before', $auth);

        $this->assertStringNotContainsString("'contracts.fillExpiresAt.view'", $baseRoles);
        $this->assertStringNotContainsString('contracts.fillExpiresAt.view', $baseRoles);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
