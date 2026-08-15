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
    ];

    public static function resolvePageLength(mixed $value): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            return self::DEFAULT_PAGE_LENGTH;
        }

        return in_array($int, self::PAGE_LENGTHS, true)
            ? $int
            : self::DEFAULT_PAGE_LENGTH;
    }

    public static function pageLengthForUser(?int $userId, string $tableKey): int
    {
        if ($userId === null || $userId < 1) {
            return self::DEFAULT_PAGE_LENGTH;
        }

        return self::resolvePageLength(
            self::query()
                ->where('user_id', $userId)
                ->where('table_key', $tableKey)
                ->value('page_length')
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
