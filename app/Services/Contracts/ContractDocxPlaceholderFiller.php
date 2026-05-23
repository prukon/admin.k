<?php

namespace App\Services\Contracts;

use RuntimeException;
use ZipArchive;

/**
 * Подстановка значений в плейсхолдеры {{key}} внутри DOCX (word/*.xml).
 */
class ContractDocxPlaceholderFiller
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

    /**
     * @param array<string, string> $values key => text
     */
    public function fill(string $sourceDocxPath, string $targetDocxPath, array $values): void
    {
        if (!is_file($sourceDocxPath)) {
            throw new RuntimeException('Исходный DOCX не найден.');
        }

        if (!@copy($sourceDocxPath, $targetDocxPath)) {
            throw new RuntimeException('Не удалось скопировать DOCX для заполнения.');
        }

        $zip = new ZipArchive();
        if ($zip->open($targetDocxPath) !== true) {
            throw new RuntimeException('Не удалось открыть копию DOCX.');
        }

        foreach (self::XML_PARTS as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false || $xml === '') {
                continue;
            }

            $zip->addFromString($part, $this->replaceInXml($xml, $values));
        }

        $zip->close();
    }

    /**
     * @param array<string, string> $values
     */
    private function replaceInXml(string $xml, array $values): string
    {
        return DocxPlaceholderSupport::replaceValuesInXml($xml, $values);
    }
}
