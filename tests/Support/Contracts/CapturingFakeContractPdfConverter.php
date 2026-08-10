<?php

namespace Tests\Support\Contracts;

use App\Services\Contracts\ContractPdfConverterInterface;
use App\Services\Contracts\FakeContractPdfConverter;
use ZipArchive;

/**
 * Тестовый конвертер: сохраняет word/document.xml заполненного DOCX до удаления tmp.
 */
final class CapturingFakeContractPdfConverter implements ContractPdfConverterInterface
{
    public static ?string $lastDocumentXml = null;

    public function convertDocxToPdf(string $docxAbsolutePath, string $outputDirectory): string
    {
        self::$lastDocumentXml = null;

        $zip = new ZipArchive();
        if ($zip->open($docxAbsolutePath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            self::$lastDocumentXml = is_string($xml) ? $xml : null;
            $zip->close();
        }

        return (new FakeContractPdfConverter())->convertDocxToPdf($docxAbsolutePath, $outputDirectory);
    }

    public static function reset(): void
    {
        self::$lastDocumentXml = null;
    }
}
