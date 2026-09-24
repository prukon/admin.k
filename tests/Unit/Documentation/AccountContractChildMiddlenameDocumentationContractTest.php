<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#account-contract-child-middlename-index совпадает с полем отчества
 * ребёнка в форме договора, сборкой {{child_full_name}} и письмом-приглашением.
 */
final class AccountContractChildMiddlenameDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_child_middlename_in_contract_and_email(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-contract-child-middlename-index"', $html);
        $start = strpos($html, 'id="account-contract-child-middlename-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-ltv-group-mode-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/account-settings/documents', $chunk);
        $this->assertStringContainsString('child_middlename', $chunk);
        $this->assertStringContainsString('users.middlename', $chunk);
        $this->assertStringContainsString('{{child_full_name}}', $chunk);
        $this->assertStringContainsString('fields.child_middlename', $chunk);
        $this->assertStringContainsString('fullNameWithPatronymic', $chunk);
        $this->assertStringContainsString('AccountContractFillSplitNameFeatureTest', $chunk);
        $this->assertStringContainsString('account-contract-fill#child-middlename', $chunk);
    }

    public function test_fill_docs_and_live_code_include_child_middlename(): void
    {
        $root = dirname(__DIR__, 3);
        $fill = $this->docFile('account-contract-fill.html');
        $templates = $this->docFile('contract-templates.html');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/DocumentationController.php');
        $presets = (string) file_get_contents($root.'/app/Services/Contracts/ContractTemplateVariablePresets.php');
        $sync = (string) file_get_contents($root.'/app/Services/Contracts/ContractStudentProfileSyncService.php');
        $mail = (string) file_get_contents($root.'/app/Services/Contracts/ContractInvitationEmailRenderer.php');

        $this->assertStringContainsString('id="child-middlename"', $fill);
        $this->assertStringContainsString('child_middlename', $fill);
        $this->assertStringContainsString('users.middlename', $fill);
        $this->assertStringContainsString('fullNameWithPatronymic', $fill);

        $this->assertStringContainsString('child_middlename', $templates);
        $this->assertStringContainsString('/doc#account-contract-child-middlename-index', $templates);

        $this->assertStringContainsString('child_middlename → {{child_full_name}}', $controller);

        $this->assertStringContainsString('CHILD_MIDDLENAME', $presets);
        $this->assertStringContainsString("\$values[ContractTemplatePrefillSources::CHILD_MIDDLENAME] ?? ''", $presets);
        $this->assertStringContainsString('fullNameWithPatronymic()', $mail);
        $this->assertStringContainsString("ContractTemplatePrefillSources::CHILD_MIDDLENAME", $sync);
        $this->assertStringContainsString("mb_substr(\$middlename, 0, 100)", $sync);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
