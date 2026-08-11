<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentNotificationRule extends Model
{
    public const TRIGGER_DAY_OF_MONTH = 'day_of_month';

    public const TRIGGER_DAYS_AFTER_OVERDUE = 'days_after_overdue';

    public const TRIGGER_TYPES = [
        self::TRIGGER_DAY_OF_MONTH,
        self::TRIGGER_DAYS_AFTER_OVERDUE,
    ];

    /** Типы абонементов, доступные в конструкторе правил. */
    public const ALLOWED_SCHEDULE_TYPES = [
        LessonPackage::SCHEDULE_TYPE_FIXED,
        LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
        LessonPackage::SCHEDULE_TYPE_POSTPAY,
    ];

    protected $table = 'payment_notification_rules';

    protected $guarded = [];

    protected $casts = [
        'partner_id'            => 'integer',
        'is_enabled'            => 'boolean',
        'trigger_value'         => 'integer',
        'billing_month_offset'  => 'integer',
        'schedule_types'        => 'array',
        'sort_order'            => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(PaymentNotificationDispatch::class, 'payment_notification_rule_id');
    }

    public function usesDayOfMonthTrigger(): bool
    {
        return $this->trigger_type === self::TRIGGER_DAY_OF_MONTH;
    }

    public function usesDaysAfterOverdueTrigger(): bool
    {
        return $this->trigger_type === self::TRIGGER_DAYS_AFTER_OVERDUE;
    }

    /**
     * @return list<string>
     */
    public function normalizedScheduleTypes(): array
    {
        $raw = $this->schedule_types;
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $type) {
            if (! is_string($type)) {
                continue;
            }
            $type = trim($type);
            if ($type !== '' && in_array($type, self::ALLOWED_SCHEDULE_TYPES, true)) {
                $out[] = $type;
            }
        }

        return array_values(array_unique($out));
    }
}
