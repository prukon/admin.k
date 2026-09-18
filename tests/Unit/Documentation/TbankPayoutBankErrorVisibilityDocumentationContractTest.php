<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#tbank-payout-bank-error-visibility-index совпадает с UI ошибок банка.
 */
final class TbankPayoutBankErrorVisibilityDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_payout_bank_error_visibility(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="tbank-payout-bank-error-visibility-index"', $html);
        $start = strpos($html, 'id="tbank-payout-bank-error-visibility-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-ltv-teams-period-tabs-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('payload_init', $chunk);
        $this->assertStringContainsString('ErrorCode', $chunk);
        $this->assertStringContainsString('disableReimbursement', $chunk);
        $this->assertStringContainsString('TinkoffPayoutBankError', $chunk);
        $this->assertStringContainsString('/admin/tinkoff/payouts/{id}', $chunk);
        $this->assertStringContainsString('/admin/tinkoff/payments/{id}', $chunk);
        $this->assertStringContainsString('/admin/legal-entities/{id}', $chunk);
        $this->assertStringContainsString('sm-refresh', $chunk);
        $this->assertStringContainsString('TinkoffPayoutBankErrorTest', $chunk);
        $this->assertStringContainsString('TbankPayoutBankErrorUxFeatureTest', $chunk);
        $this->assertStringContainsString('LegalEntitiesSmRegisterFeatureTest', $chunk);
        $this->assertStringContainsString('LegalEntitiesSmEnableReimbursementFeatureTest', $chunk);
    }

    public function test_live_code_matches_announced_visibility_contract(): void
    {
        $error = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Tinkoff/TinkoffPayoutBankError.php');
        $this->assertStringContainsString('fromPayload', $error);
        $this->assertStringContainsString('ErrorCode', $error);

        $timeline = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Tinkoff/TinkoffPaymentTimelineBuilder.php');
        $this->assertStringContainsString('bankRejectionSummary', $timeline);
        $this->assertStringContainsString('Выплата отклонена:', $timeline);

        $payoutShow = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/tinkoff/payouts/show.blade.php');
        $this->assertStringContainsString('id="payout-bank-error"', $payoutShow);

        $paymentShow = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/tinkoff/payments/show.blade.php');
        $this->assertStringContainsString('bankRejectionSummary', $paymentShow);

        $legalShow = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/legal-entities/show.blade.php');
        $this->assertStringContainsString('id="legal-entity-tbank-shop-status"', $legalShow);
        $this->assertStringContainsString('tinkoffPayoutsBlocked', $legalShow);

        $service = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Tinkoff/PartnerLegalEntitySmRegisterService.php');
        $this->assertStringContainsString('applyShopStatus', $service);
        $this->assertStringContainsString('disableReimbursement', $service);

        $refresh = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/PartnerLegalEntity/SmRefreshPartnerLegalEntityRequest.php');
        $this->assertStringContainsString('legal_entities.manage', $refresh);
    }

    public function test_parent_docs_link_the_announcement(): void
    {
        $tbank = $this->docFile('tbank.html');
        $this->assertStringContainsString('/doc#tbank-payout-bank-error-visibility-index', $tbank);

        $payouts = $this->docFile('tbank-admin-payouts.html');
        $this->assertStringContainsString('/doc#tbank-payout-bank-error-visibility-index', $payouts);

        $legal = $this->docFile('admin-legal-entities.html');
        $this->assertStringContainsString('/doc#tbank-payout-bank-error-visibility-index', $legal);

        $index = $this->docFile('index.html');
        $this->assertStringContainsString('/doc#tbank-payout-bank-error-visibility-index', $index);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
