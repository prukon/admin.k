<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-template-email-default-link-index совпадает с кнопкой
 * «По умолчанию» в модалке письма шаблона.
 */
final class ContractTemplateEmailDefaultLinkDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_default_email_documents_url(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-template-email-default-link-index"', $html);
        $start = strpos($html, 'id="contract-template-email-default-link-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="school-leads-filter-select2-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('template-email-reset-defaults', $chunk);
        $this->assertStringContainsString('ContractTemplateEmailDefaults', $chunk);
        $this->assertStringContainsString('href="{{documents_url}}"', $chunk);
        $this->assertStringContainsString('student={user_id}', $chunk);
        $this->assertStringContainsString('fill={contract_id}', $chunk);
        $this->assertStringContainsString('preservePlaceholderLinkUrl', $chunk);
        $this->assertStringContainsString('restoreDocumentsUrlHrefs', $chunk);
        $this->assertStringContainsString('account-documents-family-link-index', $chunk);
        $this->assertStringContainsString('contract-templates §5.3', $chunk);
        $this->assertStringContainsString('ContractTemplateEmailDefaultLinkDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#contract-template-email-default-link-index', $chunk);
        $this->assertStringNotContainsString('{{student_id}}', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $templates = $this->docFile('contract-templates.html');

        $this->assertStringContainsString('id="53-email-edittemplateemailmodal"', $templates);
        $this->assertStringContainsString('/doc#contract-template-email-default-link-index', $templates);
        $this->assertStringContainsString('preservePlaceholderLinkUrl', $templates);
        $this->assertStringContainsString('template-email-reset-defaults', $templates);
        $this->assertStringContainsString('{{documents_url}}', $templates);
    }

    public function test_catalog_and_controller_title_mention_default_email_link(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#contract-template-email-default-link-index', $index);
        $this->assertStringContainsString('«По умолчанию» → <code>{{documents_url}}</code>', $index);

        $this->assertStringContainsString('«По умолчанию» → {{documents_url}}', $controller);
    }

    public function test_live_code_keeps_documents_url_placeholder_in_defaults_and_summernote(): void
    {
        $root = dirname(__DIR__, 3);
        $defaults = (string) file_get_contents($root.'/app/Services/Contracts/ContractTemplateEmailDefaults.php');
        $js = (string) file_get_contents($root.'/resources/views/contract-templates/partials/email-summernote-init.blade.php');
        $hint = (string) file_get_contents($root.'/resources/views/contract-templates/partials/email-placeholders-hint.blade.php');
        $modal = (string) file_get_contents($root.'/resources/views/contract-templates/partials/email-edit-modal.blade.php');

        $this->assertStringContainsString("PLACEHOLDER_DOCUMENTS_URL = '{{documents_url}}'", $defaults);
        $this->assertStringContainsString('self::PLACEHOLDER_DOCUMENTS_URL', $defaults);
        $this->assertStringNotContainsString('?student=', $defaults);

        $this->assertStringContainsString('preservePlaceholderLinkUrl', $js);
        $this->assertStringContainsString('restoreDocumentsUrlHrefs', $js);
        $this->assertStringContainsString('onCreateLink: preservePlaceholderLinkUrl', $js);
        $this->assertStringContainsString('applyDefaultEmailText', $js);

        $this->assertStringContainsString('&#123;&#123;documents_url&#125;&#125;', $hint);
        $this->assertStringContainsString('?student=', $hint);
        $this->assertStringContainsString('email-placeholders-hint', $modal);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
