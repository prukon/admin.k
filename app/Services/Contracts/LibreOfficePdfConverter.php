<?php

namespace App\Services\Contracts;

use RuntimeException;
use Symfony\Component\Process\Process;

class LibreOfficePdfConverter implements ContractPdfConverterInterface
{
    public function convertDocxToPdf(string $docxAbsolutePath, string $outputDirectory): string
    {
        if (!is_file($docxAbsolutePath)) {
            throw new RuntimeException('DOCX для конвертации не найден.');
        }

        if (!is_dir($outputDirectory) && !@mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
            throw new RuntimeException('Не удалось создать каталог для PDF.');
        }

        $binary = (string) config('contracts.libreoffice_binary', 'libreoffice');
        $timeout = (int) config('contracts.libreoffice_timeout', 120);

        $process = new Process([
            $binary,
            '--headless',
            '--nologo',
            '--nofirststartwizard',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDirectory,
            $docxAbsolutePath,
        ]);
        $process->setTimeout(max(30, $timeout));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'LibreOffice не смог сконвертировать DOCX в PDF: ' . trim($process->getErrorOutput() ?: $process->getOutput())
            );
        }

        $baseName = pathinfo($docxAbsolutePath, PATHINFO_FILENAME);
        $pdfPath = rtrim($outputDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseName . '.pdf';

        if (!is_file($pdfPath)) {
            throw new RuntimeException('PDF после конвертации не найден: ' . $pdfPath);
        }

        return $pdfPath;
    }
}
