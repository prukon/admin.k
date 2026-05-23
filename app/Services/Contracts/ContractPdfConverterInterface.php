<?php

namespace App\Services\Contracts;

interface ContractPdfConverterInterface
{
    /**
     * Конвертирует DOCX в PDF. Возвращает абсолютный путь к PDF.
     */
    public function convertDocxToPdf(string $docxAbsolutePath, string $outputDirectory): string;
}
