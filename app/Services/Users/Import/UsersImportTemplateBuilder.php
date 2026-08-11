<?php

namespace App\Services\Users\Import;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UsersImportTemplateBuilder
{
    public function downloadResponse(bool $includeStudentFullNameGenitive = false): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Данные');

        $labels = UsersImportColumns::headerLabels($includeStudentFullNameGenitive);
        $col = 1;
        foreach ($labels as $label) {
            UsersSpreadsheetCells::setValue($sheet, $col, 1, $label);
            $col++;
        }

        $sampleByKey = [
            UsersImportColumns::STUDENT_LASTNAME => 'Иванов',
            UsersImportColumns::STUDENT_NAME => 'Иван',
            UsersImportColumns::STUDENT_FULL_NAME_GENITIVE => 'Иванова Ивана',
            UsersImportColumns::TEAM => 'Группа А',
            UsersImportColumns::LEGAL_ENTITY => 'ООО Пример',
            UsersImportColumns::STUDENT_EMAIL => 'student@example.com',
            UsersImportColumns::STUDENT_PHONE => '+79001234567',
            UsersImportColumns::BIRTHDAY => '01.09.2015',
            UsersImportColumns::IS_ENABLED => 'да',
            UsersImportColumns::PARENT_EMAIL => 'parent@example.com',
            UsersImportColumns::PARENT_LASTNAME => 'Иванова',
            UsersImportColumns::PARENT_FIRSTNAME => 'Мария',
            UsersImportColumns::PARENT_MIDDLENAME => 'Петровна',
            UsersImportColumns::PARENT_PHONE => '79007654321',
        ];

        $col = 1;
        foreach (array_keys($labels) as $key) {
            UsersSpreadsheetCells::setValue($sheet, $col, 2, $sampleByKey[$key] ?? '');
            $col++;
        }

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
