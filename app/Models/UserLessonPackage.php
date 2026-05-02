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
        'lessons_total' => 'int',
        'lessons_remaining' => 'int',
        'fee_amount' => 'decimal:2',
        'is_paid' => 'bool',
        'is_manual_paid' => 'boolean',
        'manual_paid_at' => 'datetime',
        'manual_paid_by' => 'integer',
        'user_id' => 'int',
        'lesson_package_id' => 'int',
        'created_by' => 'int',
    ];

    protected $appends = [
        'effective_is_paid',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
}

