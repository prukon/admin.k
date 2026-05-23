<?php

namespace App\Services\Contracts;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use RuntimeException;

/**
 * DOCX → PDF без proc_open (PhpWord + Dompdf, pure PHP).
 */
class PhpWordDompdfConverter implements ContractPdfConverterInterface
{
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

        try {
            $phpWord = IOFactory::load($docxAbsolutePath);
        } catch (\Throwable $e) {
            throw new RuntimeException('Не удалось прочитать DOCX: ' . $e->getMessage(), 0, $e);
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
}
