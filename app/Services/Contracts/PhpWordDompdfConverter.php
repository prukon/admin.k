<?php

namespace App\Services\Contracts;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use RuntimeException;
use ZipArchive;

/**
 * DOCX → PDF без proc_open (PhpWord + Dompdf, pure PHP).
 *
 * PhpWord HTML-writer для абзацев с Word-нумерацией (w:numPr → ListItemRun)
 * оборачивает каждый run в отдельный &lt;p&gt; — Dompdf рисует это как лишние
 * переносы строк. Перед загрузкой нумерацию снимаем с рабочей копии.
 */
class PhpWordDompdfConverter implements ContractPdfConverterInterface
{
    private const XML_PARTS = [
        'word/document.xml',
        'word/header1.xml',
        'word/header2.xml',
        'word/header3.xml',
        'word/footer1.xml',
        'word/footer2.xml',
        'word/footer3.xml',
    ];

    private static bool $rendererConfigured = false;

    public function convertDocxToPdf(string $docxAbsolutePath, string $outputDirectory): string
    {
        if (!is_file($docxAbsolutePath)) {
            throw new RuntimeException('DOCX для конвертации не найден.');
        }

        if (!is_dir($outputDirectory) && !@mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
            throw new RuntimeException('Не удалось создать каталог для PDF.');
        }

        $this->configureRenderer();

        $prepared = rtrim($outputDirectory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . pathinfo($docxAbsolutePath, PATHINFO_FILENAME)
            . '-phpword-in.docx';

        if (!@copy($docxAbsolutePath, $prepared)) {
            throw new RuntimeException('Не удалось подготовить DOCX для PhpWord.');
        }

        try {
            self::stripWordListNumbering($prepared);
            $phpWord = IOFactory::load($prepared);
        } catch (\Throwable $e) {
            throw new RuntimeException('Не удалось прочитать DOCX: ' . $e->getMessage(), 0, $e);
        } finally {
            @unlink($prepared);
        }

        if (!isset($phpWord)) {
            throw new RuntimeException('Не удалось прочитать DOCX.');
        }

        $baseName = pathinfo($docxAbsolutePath, PATHINFO_FILENAME);
        $pdfPath = rtrim($outputDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseName . '.pdf';

        try {
            $writer = IOFactory::createWriter($phpWord, 'PDF');
            if (method_exists($writer, 'setFont')) {
                $writer->setFont(config('contracts.dompdf_font', 'DejaVu Sans'));
            }
            $writer->save($pdfPath);
        } catch (\Throwable $e) {
            throw new RuntimeException('Не удалось сформировать PDF: ' . $e->getMessage(), 0, $e);
        }

        if (!is_file($pdfPath)) {
            throw new RuntimeException('PDF после конвертации не найден.');
        }

        return $pdfPath;
    }

    private function configureRenderer(): void
    {
        if (self::$rendererConfigured) {
            return;
        }

        $dompdfPath = config('contracts.dompdf_path', base_path('vendor/dompdf/dompdf'));
        if (!is_dir($dompdfPath)) {
            throw new RuntimeException(
                'Dompdf не найден. Установите dompdf/dompdf или укажите CONTRACT_DOMPDF_PATH.'
            );
        }

        Settings::setPdfRendererName(Settings::PDF_RENDERER_DOMPDF);
        Settings::setPdfRendererPath($dompdfPath);
        // Кириллица: встроенный шрифт Dompdf (без него — «??????»).
        Settings::setPdfRendererOptions([
            'font' => config('contracts.dompdf_font', 'DejaVu Sans'),
        ]);

        self::$rendererConfigured = true;
    }

    /**
     * Убирает w:numPr, чтобы PhpWord не читал абзац как ListItemRun.
     * Исходный файл шаблона не трогаем — только переданную копию.
     */
    public static function stripWordListNumbering(string $docxPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            throw new RuntimeException('Не удалось открыть DOCX для подготовки PhpWord.');
        }

        foreach (self::XML_PARTS as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false || $xml === '') {
                continue;
            }

            $stripped = self::stripNumPrFromXml($xml);
            if ($stripped !== $xml) {
                $zip->addFromString($part, $stripped);
            }
        }

        $zip->close();
    }

    public static function stripNumPrFromXml(string $xml): string
    {
        $xml = (string) preg_replace('/<w:numPr\b[^>]*\/>/u', '', $xml);
        $xml = (string) preg_replace('/<w:numPr\b[^>]*>.*?<\/w:numPr>/us', '', $xml);

        return $xml;
    }
}
