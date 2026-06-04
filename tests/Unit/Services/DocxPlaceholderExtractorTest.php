<?php

namespace Tests\Unit\Services;

use App\Services\Contracts\DocxPlaceholderExtractor;
use Tests\TestCase;
use ZipArchive;

class DocxPlaceholderExtractorTest extends TestCase
{
    /** @test */
    public function extracts_unique_placeholders_from_docx(): void
    {
        $path = $this->makeDocxWithText('Договор {{parent_full_name}} тел. {{phone}} и снова {{parent_full_name}}.');

        $extractor = new DocxPlaceholderExtractor();
        $keys = $extractor->extractFromPath($path);

        $this->assertSame(['parent_full_name', 'phone'], $keys);

        @unlink($path);
    }

    /** @test */
    public function build_fields_schema_merges_previous_labels(): void
    {
        $extractor = new DocxPlaceholderExtractor();
        $schema = $extractor->buildFieldsSchema(['phone'], [
            ['key' => 'phone', 'label' => 'Телефон родителя', 'required' => false, 'prefill_source' => 'parent_phone'],
        ]);

        $this->assertSame('Телефон родителя', $schema[0]['label']);
        $this->assertFalse($schema[0]['required']);
        $this->assertSame('parent_phone', $schema[0]['prefill_source']);
    }

    private function makeDocxWithText(string $text): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>' . htmlspecialchars($text, ENT_XML1) . '</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        return $tmp;
    }
}
