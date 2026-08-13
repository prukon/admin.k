<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserLessonPackage extends Model
{
    protected $table = 'user_lesson_packages';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
        'billing_month' => 'date:Y-m-d',
        'lessons_total' => 'int',
        'lessons_remaining' => 'int',
        'fee_amount_cents' => 'integer',
        'discount_percent' => 'integer',
        'is_paid' => 'bool',
        'is_manual_paid' => 'boolean',
        'manual_paid_at' => 'datetime',
        'manual_paid_by' => 'integer',
        'user_id' => 'int',
        'team_id' => 'int',
        'lesson_package_id' => 'int',
        'created_by' => 'int',
        'auto_prolong_enabled' => 'bool',
        'auto_prolonged_from_id' => 'int',
        'auto_prolonged_to_id' => 'int',
    ];

    protected $appends = [
        'effective_is_paid',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function lessonPackage(): BelongsTo
    {
        return $this->belongsTo(LessonPackage::class, 'lesson_package_id');
    }

    public function manualPaidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_paid_by');
    }

    /**
     * Оплачен ли абонемент для UI и отчётов: ручная отметка важнее автоматической.
     */
    public function getEffectiveIsPaidAttribute(): bool
    {
        if ($this->is_manual_paid !== null) {
            return (bool) $this->is_manual_paid;
        }

        return (bool) $this->is_paid;
    }

    /**
     * Слоты назначения (используются для flexible).
     */
    public function timeSlots(): HasMany
    {
        return $this->hasMany(UserLessonPackageTimeSlot::class, 'user_lesson_package_id');
    }

    /**
     * Заморозки конкретных занятий (по дате и слоту).
     */
    public function freezes(): HasMany
    {
        return $this->hasMany(UserLessonPackageFreeze::class, 'user_lesson_package_id');
    }

    /**
     * Записи в календаре школы (слоты группы), привязанные к этому назначению.
     */
    public function userTeamScheduleSlots(): HasMany
    {
        return $this->hasMany(UserTeamScheduleSlot::class, 'user_lesson_package_id');
    }

    public function publicPayLink(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserLessonPackagePublicPayLink::class, 'user_lesson_package_id');
    }

    public function autoProlongedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'auto_prolonged_from_id');
    }

    public function autoProlongedTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'auto_prolonged_to_id');
    }

    /**
     * Показывать бейдж автопролонгации (активный флаг или звено цепочки продления).
     */
    public function showsAutoProlongBadge(): bool
    {
        return (bool) $this->auto_prolong_enabled
            || $this->auto_prolonged_from_id !== null
            || $this->auto_prolonged_to_id !== null;
    }

    public function autoProlongBadgeLabel(): string
    {
        if ($this->auto_prolong_enabled) {
            return 'Автопролонг';
        }

        if ($this->auto_prolonged_to_id !== null) {
            return 'Автопролонг · продолжен';
        }

        if ($this->auto_prolonged_from_id !== null) {
            return 'Автопролонг · из №'.$this->auto_prolonged_from_id;
        }

        return 'Автопролонг';
    }

    /**
     * Назначение из установки цен (месячное): есть billing_month.
     */
    public function isFromSettingPrices(): bool
    {
        return $this->billing_month !== null;
    }

    /**
     * Месячный гибкий из установки цен: период (starts_at/ends_at) задаётся сразу на весь billing_month.
     */
    public function isMonthlyFlexibleFromSettingPrices(): bool
    {
        if (! $this->isFromSettingPrices()) {
            return false;
        }

        $this->loadMissing('lessonPackage:id,schedule_type');

        return $this->lessonPackage !== null
            && (string) $this->lessonPackage->schedule_type === LessonPackage::SCHEDULE_TYPE_FLEXIBLE;
    }

    /**
     * Уже разложено в расписание / задан старт периода.
     * Для месячных (billing_month): один ends_at без starts_at — ещё не раскладка.
     * Для месячного flexible: полный период при sync не означает раскладку — смотрим UTSS.
     */
    public function isLaidOutInSchedule(): bool
    {
        if ($this->isMonthlyFlexibleFromSettingPrices()) {
            return $this->userTeamScheduleSlots()->exists();
        }

        if ($this->starts_at !== null) {
            return true;
        }

        if (! $this->isFromSettingPrices() && $this->ends_at !== null) {
            return true;
        }

        return $this->userTeamScheduleSlots()->exists();
    }

    /**
     * Можно показать «+» в журнале (fixed placeable).
     */
    public function isJournalPlaceable(): bool
    {
        return ! $this->isLaidOutInSchedule() && (int) $this->lessons_total > 0;
    }

    /**
     * Сколько ещё занятий можно привязать к календарю/журналу (лимит по числу UTSS).
     */
    public function calendarSlotsRemaining(): int
    {
        $total = max(0, (int) $this->lessons_total);
        if ($total < 1) {
            return 0;
        }

        $used = $this->relationLoaded('userTeamScheduleSlots')
            ? $this->userTeamScheduleSlots->count()
            : $this->userTeamScheduleSlots()->count();

        return max(0, $total - $used);
    }

    /**
     * Последний день месяца начисления (Y-m-d) или null.
     */
    public function billingMonthEndDate(): ?string
    {
        if ($this->billing_month === null) {
            return null;
        }

        return \Carbon\Carbon::parse($this->billing_month->format('Y-m-d'))
            ->endOfMonth()
            ->format('Y-m-d');
    }
}

