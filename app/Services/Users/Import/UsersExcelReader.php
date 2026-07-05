<?php

namespace App\Services\Users\Import;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class UsersImportNormalizer
{
    public static function normalizeText(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim(preg_replace('/\s+/u', ' ', $value));

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $maxLength);
    }

    public static function normalizeEmail(?string $value): ?string
    {
        $email = self::normalizeText($value, 255);

        return $email !== null ? mb_strtolower($email) : null;
    }

    public static function normalizeRuPhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        if (! str_starts_with($digits, '7')) {
            $digits = '7' . $digits;
        }

        $digits = substr($digits, 0, 11);
        if (strlen($digits) !== 11 || ! str_starts_with($digits, '7')) {
            return null;
        }

        return '+7' . substr($digits, 1);
    }

    public static function normalizeParentPhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return $digits !== '' ? mb_substr($digits, 0, 20) : null;
    }

    public static function parseBirthday(mixed $rawValue): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        if (is_numeric($rawValue)) {
            try {
                $date = ExcelDate::excelToDateTimeObject((float) $rawValue);

                return Carbon::instance($date)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $value = trim((string) $rawValue);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function parseIsEnabled(mixed $rawValue): bool
    {
        if ($rawValue === null || $rawValue === '') {
            return true;
        }

        $value = mb_strtolower(trim((string) $rawValue));

        if (in_array($value, ['нет', 'no', '0', 'false', 'н'], true)) {
            return false;
        }

        return true;
    }

    public static function normalizeTeamTitle(?string $value): ?string
    {
        return self::normalizeText($value, 255);
    }

    /**
     * @return non-empty-string
     */
    public static function parentFingerprint(array $fields): string
    {
        $normalized = [];
        foreach ($fields as $key => $value) {
            $normalized[$key] = $value ?? '';
        }

        ksort($normalized);

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}

final class UsersExcelReader
{
    /**
     * @return array{rows: list<UsersImportRow>, errors: list<UsersImportRowError>}
     */
    public function read(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        $headerMap = $this->resolveHeaderMap($sheet);
        $errors = [];

        foreach (UsersImportColumns::requiredColumnKeys() as $requiredKey) {
            if (! isset($headerMap[$requiredKey])) {
                $errors[] = new UsersImportRowError(
                    1,
                    UsersImportColumns::headerLabels()[$requiredKey],
                    'Отсутствует обязательный столбец «' . UsersImportColumns::headerLabels()[$requiredKey] . '».'
                );
            }
        }

        if ($errors !== []) {
            return ['rows' => [], 'errors' => $errors];
        }

        $rows = [];
        $highestRow = (int) $sheet->getHighestDataRow();

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $raw = $this->readRow($sheet, $rowNumber, $headerMap);

            if ($this->isRowCompletelyEmpty($raw)) {
                continue;
            }

            $rows[] = new UsersImportRow(
                rowNumber: $rowNumber,
                studentLastname: (string) ($raw[UsersImportColumns::STUDENT_LASTNAME] ?? ''),
                studentName: (string) ($raw[UsersImportColumns::STUDENT_NAME] ?? ''),
                teamTitle: (string) ($raw[UsersImportColumns::TEAM] ?? ''),
                legalEntityTitle: (string) ($raw[UsersImportColumns::LEGAL_ENTITY] ?? ''),
                studentEmail: $raw[UsersImportColumns::STUDENT_EMAIL] ?? null,
                studentPhone: $raw[UsersImportColumns::STUDENT_PHONE] ?? null,
                birthday: $raw[UsersImportColumns::BIRTHDAY] ?? null,
                birthdayInvalid: (bool) ($raw['birthday_invalid'] ?? false),
                isEnabled: (bool) ($raw[UsersImportColumns::IS_ENABLED] ?? true),
                parentEmail: $raw[UsersImportColumns::PARENT_EMAIL] ?? null,
                parentLastname: $raw[UsersImportColumns::PARENT_LASTNAME] ?? null,
                parentFirstname: $raw[UsersImportColumns::PARENT_FIRSTNAME] ?? null,
                parentMiddlename: $raw[UsersImportColumns::PARENT_MIDDLENAME] ?? null,
                parentPhone: $raw[UsersImportColumns::PARENT_PHONE] ?? null,
                mode: 'create',
            );
        }

        if ($rows === [] && $errors === []) {
            $errors[] = new UsersImportRowError(0, 'file', 'Файл не содержит строк с данными.');
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @return array<string, int> column key => column index (1-based)
     */
    private function resolveHeaderMap(Worksheet $sheet): array
    {
        $labels = UsersImportColumns::headerLabels();
        $labelToKey = [];
        foreach ($labels as $key => $label) {
            $labelToKey[mb_strtolower(trim($label))] = $key;
        }

        $headerMap = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $header = UsersImportNormalizer::normalizeText((string) UsersSpreadsheetCells::getValue($sheet, $col, 1), 255);
            if ($header === null) {
                continue;
            }

            $key = $labelToKey[mb_strtolower($header)] ?? null;
            if ($key !== null) {
                $headerMap[$key] = $col;
            }
        }

        return $headerMap;
    }

    /**
     * @param array<string, int> $headerMap
     * @return array<string, mixed>
     */
    private function readRow(Worksheet $sheet, int $rowNumber, array $headerMap): array
    {
        $get = static function (string $key) use ($sheet, $rowNumber, $headerMap): mixed {
            if (! isset($headerMap[$key])) {
                return null;
            }

            return UsersSpreadsheetCells::getValue($sheet, $headerMap[$key], $rowNumber);
        };

        $lastname = UsersImportNormalizer::normalizeText((string) ($get(UsersImportColumns::STUDENT_LASTNAME) ?? ''), 25);
        $name = UsersImportNormalizer::normalizeText((string) ($get(UsersImportColumns::STUDENT_NAME) ?? ''), 25);
        $team = UsersImportNormalizer::normalizeTeamTitle((string) ($get(UsersImportColumns::TEAM) ?? ''));
        $legalEntity = UsersImportNormalizer::normalizeText((string) ($get(UsersImportColumns::LEGAL_ENTITY) ?? ''), 255);

        $emailRaw = $get(UsersImportColumns::STUDENT_EMAIL);
        $phoneRaw = $get(UsersImportColumns::STUDENT_PHONE);
        $birthdayRaw = $get(UsersImportColumns::BIRTHDAY);
        $birthdayParsed = UsersImportNormalizer::parseBirthday($birthdayRaw);
        $birthdayInvalid = false;
        if ($birthdayRaw !== null && $birthdayRaw !== '' && $birthdayParsed === null) {
            $birthdayInvalid = true;
        }

        $enabledRaw = $get(UsersImportColumns::IS_ENABLED);

        $parentEmailRaw = $get(UsersImportColumns::PARENT_EMAIL);
        $parentLastname = UsersImportNormalizer::normalizeText((string) ($get(UsersImportColumns::PARENT_LASTNAME) ?? ''), 100);
        $parentFirstname = UsersImportNormalizer::normalizeText((string) ($get(UsersImportColumns::PARENT_FIRSTNAME) ?? ''), 100);
        $parentMiddlename = UsersImportNormalizer::normalizeText((string) ($get(UsersImportColumns::PARENT_MIDDLENAME) ?? ''), 100);
        $parentPhoneRaw = $get(UsersImportColumns::PARENT_PHONE);

        return [
            UsersImportColumns::STUDENT_LASTNAME => $lastname ?? '',
            UsersImportColumns::STUDENT_NAME => $name ?? '',
            UsersImportColumns::TEAM => $team ?? '',
            UsersImportColumns::LEGAL_ENTITY => $legalEntity ?? '',
            UsersImportColumns::STUDENT_EMAIL => UsersImportNormalizer::normalizeEmail(is_string($emailRaw) || is_numeric($emailRaw) ? (string) $emailRaw : null),
            UsersImportColumns::STUDENT_PHONE => UsersImportNormalizer::normalizeRuPhone(is_string($phoneRaw) || is_numeric($phoneRaw) ? (string) $phoneRaw : null),
            UsersImportColumns::BIRTHDAY => $birthdayParsed,
            'birthday_invalid' => $birthdayInvalid,
            UsersImportColumns::IS_ENABLED => UsersImportNormalizer::parseIsEnabled($enabledRaw),
            UsersImportColumns::PARENT_EMAIL => UsersImportNormalizer::normalizeEmail(is_string($parentEmailRaw) || is_numeric($parentEmailRaw) ? (string) $parentEmailRaw : null),
            UsersImportColumns::PARENT_LASTNAME => $parentLastname,
            UsersImportColumns::PARENT_FIRSTNAME => $parentFirstname,
            UsersImportColumns::PARENT_MIDDLENAME => $parentMiddlename,
            UsersImportColumns::PARENT_PHONE => UsersImportNormalizer::normalizeParentPhone(is_string($parentPhoneRaw) || is_numeric($parentPhoneRaw) ? (string) $parentPhoneRaw : null),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function isRowCompletelyEmpty(array $raw): bool
    {
        foreach ($raw as $value) {
            if ($value !== null && $value !== '' && $value !== true) {
                return false;
            }
        }

        return true;
    }
}
