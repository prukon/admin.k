<?php

namespace Tests\Unit\Services;

use App\Services\Contracts\PhpWordDompdfConverter;
use Tests\TestCase;
use ZipArchive;

class PhpWordDompdfConverterTest extends TestCase
{
    /** @test */
    public function converts_simple_docx_to_pdf_without_proc_open(): void
    {
        $docx = $this->makeDocx('Договор №1. Ученик: тест кириллицы.');
        $outDir = sys_get_temp_dir() . '/contract_pdf_' . uniqid();
        @mkdir($outDir, 0775, true);

        $converter = new PhpWordDompdfConverter();
        $pdf = $converter->convertDocxToPdf($docx, $outDir);

        $this->assertFileExists($pdf);
        $this->assertStringEndsWith('.pdf', $pdf);
        $this->assertGreaterThan(500, filesize($pdf));

        @unlink($docx);
        @unlink($pdf);
        @rmdir($outDir);
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
