<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTableSetting extends Model
{
    public const PAGE_LENGTHS = [10, 20, 50, 100];
    public const DEFAULT_PAGE_LENGTH = 10;

    protected $table = 'user_table_settings';
    protected $guarded = [];

    protected $casts = [
        'columns'     => 'array', // 👈 важно, чтобы columns автоматически превращался в массив
        'page_length' => 'integer',
        'filters'     => 'array',
    ];

    public static function resolvePageLength(mixed $value, int $fallback = self::DEFAULT_PAGE_LENGTH): int
    {
        $fallback = in_array($fallback, self::PAGE_LENGTHS, true)
            ? $fallback
            : self::DEFAULT_PAGE_LENGTH;

        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            return $fallback;
        }

        return in_array($int, self::PAGE_LENGTHS, true)
            ? $int
            : $fallback;
    }

    public static function pageLengthForUser(?int $userId, string $tableKey, int $fallback = self::DEFAULT_PAGE_LENGTH): int
    {
        $fallback = in_array($fallback, self::PAGE_LENGTHS, true)
            ? $fallback
            : self::DEFAULT_PAGE_LENGTH;

        if ($userId === null || $userId < 1) {
            return $fallback;
        }

        $raw = self::query()
            ->where('user_id', $userId)
            ->where('table_key', $tableKey)
            ->value('page_length');

        if ($raw === null) {
            return $fallback;
        }

        return self::resolvePageLength($raw, $fallback);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
