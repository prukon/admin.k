<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#account-documents-resend-sms-index совпадает с кнопкой
 * «Отправить SMS ещё раз» в кабинете (sent / opened / expired, маска номера).
 */
final class AccountDocumentsResendSmsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_cabinet_resend_sms(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-documents-resend-sms-index"', $html);
        $start = strpos($html, 'id="account-documents-resend-sms-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-monthly-former-charge-clear-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/account-settings/documents', $chunk);
        $this->assertStringContainsString('Отправить SMS ещё раз', $chunk);
        $this->assertStringContainsString('CLIENT_RESEND_SMS_BUTTON', $chunk);
        $this->assertStringContainsString('SMS уйдёт на', $chunk);
        $this->assertStringContainsString('formatMaskedForDisplay', $chunk);
        $this->assertStringContainsString('+7 (XXX) ***-**-NN', $chunk);
        $this->assertStringContainsString('canClientResendSms', $chunk);
        $this->assertStringContainsString('lastSignerPhone', $chunk);
        $this->assertStringContainsString('sent', $chunk);
        $this->assertStringContainsString('opened', $chunk);
        $this->assertStringContainsString('expired', $chunk);
        $this->assertStringContainsString('provider_doc_id', $chunk);
        $this->assertStringContainsString('account.documents.resendSms', $chunk);
        $this->assertStringContainsString('AccountContractResendSmsRequest', $chunk);
        $this->assertStringContainsString('prohibited', $chunk);
        $this->assertStringContainsString('resendFromLastRequest', $chunk);
        $this->assertStringContainsString('repeat-send', $chunk);
        $this->assertStringContainsString('70 ₽', $chunk);
        $this->assertStringContainsString('ContractSmsCooldown', $chunk);
        $this->assertStringContainsString('js-contract-resend-sms-form', $chunk);
        $this->assertStringContainsString('#openResendModal', $chunk);
        $this->assertStringContainsString('account-contract-fill §1.5', $chunk);
        $this->assertStringContainsString('AccountDocumentsResendSmsUxFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsResendSmsAccessFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsResendSmsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsResendSmsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsResendSmsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('RuPhoneTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);

        $this->assertStringNotContainsString('name="signer_phone"', $chunk);
        $this->assertStringNotContainsString('родителю нужно ввести новый номер', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $fill = $this->docFile('account-contract-fill.html');
        $contracts = $this->docFile('contracts.html');
        $family = $this->docFile('parents-and-family-cabinet.html');
        $index = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-documents-resend-sms"', $fill);
        $this->assertStringContainsString('/doc#account-documents-resend-sms-index', $fill);
        $this->assertStringContainsString('Отправить SMS ещё раз', $fill);
        $this->assertStringContainsString('formatMaskedForDisplay', $fill);
        $this->assertStringContainsString('canClientResendSms', $fill);

        $this->assertStringContainsString('/doc#account-documents-resend-sms-index', $contracts);
        $this->assertStringContainsString('account-documents-resend-sms', $contracts);

        $this->assertStringContainsString('/doc#account-documents-resend-sms-index', $family);

        $this->assertStringContainsString('/doc#account-documents-resend-sms-index', $index);
        $expiryStart = strpos($index, 'id="account-documents-expiry-notice-index"');
        $expiryEnd = strpos($index, 'id="account-documents-resend-sms-index"');
        $this->assertNotFalse($expiryStart);
        $this->assertNotFalse($expiryEnd);
        $expiryChunk = substr($index, $expiryStart, $expiryEnd - $expiryStart);
        $this->assertStringContainsString('/doc#account-documents-resend-sms-index', $expiryChunk);
    }

    public function test_catalog_and_controller_title_mention_resend_sms(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#account-documents-resend-sms-index', $index);
        $this->assertStringContainsString('повтор SMS из кабинета', $index);
        $this->assertStringContainsString('Отправить SMS ещё раз', $controller);
        $this->assertStringContainsString('маска номера', $controller);
    }

    public function test_live_code_matches_documented_cabinet_resend_sms(): void
    {
        $root = dirname(__DIR__, 3);
        $model = (string) file_get_contents($root.'/app/Models/Contract.php');
        $phone = (string) file_get_contents($root.'/app/Support/RuPhone.php');
        $blade = (string) file_get_contents($root.'/resources/views/account/documents.blade.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/AccountContractFillController.php');
        $request = (string) file_get_contents($root.'/app/Http/Requests/Account/AccountContractResendSmsRequest.php');
        $service = (string) file_get_contents($root.'/app/Services/Contracts/ContractPodpislonSendService.php');
        $routes = (string) file_get_contents($root.'/routes/web.php');
        $show = (string) file_get_contents($root.'/resources/views/contracts/show.blade.php');

        $this->assertStringContainsString("CLIENT_RESEND_SMS_BUTTON = 'Отправить SMS ещё раз'", $model);
        $this->assertStringContainsString('function canClientResendSms', $model);
        $this->assertStringContainsString('function lastSignerPhone', $model);
        $this->assertStringContainsString('function clientResendSmsMaskedPhone', $model);
        $this->assertStringContainsString('STATUS_SENT', $model);
        $this->assertStringContainsString('STATUS_OPENED', $model);
        $this->assertStringContainsString('STATUS_EXPIRED', $model);

        $this->assertStringContainsString('function formatMaskedForDisplay', $phone);
        $this->assertStringContainsString("'+7 (%s) ***-**-%s'", $phone);

        $this->assertStringContainsString('canClientResendSms()', $blade);
        $this->assertStringContainsString('CLIENT_RESEND_SMS_BUTTON', $blade);
        $this->assertStringContainsString('js-contract-resend-sms-form', $blade);
        $this->assertStringContainsString('SMS уйдёт на', $blade);
        $this->assertStringContainsString("submit', '.js-contract-resend-sms-form'", $blade);
        $this->assertStringNotContainsString('name="signer_phone"', $blade);

        $this->assertStringContainsString('function resendSms', $controller);
        $this->assertStringContainsString('resendFromLastRequest', $controller);

        $this->assertStringContainsString("'signer_phone'      => ['prohibited']", $request);
        $this->assertStringContainsString('canAccessContract', $request);

        $this->assertStringContainsString('function resendFromLastRequest', $service);
        $this->assertStringContainsString('resendForContract', $service);
        $this->assertStringContainsString('ContractSmsCooldown::tryAcquire', $service);

        $provider = (string) file_get_contents($root.'/app/Services/Signatures/Providers/PodpislonProvider.php');
        $this->assertStringContainsString('/repeat-send', $provider);

        $this->assertStringContainsString("->name('account.documents.resendSms')", $routes);

        $this->assertStringContainsString("id=\"openResendModal\"", $show);
        $this->assertStringContainsString('Повторно отправить на подпись', $show);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
