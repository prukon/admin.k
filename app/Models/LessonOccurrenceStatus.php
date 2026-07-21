<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonOccurrenceStatus extends Model
{
    public const CODE_ATTENDED = 'attended';

    protected $table = 'lesson_occurrence_statuses';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'sort_order' => 'int',
        'is_system' => 'bool',
        'is_active' => 'bool',
        'consumes_lesson' => 'bool',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    /**
     * Статусы партнёра для журнала /schedule и CRUD-справочника.
     */
    public function scopeForPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('partner_id', $partnerId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public static function attendedIdForPartner(int $partnerId): ?int
    {
        $id = static::query()
            ->forPartner($partnerId)
            ->where('code', self::CODE_ATTENDED)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Активный статус партнёра для записи в ячейку журнала.
     */
    public static function findActiveForPartner(int $id, int $partnerId): self
    {
        return static::query()
            ->forPartner($partnerId)
            ->active()
            ->whereKey($id)
            ->firstOrFail();
    }
}
