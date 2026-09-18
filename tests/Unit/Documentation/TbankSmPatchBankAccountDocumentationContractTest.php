<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#tbank-sm-patch-bankaccount-index совпадает с PATCH bankAccount.
 */
final class TbankSmPatchBankAccountDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_sm_patch_bank_account_only(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="tbank-sm-patch-bankaccount-index"', $html);
        $start = strpos($html, 'id="tbank-sm-patch-bankaccount-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="tbank-payout-bank-error-visibility-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('patchBankAccountForPayout', $chunk);
        $this->assertStringContainsString('sm-enable-reimbursement', $chunk);
        $this->assertStringContainsString('disableReimbursement', $chunk);
        $this->assertStringContainsString('legal-entity-sm-patch-fields-hint', $chunk);
        $this->assertStringContainsString('legal-entity-enable-reimbursement', $chunk);
        $this->assertStringContainsString('SmEnableReimbursementPartnerLegalEntityRequest', $chunk);
        $this->assertStringContainsString('TbankAutoPayoutOnWebhookTest', $chunk);
        $this->assertStringContainsString('LegalEntitiesSmEnableReimbursementFeatureTest', $chunk);
        $this->assertStringContainsString('LegalEntitiesSmPatchBankAccountUxFeatureTest', $chunk);
        $this->assertStringContainsString('TbankSmPatchBankAccountDocumentationContractTest', $chunk);
    }

    public function test_live_code_matches_announced_patch_contract(): void
    {
        $service = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Tinkoff/PartnerLegalEntitySmRegisterService.php');
        $this->assertStringContainsString('function patchBankAccountForPayout', $service);
        $this->assertStringContainsString('function enableReimbursement', $service);
        $this->assertStringContainsString('disableReimbursement', $service);

        $payments = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Tinkoff/TinkoffPaymentsService.php');
        $this->assertStringContainsString('patchBankAccountForPayout', $payments);
        $this->assertStringNotContainsString("'bankAccount' => ['details' => \$details]", $payments);

        $legalShow = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/legal-entities/show.blade.php');
        $this->assertStringContainsString('id="legal-entity-sm-patch-fields-hint"', $legalShow);
        $this->assertStringContainsString('id="legal-entity-enable-reimbursement"', $legalShow);
        $this->assertStringContainsString('sm-enable-reimbursement', $legalShow);

        $refresh = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/PartnerLegalEntity/SmEnableReimbursementPartnerLegalEntityRequest.php');
        $this->assertStringContainsString('legal_entities.manage', $refresh);
    }

    public function test_parent_docs_link_the_announcement(): void
    {
        $tbank = $this->docFile('tbank.html');
        $this->assertStringContainsString('/doc#tbank-sm-patch-bankaccount-index', $tbank);

        $legal = $this->docFile('admin-legal-entities.html');
        $this->assertStringContainsString('/doc#tbank-sm-patch-bankaccount-index', $legal);

        $index = $this->docFile('index.html');
        $this->assertStringContainsString('/doc#tbank-sm-patch-bankaccount-index', $index);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
