<?php

namespace App\Services\Contracts;

/**
 * Выбор конвертера DOCX→PDF с учётом настроек и возможностей PHP (proc_open).
 */
class ContractPdfConverterResolver
{
    public function resolve(): ContractPdfConverterInterface
    {
        $driver = (string) config('contracts.pdf_converter', 'auto');

        if ($driver === 'fake') {
            return new FakeContractPdfConverter();
        }

        if ($driver === 'phpword') {
            return new PhpWordDompdfConverter();
        }

        if ($driver === 'libreoffice' && $this->canRunProcesses()) {
            return new LibreOfficePdfConverter();
        }

        if ($driver === 'libreoffice' && !$this->canRunProcesses()) {
            return new PhpWordDompdfConverter();
        }

        // auto: LibreOffice если доступен proc_open, иначе PhpWord+Dompdf
        if ($this->canRunProcesses()) {
            return new LibreOfficePdfConverter();
        }

        return new PhpWordDompdfConverter();
    }

    public function canRunProcesses(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !in_array('proc_open', $disabled, true);
    }
}
