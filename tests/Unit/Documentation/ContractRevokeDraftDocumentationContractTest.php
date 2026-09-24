<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-revoke-draft-index совпадает с отзывом черновика без возврата 70 ₽.
 */
final class ContractRevokeDraftDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_draft_revoke_without_refund(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-revoke-draft-index"', $html);
        $start = strpos($html, 'id="contract-revoke-draft-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="lesson-package-freeze-permission-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('draft', $chunk);
        $this->assertStringContainsString('revoked', $chunk);
        $this->assertStringContainsString('Отозвано', $chunk);
        $this->assertStringContainsString('70', $chunk);
        $this->assertStringContainsString('не</b> возвращаются', $chunk);
        $this->assertStringContainsString('#revokeDraftBtn', $chunk);
        $this->assertStringContainsString('canRevokeDraft()', $chunk);
        $this->assertStringContainsString('/client-contracts/{id}/revoke', $chunk);
        $this->assertStringContainsString('Accept: application/json', $chunk);
        $this->assertStringContainsString('location.reload()', $chunk);
        $this->assertStringContainsString('Договор отозван. 70 ₽ не возвращаются.', $chunk);
        $this->assertStringContainsString('revoke_draft', $chunk);
        $this->assertStringContainsString('refunded: false', $chunk);
        $this->assertStringContainsString('Договор отозван. Возврат 70 ₽: Нет', $chunk);
        $this->assertStringContainsString('canClientSign', $chunk);
        $this->assertStringContainsString('contracts §7.3', $chunk);
        $this->assertStringContainsString('ContractRevokeDraftFeatureTest', $chunk);
        $this->assertStringContainsString('ContractRevokeDraftDocumentationContractTest', $chunk);

        $this->assertStringNotContainsString('provider->revoke', $chunk);
    }

    public function test_contracts_page_documents_draft_revoke(): void
    {
        $contracts = $this->docFile('contracts.html');

        $this->assertStringContainsString('id="contract-revoke-draft"', $contracts);
        $this->assertStringContainsString('/doc#contract-revoke-draft-index', $contracts);
        $this->assertStringContainsString('#revokeDraftBtn', $contracts);
        $this->assertStringContainsString('reason: revoke_draft', $contracts);
        $this->assertStringContainsString('Договор отозван. 70 ₽ не возвращаются.', $contracts);
        $this->assertStringContainsString('canRevokeDraft()', $contracts);
    }

    public function test_live_code_matches_documented_draft_revoke(): void
    {
        $root = dirname(__DIR__, 3);
        $model = (string) file_get_contents($root.'/app/Models/Contract.php');
        $signing = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractSigningController.php');
        $show = (string) file_get_contents($root.'/resources/views/contracts/show.blade.php');

        $this->assertStringContainsString('function canRevokeDraft()', $model);
        $this->assertStringContainsString('STATUS_DRAFT', $model);

        $this->assertStringContainsString('function revokeDraftWithoutRefund(', $signing);
        $this->assertStringContainsString("'reason'   => 'revoke_draft'", $signing);
        $this->assertStringContainsString('Договор отозван. 70 ₽ не возвращаются.', $signing);
        $this->assertStringContainsString('Договор отозван.\nВозврат 70 ₽: Нет', $signing);
        $this->assertStringContainsString('if ($contract->canRevokeDraft())', $signing);

        $this->assertStringContainsString('id="revokeDraftBtn"', $show);
        $this->assertStringContainsString('canRevokeDraft()', $show);
        $this->assertStringContainsString('70 ₽ не возвращаются', $show);
        $this->assertStringContainsString("/client-contracts/' + contractId + '/revoke'", $show);
        $this->assertStringContainsString('location.reload()', $show);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
