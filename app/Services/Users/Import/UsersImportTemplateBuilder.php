<?php

namespace App\Services\Users\Import;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UsersImportTemplateBuilder
{
    public function downloadResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Данные');

        $labels = UsersImportColumns::headerLabels();
        $col = 1;
        foreach ($labels as $label) {
            UsersSpreadsheetCells::setValue($sheet, $col, 1, $label);
            $col++;
        }

        UsersSpreadsheetCells::setValue($sheet, 1, 2, 'Иванов');
        UsersSpreadsheetCells::setValue($sheet, 2, 2, 'Иван');
        UsersSpreadsheetCells::setValue($sheet, 3, 2, 'Группа А');
        UsersSpreadsheetCells::setValue($sheet, 4, 2, 'ООО Пример');
        UsersSpreadsheetCells::setValue($sheet, 5, 2, 'student@example.com');
        UsersSpreadsheetCells::setValue($sheet, 6, 2, '+79001234567');
        UsersSpreadsheetCells::setValue($sheet, 7, 2, '01.09.2015');
        UsersSpreadsheetCells::setValue($sheet, 8, 2, 'да');
        UsersSpreadsheetCells::setValue($sheet, 9, 2, 'parent@example.com');
        UsersSpreadsheetCells::setValue($sheet, 10, 2, 'Иванова');
        UsersSpreadsheetCells::setValue($sheet, 11, 2, 'Мария');
        UsersSpreadsheetCells::setValue($sheet, 12, 2, 'Петровна');
        UsersSpreadsheetCells::setValue($sheet, 13, 2, '79007654321');

        foreach (range(1, count($labels)) as $columnIndex) {
            UsersSpreadsheetCells::setColumnAutoSize($sheet, $columnIndex);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(static function () use ($writer): void {
            $writer->save('php://output');
        }, 'import_users_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
