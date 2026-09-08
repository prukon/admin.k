<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#account-documents-family-link-index совпадает с семейным контекстом
 * «Мои документы» и deep-link из письма.
 */
final class AccountDocumentsFamilyLinkDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_family_documents_link(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-documents-family-link-index"', $html);
        $start = strpos($html, 'id="account-documents-family-link-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-status-memo-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/account-settings/documents', $chunk);
        $this->assertStringContainsString('student={user_id}', $chunk);
        $this->assertStringContainsString('fill={contract_id}', $chunk);
        $this->assertStringContainsString('activeStudent()', $chunk);
        $this->assertStringContainsString('canAccessContract', $chunk);
        $this->assertStringContainsString('accessibleStudents', $chunk);
        $this->assertStringContainsString('ContractInvitationEmailRenderer::documentsUrl', $chunk);
        $this->assertStringContainsString('без query', $chunk);
        $this->assertStringContainsString('не сумма по семье', $chunk);
        $this->assertStringContainsString('AccountContractGenerateRequest', $chunk);
        $this->assertStringContainsString('url.intended', $chunk);
        $this->assertStringContainsString('ContractInvitationEmailFeatureTest', $chunk);
        $this->assertStringContainsString('даже если в URL есть <code>fill</code>', $chunk);
        $this->assertStringContainsString('Отключённый брат/сестра', $chunk);
        $this->assertStringContainsString('account-contract-fill §1.2', $chunk);
        $this->assertStringContainsString('AccountDocumentsFamilyContextFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsFamilyLinkDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#account-documents-family-link-index', $chunk);
        $this->assertStringNotContainsString('сумма по всем детям', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $fill = $this->docFile('account-contract-fill.html');
        $family = $this->docFile('parents-and-family-cabinet.html');
        $templates = $this->docFile('contract-templates.html');

        $this->assertStringContainsString('id="account-documents-family-link"', $fill);
        $this->assertStringContainsString('/doc#account-documents-family-link-index', $fill);
        $this->assertStringContainsString('?student={user_id}', $fill);
        $this->assertStringContainsString('fill={contract_id}', $fill);
        $this->assertStringContainsString('без query', $fill);
        $this->assertStringContainsString('AccountContractGenerateRequest', $fill);
        $this->assertStringContainsString('url.intended', $fill);
        $this->assertStringContainsString('POST /cabinet/active-student', $fill);

        $this->assertStringContainsString('/doc#account-documents-family-link-index', $family);
        $this->assertStringContainsString('GET /account-settings/documents', $family);
        $this->assertStringContainsString('canAccessContract', $family);

        $this->assertStringContainsString('/doc#account-documents-family-link-index', $templates);
        $this->assertStringContainsString('student={user_id}', $templates);
    }

    public function test_catalog_and_controller_title_mention_family_documents_link(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#account-documents-family-link-index', $index);
        $this->assertStringContainsString('семейная ссылка из письма на нужного ребёнка', $index);

        $this->assertStringContainsString('семейная ссылка ?student=&fill=', $controller);
    }

    public function test_live_code_switches_only_on_student_query_and_keeps_pdf_generic(): void
    {
        $root = dirname(__DIR__, 3);
        $renderer = (string) file_get_contents($root.'/app/Services/Contracts/ContractInvitationEmailRenderer.php');
        $documents = (string) file_get_contents($root.'/app/Http/Controllers/AccountDocumentsController.php');
        $pdf = (string) file_get_contents($root.'/app/Services/Contracts/ContractTemplateSystemPlaceholders.php');
        $middleware = (string) file_get_contents($root.'/app/Http/Middleware/EnsureContractBelongsToCurrentUser.php');
        $counter = (string) file_get_contents($root.'/app/Providers/AppServiceProvider.php');

        $this->assertStringContainsString("function documentsUrl", $renderer);
        $this->assertStringContainsString("'student' => (int) \$student->id", $renderer);
        $this->assertStringContainsString("'fill'    => (int) \$contract->id", $renderer);

        $this->assertStringContainsString('applyEmailStudentContext', $documents);
        $this->assertStringContainsString('canAccessStudent', $documents);
        $this->assertStringContainsString('activeStudent($actor)', $documents);

        $this->assertStringContainsString("url('/account-settings/documents')", $pdf);
        $this->assertStringNotContainsString("'student'", $pdf);

        $this->assertStringContainsString('canAccessContract', $middleware);

        $this->assertStringContainsString('activeStudent($user)', $counter);

        $generateRequest = (string) file_get_contents($root.'/app/Http/Requests/Account/AccountContractGenerateRequest.php');
        $this->assertStringContainsString('canAccessContract', $generateRequest);
        $this->assertStringNotContainsString('=== (int) $this->user()?->id', $generateRequest);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
