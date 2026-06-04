<?php

namespace Tests\Unit\Services;

use App\Services\Contracts\ContractDocxPlaceholderFiller;
use Tests\TestCase;
use ZipArchive;

class ContractDocxPlaceholderFillerTest extends TestCase
{
    /** @test */
    public function replaces_cyrillic_and_system_placeholders(): void
    {
        $source = $this->makeDocx('Договор {{parent_full_name}}, ссылка {{documents_url}}');
        $target = tempnam(sys_get_temp_dir(), 'filled_') . '.docx';

        (new ContractDocxPlaceholderFiller())->fill($source, $target, [
            'parent_full_name'    => 'Иванов Иван Иванович',
            'documents_url' => 'https://example.test/documents',
        ]);

        $xml = $this->readDocumentXml($target);

        $this->assertStringContainsString('Иванов Иван Иванович', $xml);
        $this->assertStringContainsString('https://example.test/documents', $xml);
        $this->assertStringNotContainsString('{{parent_full_name}}', $xml);
        $this->assertStringNotContainsString('{{documents_url}}', $xml);

        @unlink($source);
        @unlink($target);
    }

    /** @test */
    public function replaces_placeholders_split_by_word_xml_runs(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'docx_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($source, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        // Word часто разрывает плейсхолдер на несколько w:t
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>Привет {{documents_</w:t></w:r>'
            . '<w:r><w:t>url}}, ученик {{child_</w:t></w:r><w:r><w:t>full_name}}</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        $target = tempnam(sys_get_temp_dir(), 'filled_') . '.docx';
        (new ContractDocxPlaceholderFiller())->fill($source, $target, [
            'documents_url' => 'https://crm.test/docs',
            'child_full_name' => 'Петров Пётр',
        ]);

        $xml = $this->readDocumentXml($target);

        $this->assertStringContainsString('https://crm.test/docs', $xml);
        $this->assertStringContainsString('Петров Пётр', $xml);
        $this->assertStringNotContainsString('{{documents', $xml);
        $this->assertStringNotContainsString('{{child', $xml);

        @unlink($source);
        @unlink($target);
    }

    /** @test */
    public function replaces_documents_url_inside_hyperlink_xml(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'docx_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($source, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>Привет {{documents_</w:t></w:r>'
            . '<w:hyperlink r:id="rId5"><w:r><w:t>url</w:t></w:r></w:hyperlink>'
            . '<w:r><w:t>}}</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        $target = tempnam(sys_get_temp_dir(), 'filled_') . '.docx';
        (new ContractDocxPlaceholderFiller())->fill($source, $target, [
            'documents_url' => 'https://test.kidscrm.online/account-settings/documents',
        ]);

        $xml = $this->readDocumentXml($target);

        $this->assertStringContainsString('https://test.kidscrm.online/account-settings/documents', $xml);
        $this->assertStringNotContainsString('{{documents', $xml);

        @unlink($source);
        @unlink($target);
    }

    private function readDocumentXml(string $docxPath): string
    {
        $zip = new ZipArchive();
        $zip->open($docxPath);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }

    private function makeDocx(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>' . htmlspecialchars($text, ENT_XML1) . '</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        return $path;
    }
}
