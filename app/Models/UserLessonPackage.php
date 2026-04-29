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
        'user_id' => 'int',
        'lesson_package_id' => 'int',
        'created_by' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lessonPackage(): BelongsTo
    {
        return $this->belongsTo(LessonPackage::class, 'lesson_package_id');
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

