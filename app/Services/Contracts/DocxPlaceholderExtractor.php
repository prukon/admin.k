<?php

namespace App\Services\Contracts;

use RuntimeException;
use ZipArchive;

/**
 * Извлекает плейсхолдеры вида {{key}} из DOCX (word/document.xml).
 */
class DocxPlaceholderExtractor
{
    private const PLACEHOLDER_PATTERN = '/\{\{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/u';

    /**
     * @return list<string> Уникальные ключи в порядке первого появления.
     */
    public function extractFromPath(string $absolutePath): array
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException('DOCX-файл не найден.');
        }

        $zip = new ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('Не удалось открыть DOCX как архив.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || $xml === '') {
            throw new RuntimeException('В DOCX отсутствует word/document.xml.');
        }

        $keys = DocxPlaceholderSupport::extractKeysFromXml($xml);
        if ($keys !== []) {
            return $keys;
        }

        return $this->extractFromText($this->xmlToPlainText($xml));
    }

    /**
     * @return list<string>
     */
    public function extractFromText(string $text): array
    {
        if (!preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches)) {
            return [];
        }

        $keys = [];
        foreach ($matches[1] as $key) {
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param list<string> $keys
     * @param array<int, array<string, mixed>>|null $previousSchema
     * @return list<array{key: string, label: string, required: bool, prefill_source: string|null}>
     */
    public function buildFieldsSchema(array $keys, ?array $previousSchema = null): array
    {
        $previousByKey = [];
        if ($previousSchema) {
            foreach ($previousSchema as $field) {
                $k = $field['key'] ?? null;
                if (is_string($k) && $k !== '') {
                    $previousByKey[$k] = $field;
                }
            }
        }

        $schema = [];
        foreach (DocxPlaceholderSupport::filterFormFieldKeys($keys) as $key) {
            $prev = $previousByKey[$key] ?? null;
            $schema[] = [
                'key'             => $key,
                'label'           => (string) ($prev['label'] ?? $this->defaultLabel($key)),
                'required'        => (bool) ($prev['required'] ?? true),
                'prefill_source'  => isset($prev['prefill_source']) && $prev['prefill_source'] !== ''
                    ? (string) $prev['prefill_source']
                    : null,
            ];
        }

        return $schema;
    }

    private function defaultLabel(string $key): string
    {
        return str_replace('_', ' ', $key);
    }

    private function xmlToPlainText(string $xml): string
    {
        // Склеиваем текст из w:t, чтобы уменьшить риск разрыва плейсхолдеров тегами.
        if (preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $xml, $parts)) {
            return html_entity_decode(implode('', $parts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $stripped = strip_tags($xml);

        return html_entity_decode($stripped, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
