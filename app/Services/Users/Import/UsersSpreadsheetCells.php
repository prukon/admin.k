<?php

namespace App\Services\Users\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Совместимость с PhpSpreadsheet 2+ (ByColumnAndRow API удалён).
 */
final class UsersSpreadsheetCells
{
    public static function setValue(Worksheet $sheet, int $column, int $row, mixed $value): void
    {
        $sheet->setCellValue([$column, $row], $value);
    }

    public static function getValue(Worksheet $sheet, int $column, int $row): mixed
    {
        return $sheet->getCell([$column, $row])->getValue();
    }

    public static function setColumnAutoSize(Worksheet $sheet, int $column): void
    {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
    }
}
