<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#account-documents-expiry-notice-index совпадает с карточкой
 * «Мои документы»: просрочка заполнения и SMS; повтор SMS — отдельный анонс.
 */
final class AccountDocumentsExpiryNoticeDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_cabinet_expiry_notices(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-documents-expiry-notice-index"', $html);
        $start = strpos($html, 'id="account-documents-expiry-notice-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="account-documents-resend-sms-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/account-settings/documents', $chunk);
        $this->assertStringContainsString('Срок заполнения истёк', $chunk);
        $this->assertStringContainsString('CLIENT_FILL_EXPIRED_BADGE', $chunk);
        $this->assertStringContainsString('CLIENT_FILL_EXPIRED_NOTICE', $chunk);
        $this->assertStringContainsString('CLIENT_SMS_EXPIRED_NOTICE', $chunk);
        $this->assertStringContainsString('Обратитесь к администратору школы', $chunk);
        $this->assertStringContainsString('Отправить SMS ещё раз', $chunk);
        $this->assertStringContainsString('isAwaitingClientFillExpired', $chunk);
        $this->assertStringContainsString('clientCabinetExpiryNotice', $chunk);
        $this->assertStringContainsString('canClientOpenSigningUrl', $chunk);
        $this->assertStringContainsString('STATUS_EXPIRED', $chunk);
        $this->assertStringContainsString('awaiting_client_fill', $chunk);
        $this->assertStringContainsString('FILL_TTL_DAYS', $chunk);
        $this->assertStringContainsString('account-documents-resend-sms-index', $chunk);
        $this->assertStringContainsString('#openResendModal', $chunk);
        $this->assertStringContainsString('Повторно отправить на подпись', $chunk);
        $this->assertStringContainsString('account-contract-fill §1.4', $chunk);
        $this->assertStringContainsString('ops-contracts-expired-index', $chunk);
        $this->assertStringContainsString('AccountDocumentsExpiryNoticeUxFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsExpiryNoticeAccessFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsExpiryNoticeAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsExpiryNoticeNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsExpiryNoticeDocumentationContractTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);

        $this->assertStringNotContainsString('Обратитесь в организацию', $chunk);
        $this->assertStringNotContainsString('кнопка повторной отправки в кабинете', $chunk);
        $this->assertStringNotContainsString('родителю нужно нажать «Повторно отправить', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $fill = $this->docFile('account-contract-fill.html');
        $contracts = $this->docFile('contracts.html');
        $family = $this->docFile('parents-and-family-cabinet.html');
        $index = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-documents-expiry-notice"', $fill);
        $this->assertStringContainsString('/doc#account-documents-expiry-notice-index', $fill);
        $this->assertStringContainsString('Срок заполнения истёк', $fill);
        $this->assertStringContainsString('Обратитесь к администратору школы', $fill);
        $this->assertStringContainsString('canClientOpenSigningUrl', $fill);

        $this->assertStringContainsString('/doc#account-documents-expiry-notice-index', $contracts);
        $this->assertStringContainsString('account-documents-expiry-notice', $contracts);

        $this->assertStringContainsString('/doc#account-documents-expiry-notice-index', $family);

        $this->assertStringContainsString('/doc#account-documents-expiry-notice-index', $index);
        $opsStart = strpos($index, 'id="ops-contracts-expired-index"');
        $opsEnd = strpos($index, 'id="account-documents-expiry-notice-index"');
        $this->assertNotFalse($opsStart);
        $this->assertNotFalse($opsEnd);
        $opsChunk = substr($index, $opsStart, $opsEnd - $opsStart);
        $this->assertStringContainsString('/doc#account-documents-expiry-notice-index', $opsChunk);
    }

    public function test_catalog_and_controller_title_mention_expiry_notice(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#account-documents-expiry-notice-index', $index);
        $this->assertStringContainsString('просрочка заполнения и SMS', $index);
        $this->assertStringContainsString('Срок заполнения истёк', $controller);
        $this->assertStringContainsString('администратору школы', $controller);
    }

    public function test_live_code_matches_documented_cabinet_expiry_notices(): void
    {
        $root = dirname(__DIR__, 3);
        $model = (string) file_get_contents($root.'/app/Models/Contract.php');
        $blade = (string) file_get_contents($root.'/resources/views/account/documents.blade.php');
        $fill = (string) file_get_contents($root.'/app/Http/Controllers/AccountContractFillController.php');
        $pdf = (string) file_get_contents($root.'/app/Services/Contracts/ContractPdfGenerationService.php');
        $show = (string) file_get_contents($root.'/resources/views/contracts/show.blade.php');

        $this->assertStringContainsString("CLIENT_FILL_EXPIRED_BADGE = 'Срок заполнения истёк'", $model);
        $this->assertStringContainsString('Обратитесь к администратору школы', $model);
        $this->assertStringContainsString('Отправить SMS ещё раз', $model);
        $this->assertStringContainsString('function isAwaitingClientFillExpired', $model);
        $this->assertStringContainsString('function clientCabinetExpiryNotice', $model);
        $this->assertStringContainsString('function canClientOpenSigningUrl', $model);

        $this->assertStringContainsString('isAwaitingClientFillExpired()', $blade);
        $this->assertStringContainsString('clientCabinetExpiryNotice()', $blade);
        $this->assertStringContainsString('canClientOpenSigningUrl()', $blade);
        $this->assertStringContainsString('CLIENT_FILL_EXPIRED_BADGE', $blade);
        $this->assertStringContainsString('alert alert-warning', $blade);
        $this->assertStringContainsString('canClientFill()', $blade);

        $this->assertStringContainsString('isAwaitingClientFillExpired()', $fill);
        $this->assertStringContainsString('CLIENT_FILL_EXPIRED_NOTICE', $fill);
        $this->assertStringContainsString('CLIENT_FILL_EXPIRED_NOTICE', $pdf);
        $this->assertStringContainsString('isAwaitingClientFillExpired()', $pdf);

        $this->assertStringContainsString("id=\"openResendModal\"", $show);
        $this->assertStringContainsString('Повторно отправить на подпись', $show);
        $this->assertStringContainsString("'sent','opened','failed','expired'", $show);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
