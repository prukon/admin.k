<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class SchoolLeadStatus extends Model
{
    use SoftDeletes;

    public const CODE_NEW = 'new';

    public const DEFAULT_NEW_COLOR = '#a0fe62';

    protected $table = 'school_lead_statuses';

    protected $fillable = [
        'partner_id',
        'code',
        'name',
        'color',
        'sort_order',
        'is_default_in_filter',
        'is_system',
    ];

    protected $casts = [
        'partner_id'           => 'int',
        'sort_order'           => 'int',
        'is_default_in_filter' => 'bool',
        'is_system'            => 'bool',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function schoolLeads(): HasMany
    {
        return $this->hasMany(SchoolLead::class, 'school_lead_status_id');
    }

    /**
     * Системный «Новый» + кастомные статусы партнёра.
     */
    public function scopeAvailableForPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where(function (Builder $q) use ($partnerId) {
            $q->where(function (Builder $systemQuery) {
                $systemQuery->whereNull('partner_id')->where('is_system', true);
            })->orWhere('partner_id', $partnerId);
        });
    }

    public static function systemNew(): self
    {
        $status = static::query()
            ->whereNull('partner_id')
            ->where('is_system', true)
            ->where('code', self::CODE_NEW)
            ->first();

        if (!$status) {
            throw new RuntimeException('Системный статус заявок «Новый» не найден.');
        }

        return $status;
    }

    public static function systemNewId(): int
    {
        return (int) static::systemNew()->id;
    }

    public static function findAvailableForPartner(int $id, int $partnerId): self
    {
        return static::query()
            ->availableForPartner($partnerId)
            ->whereKey($id)
            ->firstOrFail();
    }

    public function contrastingTextColor(): string
    {
        $hex = ltrim((string) ($this->color ?: self::DEFAULT_NEW_COLOR), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '#ffffff';
        }

        $red   = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue  = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $red + 0.587 * $green + 0.114 * $blue) / 255;

        return $luminance > 0.6 ? '#212529' : '#ffffff';
    }

    public function badgeStyle(): string
    {
        $background = $this->color ?: self::DEFAULT_NEW_COLOR;

        return 'background-color:' . $background . ';color:' . $this->contrastingTextColor() . ';';
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontendArray(?int $leadsCount = null): array
    {
        $payload = [
            'id'                   => $this->id,
            'partner_id'           => $this->partner_id,
            'name'                 => $this->name,
            'color'                => $this->color,
            'text_color'           => $this->contrastingTextColor(),
            'badge_style'          => $this->badgeStyle(),
            'sort_order'           => (int) ($this->sort_order ?? 0),
            'is_default_in_filter' => (bool) $this->is_default_in_filter,
            'is_system'            => (bool) $this->is_system,
        ];

        if ($leadsCount !== null) {
            $payload['leads_count'] = $leadsCount;
        }

        return $payload;
    }
}
