<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-send-failure-message-index совпадает с кодом.
 */
final class ContractSendFailureMessageDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_send_failure_message(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-send-failure-message-index"', $html);
        $start = strpos($html, 'id="contract-send-failure-message-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-list-number-and-fill-remaining-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('status: false', $chunk);
        $this->assertStringContainsString('Не выбран провайдер отправки смс.', $chunk);
        $this->assertStringContainsString('PodpislonProvider::send()', $chunk);
        $this->assertStringContainsString('provider_request_id', $chunk);
        $this->assertStringContainsString('payload_json.message', $chunk);
        $this->assertStringContainsString('#contract-status-error', $chunk);
        $this->assertStringContainsString('.contract-event-error', $chunk);
        $this->assertStringContainsString('status_error', $chunk);
        $this->assertStringContainsString('send_not_sent', $chunk);
        $this->assertStringContainsString('не пишет событие <code>sent</code>', $chunk);
        $this->assertStringContainsString('ContractSendFailureMessageAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ContractSendFailureMessageFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ContractSendFailureMessageAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ContractSendFailureMessageNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('ContractSendFailureMessageUxFeatureTest', $chunk);
        $this->assertStringContainsString('гость/403/тренер/чужая школа', $chunk);
        $this->assertStringContainsString('native JSON 422, не 302', $chunk);
        $this->assertStringContainsString('ContractEventFailureMessageTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('#error-modal-message', $chunk);
        $this->assertStringContainsString('lastFailureMessage()', $chunk);
        $this->assertStringContainsString('errors.sign', $chunk);
        $this->assertStringContainsString('Провайдер не подтвердил отправку SMS.', $chunk);
        $this->assertStringContainsString('ContractSendFailureMessageDocumentationContractTest', $chunk);
        $this->assertStringContainsString('contracts §3.1.2', $chunk);
        $this->assertStringContainsString('/doc#contract-send-failure-message-index', $chunk);
    }

    public function test_related_doc_and_code_match_announcement(): void
    {
        $contracts = $this->docFile('contracts.html');
        $root = dirname(__DIR__, 3);
        $provider = (string) file_get_contents($root.'/app/Services/Signatures/Providers/PodpislonProvider.php');
        $send = (string) file_get_contents($root.'/app/Services/Contracts/ContractPodpislonSendService.php');
        $event = (string) file_get_contents($root.'/app/Models/ContractEvent.php');
        $contract = (string) file_get_contents($root.'/app/Models/Contract.php');
        $table = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractTableController.php');
        $show = (string) file_get_contents($root.'/resources/views/contracts/show.blade.php');
        $index = (string) file_get_contents($root.'/resources/views/contracts/index.blade.php');

        $this->assertStringContainsString('/doc#contract-send-failure-message-index', $contracts);
        $this->assertStringContainsString('id="contract-send-failure-message"', $contracts);
        $this->assertStringContainsString('#contract-status-error', $contracts);
        $this->assertStringContainsString('status_error', $contracts);
        $this->assertStringContainsString('isAddDocumentBusinessRejection', $provider);
        $this->assertStringContainsString('rejectedAddDocumentResult', $provider);
        $this->assertStringContainsString('fromProviderSendResult', $send);
        $this->assertStringContainsString('if (!$contract->provider_doc_id)', $send);
        $this->assertStringContainsString('userFacingMessage', $event);
        $this->assertStringContainsString('lastFailureMessage', $contract);
        $this->assertStringContainsString("'status_error'", $table);
        $this->assertStringContainsString('id="contract-status-error"', $show);
        $this->assertStringContainsString('contract-event-error', $show);
        $this->assertStringContainsString('#error-modal-message', $contracts);
        $this->assertStringContainsString('row.status_error', $index);
        $this->assertStringContainsString('titleAttr', $index);
        $this->assertStringContainsString('ContractSendFailureMessageAjaxContractFeatureTest.php', $contracts);
        $this->assertStringContainsString('ContractSendFailureMessageNonAjaxSafetyNetFeatureTest.php', $contracts);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest.php', $contracts);

        $accountFill = $this->docFile('account-contract-fill.html');
        $this->assertStringContainsString('/doc#contract-send-failure-message-index', $accountFill);
        $this->assertStringContainsString('errors.sign', $accountFill);
        $this->assertStringContainsString('#contract-status-error', $accountFill);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
