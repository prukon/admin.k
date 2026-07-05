<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users\Concerns;

use App\Models\PartnerLegalEntity;
use App\Services\Users\Import\UsersImportColumns;
use App\Services\Users\Import\UsersSpreadsheetCells;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait UsersImportTestHelpers
{
    protected function requirePhpSpreadsheet(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet не установлен. Выполните composer install от пользователя prukon.');
        }
    }

    protected function createImportLegalEntity(): PartnerLegalEntity
    {
        return PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Импорт ЮЛ',
            'organization_name' => 'ООО Импорт Тест',
            'is_default' => true,
            'is_enabled' => true,
        ]);
    }

    /**
     * @param array<string, string|null> $overrides
     * @return array<string, string|null>
     */
    protected function sampleImportRow(PartnerLegalEntity $legalEntity, array $overrides = []): array
    {
        return array_merge([
            'Фамилия ученика' => 'Тестов',
            'Имя ученика' => 'Тест',
            'Группа' => 'Группа импорта',
            'Юр. лицо' => $legalEntity->displayTitle(),
            'Email ученика' => null,
            'Телефон ученика' => null,
            'Дата рождения' => null,
            'Активен' => 'да',
            'Email родителя' => null,
            'Фамилия родителя' => null,
            'Имя родителя' => null,
            'Отчество родителя' => null,
            'Телефон родителя' => null,
        ], $overrides);
    }

    /**
     * @param list<array<string, string|null>> $rows
     */
    protected function makeImportFile(array $rows): UploadedFile
    {
        $headers = array_values(UsersImportColumns::headerLabels());

        return $this->buildImportXlsx($headers, $rows);
    }

    /**
     * @param list<string> $headers
     */
    protected function makeImportFileWithHeaders(array $headers): UploadedFile
    {
        return $this->buildImportXlsx($headers, []);
    }

    /**
     * @param list<string> $headers
     * @param list<array<string, string|null>> $rows
     */
    protected function buildImportXlsx(array $headers, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $index => $header) {
            UsersSpreadsheetCells::setValue($sheet, $index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            foreach ($headers as $index => $header) {
                UsersSpreadsheetCells::setValue($sheet, $index + 1, $rowNumber, $row[$header] ?? '');
            }
            $rowNumber++;
        }

        $path = tempnam(sys_get_temp_dir(), 'users_import_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function importAjaxHeaders(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ];
    }

    /**
     * @return array{import_token: string, preview: array<string, mixed>}
     */
    protected function previewImportFile(UploadedFile $file): array
    {
        $response = $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('valid', true);

        $json = $response->json();

        return [
            'import_token' => (string) ($json['import_token'] ?? ''),
            'preview' => $json,
        ];
    }
}
