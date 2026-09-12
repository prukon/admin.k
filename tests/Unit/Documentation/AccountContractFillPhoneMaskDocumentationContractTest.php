<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#account-contract-phone-mask-index совпадает с маской телефона в PDF договора.
 */
final class AccountContractFillPhoneMaskDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_contract_phone_mask_in_pdf(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-contract-phone-mask-index"', $html);
        $start = strpos($html, 'id="account-contract-phone-mask-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-paid-empty-prepaid-attach-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('9062475508', $chunk);
        $this->assertStringContainsString('+7 (906) 247-55-08', $chunk);
        $this->assertStringContainsString('RuPhone::formatForInput', $chunk);
        $this->assertStringContainsString('js-contract-fill-phone', $chunk);
        $this->assertStringContainsString('autoUnmask', $chunk);
        $this->assertStringContainsString('isFillFormPhoneField', $chunk);
        $this->assertStringContainsString('parent_phone', $chunk);
        $this->assertStringContainsString('student_phone', $chunk);
        $this->assertStringContainsString('spouse_phones', $chunk);
        $this->assertStringContainsString('trusted_person_1_contacts', $chunk);
        $this->assertStringContainsString('formatPhoneFieldsForPdf', $chunk);
        $this->assertStringContainsString('composeNameFieldsForPdf', $chunk);
        $this->assertStringContainsString('creation_mode=template', $chunk);
        $this->assertStringContainsString('creation_mode=pdf', $chunk);
        $this->assertStringContainsString('signer_phone', $chunk);
        $this->assertStringContainsString('removeMaskOnSubmit', $chunk);
        $this->assertStringContainsString('js-open-contract-fill-edit', $chunk);
        $this->assertStringContainsString('phone-inputmask:refresh', $chunk);
        $this->assertStringContainsString("errors['fields.parent_phone']", $chunk);
        $this->assertStringContainsString('StudentParentSyncService', $chunk);
        $this->assertStringContainsString('account-contract-fill#contract-phone-mask-pdf', $chunk);
        $this->assertStringContainsString('contract-templates#docx-placeholder-substitution', $chunk);
        $this->assertStringContainsString('AccountContractFillPhoneMaskFeatureTest', $chunk);
        $this->assertStringContainsString('AccountContractFillPhoneMaskAccessFeatureTest', $chunk);
        $this->assertStringContainsString('AccountContractFillPhoneMaskAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('AccountContractFillPhoneMaskNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('AccountContractFillPhoneMaskDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#account-contract-phone-mask-index', $chunk);
        $this->assertStringNotContainsString('HTML5 required', $chunk);
        $this->assertStringNotContainsString('меняет уже подписанный PDF без generate', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $fill = $this->docFile('account-contract-fill.html');
        $templates = $this->docFile('contract-templates.html');
        $contracts = $this->docFile('contracts.html');

        $this->assertStringContainsString('id="contract-phone-mask-pdf"', $fill);
        $this->assertStringContainsString('/doc#account-contract-phone-mask-index', $fill);
        $this->assertStringContainsString('formatPhoneFieldsForPdf', $fill);
        $this->assertStringContainsString('RuPhone::formatForInput', $fill);
        $this->assertStringContainsString('isFillFormPhoneField', $fill);
        $this->assertStringContainsString('AccountContractFillPhoneMaskFeatureTest', $fill);
        $this->assertStringContainsString('AccountContractFillPhoneMaskAccessFeatureTest', $fill);
        $this->assertStringContainsString('AccountContractFillPhoneMaskAjaxContractFeatureTest', $fill);
        $this->assertStringContainsString('AccountContractFillPhoneMaskNonAjaxSafetyNetFeatureTest', $fill);
        $this->assertStringContainsString("errors['fields.parent_phone']", $fill);
        $this->assertStringContainsString('js-open-contract-fill-edit', $fill);
        $this->assertStringContainsString('phone-inputmask:refresh', $fill);
        $this->assertStringContainsString('creation_mode=template', $fill);
        $this->assertStringContainsString('signer_phone', $fill);
        $this->assertStringContainsString('StudentParentSyncService::normalizePhonePart', $fill);

        $this->assertStringContainsString('/doc#account-contract-phone-mask-index', $templates);
        $this->assertStringContainsString('formatPhoneFieldsForPdf', $templates);
        $this->assertStringContainsString('RuPhone::formatForInput', $templates);

        $this->assertStringContainsString('/doc#account-contract-phone-mask-index', $contracts);
        $this->assertStringContainsString('contract-phone-mask-pdf', $contracts);
        $this->assertStringContainsString('creation_mode=template', $templates);
        $this->assertStringContainsString('signer_phone', $templates);

        $family = $this->docFile('parents-and-family-cabinet.html');
        $this->assertStringContainsString('/doc#account-contract-phone-mask-index', $family);
        $this->assertStringContainsString('contract-phone-mask-pdf', $family);
    }

    public function test_catalog_and_controller_title_mention_phone_mask(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#account-contract-phone-mask-index', $index);
        $this->assertStringContainsString('телефон в PDF с маской', $index);

        $this->assertStringContainsString('телефон в PDF с маской +7 (999) 999-99-99', $controller);
        $this->assertStringContainsString('маска телефона в PDF', $controller);
    }

    public function test_live_code_formats_phone_keys_for_pdf(): void
    {
        $root = dirname(__DIR__, 3);
        $presets = (string) file_get_contents($root.'/app/Services/Contracts/ContractTemplateVariablePresets.php');
        $blade = (string) file_get_contents($root.'/resources/views/account/partials/contract-fill-field.blade.php');
        $ruPhone = (string) file_get_contents($root.'/app/Support/RuPhone.php');

        $this->assertStringContainsString('function isFillFormPhoneField', $presets);
        $this->assertStringContainsString('function formatPhoneFieldsForPdf', $presets);
        $this->assertStringContainsString('return self::formatPhoneFieldsForPdf($values)', $presets);
        $this->assertStringContainsString('RuPhone::formatForInput', $presets);

        $this->assertStringContainsString('isFillFormPhoneField($key)', $blade);
        $this->assertStringNotContainsString("str_contains(\$key, 'phone')", $blade);

        $this->assertStringContainsString('+7 (%s) %s-%s-%s', $ruPhone);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
