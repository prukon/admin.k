<?php

namespace App\Services\Contracts;

/**
 * Плейсхолдеры {{key}} в DOCX: Word часто разрывает их XML-тегами между символами.
 */
final class DocxPlaceholderSupport
{
    /** Допускает XML-теги внутри {{ ... }} */
    public const PATTERN_IN_XML = '/\{\{((?:[^{}]|<[^>]+>)*?)\}\}/us';

    /**
     * Служебные ключи: подставляются автоматически, не показываются в форме клиента.
     *
     * @return list<string>
     */
    public static function systemKeys(): array
    {
        return ['documents_url', 'student_name', 'contract_id'];
    }

    public static function isSystemKey(string $key): bool
    {
        return in_array($key, self::systemKeys(), true);
    }

    /**
     * @param list<string> $keys
     * @return list<string>
     */
    public static function filterFormFieldKeys(array $keys): array
    {
        return array_values(array_filter($keys, fn (string $key) => !self::isSystemKey($key)));
    }

    public static function normalizeKey(string $rawInsideBraces): ?string
    {
        $key = preg_replace('/<[^>]+>/', '', $rawInsideBraces);
        $key = preg_replace('/\s+/u', '', $key ?? '');

        if ($key === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $key)) {
            return null;
        }

        return $key;
    }

    /**
     * @return list<string>
     */
    public static function extractKeysFromXml(string $xml): array
    {
        if (!preg_match_all(self::PATTERN_IN_XML, $xml, $matches)) {
            return [];
        }

        $keys = [];
        foreach ($matches[1] as $fragment) {
            $key = self::normalizeKey($fragment);
            if ($key !== null && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param array<string, string> $values
     */
    public static function replaceValuesInXml(string $xml, array $values): string
    {
        $xml = (string) preg_replace_callback(
            self::PATTERN_IN_XML,
            function (array $matches) use ($values): string {
                $key = self::normalizeKey($matches[1]);
                if ($key === null || !array_key_exists($key, $values)) {
                    return $matches[0];
                }

                return self::escapeXmlText($values[$key]);
            },
            $xml
        );

        foreach ($values as $key => $value) {
            if (!is_string($key) || $key === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $key)) {
                continue;
            }

            $pattern = self::buildAggressiveKeyPattern($key);
            $xml = (string) preg_replace(
                $pattern,
                self::escapeXmlText($value),
                $xml
            );
        }

        return $xml;
    }

    public static function buildAggressiveKeyPattern(string $key): string
    {
        $pattern = '/\{\{(?:<[^>]+>|\s)*';
        $length = strlen($key);
        for ($i = 0; $i < $length; $i++) {
            $pattern .= preg_quote($key[$i], '/');
            $pattern .= '(?:<[^>]+>|\s)*';
        }
        $pattern .= '\}\}/u';

        return $pattern;
    }

    private static function escapeXmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
