<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Status extends Model
{
    protected $table = 'statuses';

    use SoftDeletes;

    public const VISITED_NAME = 'Посетил';

    protected $fillable = [
        'partner_id',
        'name',
        'icon',
        'color',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'partner_id' => 'int',
        'is_system' => 'bool',
        'sort_order' => 'int',
    ];

    /**
     * Статусы журнала /schedule: кастомные партнёра + общие системные (partner_id IS NULL).
     */
    public function scopeForSchedulePartner(Builder $query, int $partnerId): Builder
    {
        return $query->where(function (Builder $q) use ($partnerId) {
            $q->where('partner_id', $partnerId)
                ->orWhere(function (Builder $q2) {
                    $q2->whereNull('partner_id')->where('is_system', true);
                });
        });
    }

    public static function findForSchedulePartner(int $id, int $partnerId): self
    {
        return static::query()
            ->forSchedulePartner($partnerId)
            ->whereKey($id)
            ->firstOrFail();
    }

    public static function globalVisitedId(): ?int
    {
        return static::query()
            ->whereNull('partner_id')
            ->where('is_system', true)
            ->where('name', self::VISITED_NAME)
            ->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toScheduleJsonArray(): array
    {
        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'is_system' => (bool) $this->is_system,
            'sort_order' => (int) ($this->sort_order ?? 0),
        ];
    }
}
