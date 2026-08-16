<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InAppNotification extends Model
{
    public const CATEGORY_UPDATE = 'update';

    public const CATEGORY_IMPORTANT = 'important';

    public const CATEGORY_NORMAL = 'normal';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_EVENT = 'event';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPATCHING = 'dispatching';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_FAILED = 'failed';

    public const TTL_1D = '1d';

    public const TTL_7D = '7d';

    public const TTL_30D = '30d';

    public const TTL_CUSTOM = 'custom';

    public const TTL_UNTIL_READ = 'until_read';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_UPDATE,
        self::CATEGORY_IMPORTANT,
        self::CATEGORY_NORMAL,
    ];

    /** @var list<string> */
    public const TTL_PRESETS = [
        self::TTL_1D,
        self::TTL_7D,
        self::TTL_30D,
        self::TTL_CUSTOM,
        self::TTL_UNTIL_READ,
    ];

    protected $table = 'in_app_notifications';

    protected $guarded = [];

    protected $casts = [
        'is_global' => 'boolean',
        'audience_role_ids' => 'array',
        'expires_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'failed_at' => 'datetime',
        'recipients_count' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(
            Partner::class,
            'in_app_notification_partners',
            'in_app_notification_id',
            'partner_id'
        )->withTimestamps();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(InAppNotificationRecipient::class, 'in_app_notification_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(InAppNotificationRead::class, 'in_app_notification_id');
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeDispatched(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DISPATCHED);
    }

    public function categoryLabel(): string
    {
        $labels = config('in_app_notifications.categories', []);

        return (string) ($labels[$this->category] ?? $this->category);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now());
    }
}
