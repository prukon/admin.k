<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class LessonPackage extends Model
{
    use HasFactory;

    public const SCHEDULE_TYPE_FIXED = 'fixed';

    public const SCHEDULE_TYPE_FLEXIBLE = 'flexible';

    public const SCHEDULE_TYPE_NO_SCHEDULE = 'no_schedule';

    /** Постоплата: цена за занятие, биллинг через users_prices (без user_lesson_packages). */
    public const SCHEDULE_TYPE_POSTPAY = 'postpay';

    /** @var list<string> */
    public const SCHEDULE_TYPES = [
        self::SCHEDULE_TYPE_FIXED,
        self::SCHEDULE_TYPE_FLEXIBLE,
        self::SCHEDULE_TYPE_NO_SCHEDULE,
        self::SCHEDULE_TYPE_POSTPAY,
    ];

    /** Типы, для которых создаются назначения user_lesson_packages. */
    /** @var list<string> */
    public const ASSIGNMENT_SCHEDULE_TYPES = [
        self::SCHEDULE_TYPE_FIXED,
        self::SCHEDULE_TYPE_FLEXIBLE,
        self::SCHEDULE_TYPE_NO_SCHEDULE,
    ];

    protected $table = 'lesson_packages';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'duration_days' => 'int',
        'lessons_count' => 'int',
        'price_cents' => 'int',
        'freeze_enabled' => 'bool',
        'freeze_days' => 'int',
        'auto_attendance_enabled' => 'bool',
        'is_active' => 'bool',
    ];

    public function isPostpay(): bool
    {
        return (string) $this->schedule_type === self::SCHEDULE_TYPE_POSTPAY;
    }

    public function priceRub(): float
    {
        return (float) Money::fromCents((int) $this->price_cents);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserLessonPackage::class, 'lesson_package_id');
    }

    /**
     * Занятия в календаре школы, привязанные к назначениям этого шаблона абонемента.
     */
    public function userTeamScheduleSlots(): HasManyThrough
    {
        return $this->hasManyThrough(
            UserTeamScheduleSlot::class,
            UserLessonPackage::class,
            'lesson_package_id',
            'user_lesson_package_id',
            'id',
            'id'
        );
    }
}

