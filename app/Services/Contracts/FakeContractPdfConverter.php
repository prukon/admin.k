<?php

namespace App\Services\Contracts;

/**
 * Для тестов: создаёт минимальный валидный PDF без LibreOffice.
 */
class FakeContractPdfConverter implements ContractPdfConverterInterface
{
    public function convertDocxToPdf(string $docxAbsolutePath, string $outputDirectory): string
    {
        if (!is_dir($outputDirectory) && !@mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
            throw new \RuntimeException('Не удалось создать каталог для PDF.');
        }

        $baseName = pathinfo($docxAbsolutePath, PATHINFO_FILENAME);
        $pdfPath = rtrim($outputDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseName . '.pdf';

        $content = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        file_put_contents($pdfPath, $content);

        return $pdfPath;
    }
}
