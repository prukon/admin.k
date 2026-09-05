<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-annul-after-send-index совпадает с кодом аннулирования sent/opened.
 */
final class ContractAnnulAfterSendDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_annul_after_send_without_refund(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-annul-after-send-index"', $html);
        $start = strpos($html, 'id="contract-annul-after-send-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="lesson-package-duration-permission-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('sent', $chunk);
        $this->assertStringContainsString('opened', $chunk);
        $this->assertStringContainsString('revoked', $chunk);
        $this->assertStringContainsString('Отозвано', $chunk);
        $this->assertStringContainsString('70', $chunk);
        $this->assertStringContainsString('не</b> возвращаются', $chunk);
        $this->assertStringContainsString('#annulAfterSendBtn', $chunk);
        $this->assertStringContainsString('canAnnulAfterSend()', $chunk);
        $this->assertStringContainsString('#revokeAwaitingBtn', $chunk);
        $this->assertStringContainsString('awaiting_client_fill', $chunk);
        $this->assertStringContainsString('/client-contracts/{id}/revoke', $chunk);
        $this->assertStringContainsString('Accept: application/json', $chunk);
        $this->assertStringContainsString('location.reload()', $chunk);
        $this->assertStringContainsString('Договор аннулирован. 70 ₽ не возвращаются.', $chunk);
        $this->assertStringContainsString('annul_after_send', $chunk);
        $this->assertStringContainsString('refunded: false', $chunk);
        $this->assertStringContainsString('signed_after_revoke', $chunk);
        $this->assertStringContainsString('$becameSigned', $chunk);
        $this->assertStringContainsString('LatestUserContractLookup', $chunk);
        $this->assertStringContainsString('Создать договор', $chunk);
        $this->assertStringContainsString('Посмотреть черновик', $chunk);
        $this->assertStringContainsString('contracts §7.2', $chunk);
        $this->assertStringContainsString('ContractAnnulAfterSend*FeatureTest', $chunk);
        $this->assertStringContainsString('opened_contract_blocks_create_on_users_list_until_school_annuls', $chunk);
        $this->assertStringContainsString('ContractAnnulAfterSendDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#contract-signed-in-app-index', $chunk);

        $this->assertStringNotContainsString('PODPISLON', $chunk);
        $this->assertStringNotContainsString('provider->revoke', $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_do_not_treat_revoked_as_draft_cell(): void
    {
        $contracts = $this->docFile('contracts.html');
        $fill = $this->docFile('account-contract-fill.html');
        $users = $this->docFile('admin-users.html');
        $leads = $this->docFile('school-leads-widget.html');
        $wallet = $this->docFile('partner-wallet.html');
        $inApp = $this->docFile('in-app-notifications.html');
        $templates = $this->docFile('contract-templates.html');

        $this->assertStringContainsString('/doc#contract-annul-after-send-index', $contracts);
        $this->assertStringContainsString('id="contract-annul-after-send"', $contracts);
        $this->assertStringContainsString('#annulAfterSendBtn', $contracts);
        $this->assertStringContainsString('reason: annul_after_send', $contracts);
        $this->assertStringContainsString('Договор аннулирован. 70 ₽ не возвращаются.', $contracts);

        $this->assertStringContainsString('/doc#contract-annul-after-send-index', $fill);
        $this->assertStringContainsString('Отозвано', $fill);
        $this->assertStringNotContainsString('статус остаётся «Отозван»', $fill);

        $this->assertStringContainsString('/doc#contract-annul-after-send-index', $users);
        $this->assertStringContainsString('не-отозванного', $users);
        $this->assertStringContainsString('включая</b> только <code>revoked</code>', $users);
        $this->assertStringNotContainsString('отозван и т.д.', $users);

        $this->assertStringContainsString('/doc#contract-annul-after-send-index', $leads);
        $this->assertStringContainsString('не</b> <code>revoked</code>', $leads);

        $this->assertStringContainsString('/doc#contract-annul-after-send-index', $wallet);
        $this->assertStringContainsString('credit не пишет', $wallet);

        $this->assertStringContainsString('/doc#contract-annul-after-send-index', $inApp);
        $this->assertStringContainsString('signed_after_revoke', $contracts);

        $this->assertStringContainsString('/doc#contract-annul-after-send-index', $templates);
        $this->assertStringContainsString('возврат только при отзыве', $templates);
    }

    public function test_catalog_and_controller_title_mention_annul_without_refund(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="contract-annul-after-send-index"', $index);
        $this->assertStringContainsString('/doc#contract-annul-after-send-index', $index);
        $this->assertStringContainsString('аннулирование sent/opened без возврата 70 ₽', $index);

        $this->assertStringContainsString('аннулирование sent/opened без возврата 70 ₽ (signed PDF при revoked, без колокольчика)', $controller);
        $this->assertStringContainsString('колокольчик админам при signed (не после revoked)', $controller);
    }

    public function test_users_create_announcement_no_longer_calls_revoked_a_draft_cell(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="users-contract-create-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="kids-tooltip-contrast-index"');
        $this->assertNotFalse($end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('не-отозванному', $chunk);
        $this->assertStringContainsString('/doc#contract-annul-after-send-index', $chunk);
        $this->assertStringContainsString('Посмотреть черновик', $chunk);
        $this->assertStringNotContainsString('отозван и т.д.', $chunk);
    }

    public function test_live_code_matches_documented_annul_and_lookup(): void
    {
        $root = dirname(__DIR__, 3);
        $model = (string) file_get_contents($root.'/app/Models/Contract.php');
        $signing = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractSigningController.php');
        $webhook = (string) file_get_contents($root.'/app/Http/Controllers/Webhooks/PodpislonWebhookController.php');
        $lookup = (string) file_get_contents($root.'/app/Services/SchoolLeads/LatestUserContractLookup.php');
        $show = (string) file_get_contents($root.'/resources/views/contracts/show.blade.php');
        $documents = (string) file_get_contents($root.'/resources/views/account/documents.blade.php');

        $this->assertStringContainsString('function canAnnulAfterSend()', $model);
        $this->assertStringContainsString('STATUS_SENT', $model);
        $this->assertStringContainsString('STATUS_OPENED', $model);
        $this->assertStringContainsString("self::STATUS_REVOKED => 'Отозвано'", $model);

        $this->assertStringContainsString('function annulAfterSend(', $signing);
        $this->assertStringContainsString("'reason'   => 'annul_after_send'", $signing);
        $this->assertStringContainsString("'refunded' => false", $signing);
        $this->assertStringContainsString('Договор аннулирован. 70 ₽ не возвращаются.', $signing);
        $this->assertStringContainsString('Договор аннулирован.\nВозврат 70 ₽: Нет', $signing);
        $this->assertStringContainsString("'type'         => 'signed_after_revoke'", $signing);
        $this->assertStringContainsString('if ($oldStatus === Contract::STATUS_REVOKED)', $signing);

        $this->assertStringContainsString("'type'         => 'signed_after_revoke'", $webhook);
        $this->assertStringContainsString('if ($contractForUpdate->status === Contract::STATUS_REVOKED)', $webhook);
        $this->assertStringContainsString('if ($becameSigned)', $webhook);
        $this->assertStringContainsString('$currentStatus === Contract::STATUS_SIGNED || $currentStatus === Contract::STATUS_REVOKED', $webhook);

        $this->assertStringContainsString("where('status', '!=', Contract::STATUS_REVOKED)", $lookup);
        $this->assertStringContainsString('AND c2.status != ?', $lookup);

        $this->assertStringContainsString('id="annulAfterSendBtn"', $show);
        $this->assertStringContainsString('id="revokeAwaitingBtn"', $show);
        $this->assertStringContainsString("canAnnulAfterSend()", $show);
        $this->assertStringContainsString("canRevokeWithRefund()", $show);
        $this->assertStringContainsString('70 ₽ не возвращаются', $show);
        $this->assertStringContainsString('статус останется «Отозвано»', $show);
        $this->assertStringContainsString("/client-contracts/' + contractId + '/revoke'", $show);
        $this->assertStringContainsString("headers: {'Accept': 'application/json'}", $show);
        $this->assertStringContainsString('location.reload()', $show);
        $this->assertStringContainsString("\$contract->status === 'revoked' && !\$contract->signed_pdf_path", $show);

        $this->assertStringContainsString('signed_pdf_path', $documents);
        $this->assertStringContainsString('account.documents.downloadSigned', $documents);
        $this->assertStringContainsString('js-open-contract-fill-edit', $documents);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
