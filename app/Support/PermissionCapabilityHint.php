<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Текст ховера в матрице «Права и роли»: что даёт permission в проекте.
 */
final class PermissionCapabilityHint
{
    /**
     * @return list<string>
     */
    public static function items(string $permissionName): array
    {
        $catalog = config('permission_capability_hints', []);
        if (! is_array($catalog)) {
            return [];
        }

        $raw = $catalog[$permissionName] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $item) {
            $line = trim((string) $item);
            if ($line !== '') {
                $items[] = $line;
            }
        }

        return $items;
    }

    public static function title(string $permissionName): string
    {
        $items = self::items($permissionName);
        if ($items === []) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $lines = [];
        foreach ($items as $index => $item) {
            $lines[] = ($index + 1).'. '.$item;
        }

        return implode("\n", $lines);
    }
}
