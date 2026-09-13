<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-list-hide-remaining-signed-revoked-index совпадает со списком CRM:
 * у signed/revoked нет второй строки «Срок подписания»; дата остаётся; ЛК revoked не прячет.
 */
final class ContractListHideRemainingSignedRevokedDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_hide_remaining_for_signed_and_revoked(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-list-hide-remaining-signed-revoked-index"', $html);
        $start = strpos($html, 'id="contract-list-hide-remaining-signed-revoked-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="account-phone-verify-permission-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/client-contracts', $chunk);
        $this->assertStringContainsString('Срок подписания', $chunk);
        $this->assertStringContainsString('Подписано', $chunk);
        $this->assertStringContainsString('Отозвано', $chunk);
        $this->assertStringContainsString('signed', $chunk);
        $this->assertStringContainsString('revoked', $chunk);
        $this->assertStringContainsString('STATUS_SIGNED', $chunk);
        $this->assertStringContainsString('STATUS_REVOKED', $chunk);
        $this->assertStringContainsString('SCHOOL_LIST_FILL_REMAINING_EXPIRED', $chunk);
        $this->assertStringContainsString('Срок истек.', $chunk);
        $this->assertStringContainsString('schoolListFillRemainingDaysLabel', $chunk);
        $this->assertStringContainsString('fill_expires_remaining', $chunk);
        $this->assertStringContainsString('fill_expires_remaining_warn', $chunk);
        $this->assertStringContainsString('if (!remaining) return date', $chunk);
        $this->assertStringContainsString('contracts.fillExpiresAt.view', $chunk);
        $this->assertStringContainsString('Срок заполнения', $chunk);
        $this->assertStringContainsString('shouldShowClientFillRemainingDays', $chunk);
        $this->assertStringContainsString('account-documents-fill-remaining-days-index', $chunk);
        $this->assertStringContainsString('contracts §1.4', $chunk);
        $this->assertStringContainsString('ContractSchoolListFillRemainingDaysTest', $chunk);
        $this->assertStringContainsString('data_hides_remaining_for_signed_contract', $chunk);
        $this->assertStringContainsString('data_hides_remaining_for_revoked_contract', $chunk);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListHideRemainingSignedRevokedDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#contract-list-hide-remaining-signed-revoked-index', $chunk);
        $this->assertStringNotContainsString('npm run build', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $contracts = $this->docFile('contracts.html');
        $fill = $this->docFile('account-contract-fill.html');
        $index = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-list-hide-remaining-signed-revoked"', $contracts);
        $this->assertStringContainsString('/doc#contract-list-hide-remaining-signed-revoked-index', $contracts);
        $this->assertStringContainsString('schoolListFillRemainingDaysLabel', $contracts);
        $this->assertStringContainsString('Подписано', $contracts);
        $this->assertStringContainsString('Отозвано', $contracts);
        $this->assertStringContainsString('fill_expires_remaining_warn: false', $contracts);
        $this->assertStringContainsString('ContractListHideRemainingSignedRevokedDocumentationContractTest.php', $contracts);

        $this->assertStringContainsString('/doc#contract-list-hide-remaining-signed-revoked-index', $fill);
        $this->assertStringContainsString('schoolListFillRemainingDaysLabel', $fill);

        $this->assertStringContainsString('/doc#contract-list-hide-remaining-signed-revoked-index', $index);
        $this->assertStringContainsString('без второй строки у signed/revoked', $index);
    }

    public function test_catalog_and_controller_title_mention_hidden_remaining(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#contract-list-hide-remaining-signed-revoked-index', $index);
        $this->assertStringContainsString('без остатка дней у «Подписано» и «Отозвано»', $index);

        $this->assertStringContainsString('без второй строки у signed/revoked', $controller);
        $this->assertStringContainsString('fill_expires_at + остаток дней', $controller);
    }

    public function test_live_code_matches_documented_hide_remaining(): void
    {
        $root = dirname(__DIR__, 3);
        $model = (string) file_get_contents($root.'/app/Models/Contract.php');
        $list = (string) file_get_contents($root.'/resources/views/contracts/index.blade.php');
        $show = (string) file_get_contents($root.'/resources/views/contracts/show.blade.php');
        $table = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractTableController.php');

        $this->assertStringContainsString('function schoolListFillRemainingDaysLabel', $model);
        $this->assertStringContainsString('STATUS_SIGNED, self::STATUS_REVOKED', $model);
        $this->assertStringContainsString("SCHOOL_LIST_FILL_REMAINING_EXPIRED = 'Срок истек.'", $model);
        $this->assertStringContainsString('function shouldShowClientFillRemainingDays', $model);

        $cabinetStart = strpos($model, 'function shouldShowClientFillRemainingDays');
        $cabinetEnd = strpos($model, 'function clientFillRemainingDays');
        $this->assertNotFalse($cabinetStart);
        $this->assertNotFalse($cabinetEnd);
        $this->assertGreaterThan($cabinetStart, $cabinetEnd);
        $cabinetChunk = substr($model, $cabinetStart, $cabinetEnd - $cabinetStart);
        $this->assertStringContainsString('STATUS_SIGNED', $cabinetChunk);
        $this->assertStringNotContainsString('STATUS_REVOKED', $cabinetChunk);

        $this->assertStringContainsString("const remaining = escapeHtml(row.fill_expires_remaining || '')", $list);
        $this->assertStringContainsString('if (!remaining)', $list);
        $this->assertStringContainsString('return date;', $list);

        $this->assertStringContainsString('Срок заполнения', $show);
        $this->assertStringContainsString('isTemplateMode() && $contract->fill_expires_at', $show);
        $this->assertStringNotContainsString('STATUS_SIGNED', substr(
            $show,
            (int) strpos($show, 'Срок заполнения') - 200,
            400
        ));

        $this->assertStringContainsString("schoolListFillRemainingDaysLabel()", $table);
        $this->assertStringContainsString('fill_expires_remaining', $table);
        $this->assertStringContainsString('fill_expires_remaining_warn', $table);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
