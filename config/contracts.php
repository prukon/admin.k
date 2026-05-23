<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Конвертация DOCX → PDF
    |--------------------------------------------------------------------------
    | auto       — LibreOffice, если доступен proc_open; иначе PhpWord+Dompdf
    | libreoffice — только LibreOffice (нужен proc_open)
    | phpword    — PhpWord + Dompdf (без proc_open, подходит для shared hosting)
    | fake       — заглушка для тестов
    */
    'pdf_converter' => env('CONTRACT_PDF_CONVERTER', 'auto'),

    'libreoffice_binary' => env('LIBREOFFICE_BINARY', 'libreoffice'),

    'libreoffice_timeout' => (int) env('LIBREOFFICE_TIMEOUT', 120),

    'dompdf_path' => env('CONTRACT_DOMPDF_PATH', base_path('vendor/dompdf/dompdf')),

    /** Шрифт Dompdf с поддержкой кириллицы */
    'dompdf_font' => env('CONTRACT_DOMPDF_FONT', 'DejaVu Sans'),
];
