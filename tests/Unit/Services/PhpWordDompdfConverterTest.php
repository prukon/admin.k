<?php

namespace Tests\Unit\Services;

use App\Services\Contracts\PhpWordDompdfConverter;
use PhpOffice\PhpWord\IOFactory;
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

    /** @test */
    public function strip_num_pr_removes_word_list_numbering_from_xml(): void
    {
        $xml = '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>'
            . '<w:ind w:firstLine="567"/></w:pPr><w:r><w:t>Текст</w:t></w:r></w:p>';

        $stripped = PhpWordDompdfConverter::stripNumPrFromXml($xml);

        $this->assertStringNotContainsString('w:numPr', $stripped);
        $this->assertStringContainsString('w:ind', $stripped);
        $this->assertStringContainsString('Текст', $stripped);
    }

    /** @test */
    public function numbered_paragraph_with_split_runs_stays_one_html_paragraph(): void
    {
        $runs = [
            'Исполнитель предоставляет услуги ',
            '(далее – «',
            'Ребенок',
            '»)',
            ' в форме занятий.',
        ];
        $sentence = implode('', $runs);
        $docx = $this->makeNumberedMultiRunDocx($runs);

        $htmlBefore = $this->docxToHtml($docx);
        $this->assertGreaterThan(
            3,
            substr_count($htmlBefore, '<p'),
            'Без фикса PhpWord должен разорвать numbered paragraph на несколько <p>'
        );

        PhpWordDompdfConverter::stripWordListNumbering($docx);
        $this->assertStringNotContainsString('w:numPr', $this->readDocumentXml($docx));

        $htmlAfter = $this->docxToHtml($docx);
        $plainParagraphs = $this->plainParagraphs($htmlAfter);
        $joined = implode(' ', $plainParagraphs);

        $this->assertStringContainsString($sentence, $joined);
        $matching = array_values(array_filter(
            $plainParagraphs,
            static fn (string $p): bool => str_contains($p, $sentence)
        ));
        $this->assertCount(1, $matching, 'Фраза должна остаться одним HTML-абзацем, а не набором <p> по run.');

        @unlink($docx);
    }

    /** @test */
    public function convert_does_not_mutate_source_docx(): void
    {
        $runs = ['Абзац с ', 'нумерацией.'];
        $docx = $this->makeNumberedMultiRunDocx($runs);
        $xmlBefore = $this->readDocumentXml($docx);

        $outDir = sys_get_temp_dir() . '/contract_pdf_' . uniqid();
        @mkdir($outDir, 0775, true);

        $pdf = (new PhpWordDompdfConverter())->convertDocxToPdf($docx, $outDir);

        $this->assertSame($xmlBefore, $this->readDocumentXml($docx));
        $this->assertFileExists($pdf);

        @unlink($docx);
        @unlink($pdf);
        @rmdir($outDir);
    }

    /**
     * @param list<string> $runTexts
     */
    private function makeNumberedMultiRunDocx(array $runTexts): string
    {
        $runsXml = '';
        foreach ($runTexts as $text) {
            $runsXml .= '<w:r><w:t xml:space="preserve">' . htmlspecialchars($text, ENT_XML1) . '</w:t></w:r>';
        }

        $body = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>'
            . $runsXml
            . '</w:p></w:body></w:document>';

        return $this->zipDocx(['word/document.xml' => $body]);
    }

    private function makeDocx(string $text): string
    {
        $body = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>' . htmlspecialchars($text, ENT_XML1) . '</w:t></w:r></w:p></w:body></w:document>';

        return $this->zipDocx(['word/document.xml' => $body]);
    }

    /**
     * @param array<string, string> $parts
     */
    private function zipDocx(array $parts): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString(
            '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>'
        );
        $zip->addFromString(
            '_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>'
        );
        foreach ($parts as $name => $xml) {
            $zip->addFromString($name, $xml);
        }
        $zip->close();

        return $path;
    }

    private function docxToHtml(string $docxPath): string
    {
        $phpWord = IOFactory::load($docxPath);
        $writer = IOFactory::createWriter($phpWord, 'HTML');
        $tmp = tempnam(sys_get_temp_dir(), 'html_');
        $writer->save($tmp);
        $html = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $html;
    }

    /**
     * @return list<string>
     */
    private function plainParagraphs(string $html): array
    {
        if (!preg_match_all('/<p\b[^>]*>(.*?)<\/p>/us', $html, $matches)) {
            return [];
        }

        $out = [];
        foreach ($matches[1] as $inner) {
            $plain = html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
            $plain = trim($plain);
            if ($plain !== '' && $plain !== "\u{00a0}") {
                $out[] = $plain;
            }
        }

        return $out;
    }

    private function readDocumentXml(string $docxPath): string
    {
        $zip = new ZipArchive();
        $zip->open($docxPath);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }
}
