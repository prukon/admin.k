<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class AccountContractGenitiveFullNameDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_genitive_full_name_rule(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="account-contract-genitive-full-name-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-template-context-placeholders-index"');
        $this->assertNotFalse($end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/account-settings/documents', $chunk);
        $this->assertStringContainsString('parent_full_name_genitive', $chunk);
        $this->assertStringContainsString('child_full_name_genitive', $chunk);
        $this->assertStringContainsString('GenitiveFullName', $chunk);
        $this->assertStringContainsString('AccountContractFillGenitiveFullNameFeatureTest', $chunk);
        $this->assertStringContainsString('account-contract-fill#child-middlename', $chunk);
    }

    public function test_fill_docs_and_request_include_genitive_rule(): void
    {
        $root = dirname(__DIR__, 3);
        $fill = $this->docFile('account-contract-fill.html');
        $request = (string) file_get_contents($root.'/app/Http/Requests/Account/AccountContractGenerateRequest.php');
        $blade = (string) file_get_contents($root.'/resources/views/account/partials/contract-fill-field.blade.php');

        $this->assertStringContainsString('GenitiveFullName', $fill);
        $this->assertStringContainsString('new GenitiveFullName', $request);
        $this->assertStringContainsString('GenitiveFullName::HINT', $blade);
        $this->assertStringContainsString('isFillFormGenitiveFullNameField', $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
