<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#account-contract-fill-modal-ux-index совпадает с модалкой fill
 * и не утверждает, что переименовали паспорт в профиле ЛК/админки.
 */
final class AccountContractFillModalUxDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_fill_modal_passport_email_and_field_errors(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-contract-fill-modal-ux-index"', $html);
        $start = strpos($html, 'id="account-contract-fill-modal-ux-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-signed-in-app-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#contractFillModal', $chunk);
        $this->assertStringContainsString('/account-settings/documents', $chunk);
        $this->assertStringContainsString('Паспорт (серия и номер)', $chunk);
        $this->assertStringContainsString('fillFormFieldLabel', $chunk);
        $this->assertStringContainsString('Родитель: паспорт (серия и номер)', $chunk);
        $this->assertStringContainsString('parent_passport_issued', $chunk);
        $this->assertStringContainsString('parents.email', $chunk);
        $this->assertStringContainsString('users.email', $chunk);
        $this->assertStringContainsString('не</b> подставляется', $chunk);
        $this->assertStringContainsString('непустой <code>parent_email</code>', $chunk);
        $this->assertStringContainsString('parents.email', $chunk);
        $this->assertStringContainsString('novalidate', $chunk);
        $this->assertStringContainsString('без HTML5 <code>required</code>', $chunk);
        $this->assertStringContainsString("errors['fields.{key}']", $chunk);
        $this->assertStringContainsString('data-error-for', $chunk);
        $this->assertStringContainsString('js-open-contract-fill-edit', $chunk);
        $this->assertStringContainsString('account-contract-fill#fill-modal-passport-email-errors', $chunk);
        $this->assertStringContainsString('AccountContractFillPassportEmailUxFeatureTest', $chunk);
        $this->assertStringContainsString('AccountContractFillModalUxDocumentationContractTest', $chunk);

        $this->assertStringContainsString('/account-settings/user/edit', $chunk);
        $this->assertStringContainsString('в ЛК подпись «Паспорт»', $chunk);
        $this->assertStringContainsString('Паспорт родителя', $chunk);
        $this->assertStringContainsString('только в модалке договора', $chunk);

        $this->assertStringNotContainsString('HTML5 required', $chunk);
        $this->assertStringNotContainsString('во всех формах CRM', $chunk);
        $this->assertStringNotContainsString('переименовали паспорт в профиле', $chunk);
        $this->assertStringNotContainsString('loadContractFill(contractId, true).done', $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_keep_profile_passport_label(): void
    {
        $fill = $this->docFile('account-contract-fill.html');
        $contracts = $this->docFile('contracts.html');
        $templates = $this->docFile('contract-templates.html');
        $family = $this->docFile('parents-and-family-cabinet.html');

        $this->assertStringContainsString('id="fill-modal-passport-email-errors"', $fill);
        $this->assertStringContainsString('/doc#account-contract-fill-modal-ux-index', $fill);
        $this->assertStringContainsString('Паспорт (серия и номер)', $fill);
        $this->assertStringContainsString('/account-settings/user/edit</code> — «Паспорт»', $fill);
        $this->assertStringContainsString('Паспорт родителя', $fill);
        $this->assertStringContainsString('parentEmailOrStudentFallback', $fill);
        $this->assertStringContainsString('novalidate', $fill);
        $this->assertStringContainsString('loadContractFill(…).done(…)', $fill);
        $this->assertStringContainsString('не форма профиля', $fill);

        $this->assertStringContainsString('/doc#account-contract-fill-modal-ux-index', $contracts);
        $this->assertStringContainsString('fill-modal-passport-email-errors', $contracts);

        $this->assertStringContainsString('/doc#account-contract-fill-modal-ux-index', $templates);
        $this->assertStringContainsString('не форма профиля ЛК', $templates);

        $this->assertStringContainsString('/doc#account-contract-fill-modal-ux-index', $family);
        $this->assertStringContainsString('Паспорт (серия и номер)', $family);
        $this->assertStringContainsString('без этого переименования', $family);
    }

    public function test_catalog_and_controller_title_mention_fill_modal_ux(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#account-contract-fill-modal-ux-index', $index);
        $this->assertStringContainsString('паспорт (серия и номер)', $index);
        $this->assertStringContainsString('fallback <code>parent_email</code>', $index);
        $this->assertStringContainsString('ошибки Laravel под полями', $index);

        $this->assertStringContainsString('паспорт (серия и номер)', $controller);
        $this->assertStringContainsString('fallback parent_email', $controller);
        $this->assertStringContainsString('ошибки Laravel под полями', $controller);
        $this->assertStringContainsString('колокольчик админам при signed', $controller);
    }

    public function test_live_code_matches_documented_fill_modal_ux_and_keeps_profile_labels(): void
    {
        $root = dirname(__DIR__, 3);
        $presets = (string) file_get_contents($root.'/app/Services/Contracts/ContractTemplateVariablePresets.php');
        $prefill = (string) file_get_contents($root.'/app/Services/Contracts/ContractPrefillResolver.php');
        $generate = (string) file_get_contents($root.'/app/Http/Requests/Account/AccountContractGenerateRequest.php');
        $field = (string) file_get_contents($root.'/resources/views/account/partials/contract-fill-field.blade.php');
        $content = (string) file_get_contents($root.'/resources/views/account/partials/contract-fill-content.blade.php');
        $docsJs = (string) file_get_contents($root.'/resources/views/account/documents.blade.php');
        $accountUsers = (string) file_get_contents($root.'/resources/views/account/users.blade.php');
        $adminParent = (string) file_get_contents($root.'/resources/views/admin/users/_parent_form.blade.php');
        $sync = (string) file_get_contents($root.'/app/Services/Users/StudentParentSyncService.php');

        $this->assertStringContainsString("'label'            => 'Родитель: паспорт (серия и номер)'", $presets);
        $this->assertStringContainsString("mb_strtolower(\$label, 'UTF-8') === 'паспорт'", $presets);
        $this->assertStringContainsString("return 'Паспорт (серия и номер)'", $presets);

        $this->assertStringContainsString('parentEmailOrStudentFallback', $prefill);
        $this->assertStringContainsString("\$parentFields['parent_email']", $prefill);
        $this->assertStringContainsString('$student->email', $prefill);

        $this->assertStringContainsString('fillFormFieldLabel', $generate);

        $this->assertStringContainsString('data-error-for="fields.{{ $key }}"', $field);
        $this->assertStringContainsString("'required' => false", $field);
        $this->assertStringNotContainsString('{{ $required ? \'required\' : \'\' }}', $field);

        $this->assertStringContainsString('novalidate', $content);
        $this->assertStringContainsString('data-error-for="signer_lastname"', $content);

        $this->assertStringContainsString('showFillAjaxErrors', $docsJs);
        $this->assertStringContainsString("xhr.status === 422", $docsJs);
        $this->assertStringContainsString("js-open-contract-fill-edit", $docsJs);
        $this->assertStringNotContainsString('loadContractFill(contractId, true).done(function () {', $docsJs);

        $this->assertStringContainsString('<label for="parent_passport" class="form-label">Паспорт</label>', $accountUsers);
        $this->assertStringContainsString('Паспорт родителя', $adminParent);
        $this->assertStringNotContainsString('Паспорт (серия и номер)', $accountUsers);
        $this->assertStringNotContainsString('Паспорт (серия и номер)', $adminParent);

        $this->assertStringContainsString("'parent_email'            => 'parent_email'", $sync);
    }

    public function test_docs_do_not_claim_profile_form_got_student_email_fallback(): void
    {
        $fill = $this->docFile('account-contract-fill.html');
        $index = $this->docFile('index.html');
        $start = strpos($index, 'id="account-contract-fill-modal-ux-index"');
        $end = strpos($index, 'id="contract-signed-in-app-index"');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $chunk = substr($index, $start, $end - $start);

        $this->assertStringContainsString('не в форме профиля', $chunk);
        $this->assertStringContainsString('не форма <code>/account-settings/user/edit</code>', $fill);
        $this->assertStringNotContainsString('в форме профиля ЛК подставляется users.email', $fill);
        $this->assertStringNotContainsString('в форме профиля ЛК подставляется users.email', $chunk);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
