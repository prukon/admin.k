<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLessonPackageFreeze extends Model
{
    protected $table = 'user_lesson_package_freezes';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'user_lesson_package_id' => 'int',
        'lesson_package_time_slot_id' => 'int',
        'user_lesson_package_time_slot_id' => 'int',
        'created_by' => 'int',
    ];

    public function userLessonPackage(): BelongsTo
    {
        return $this->belongsTo(UserLessonPackage::class, 'user_lesson_package_id');
    }

    public function lessonPackageTimeSlot(): BelongsTo
    {
        return $this->belongsTo(LessonPackageTimeSlot::class, 'lesson_package_time_slot_id');
    }

    public function userLessonPackageTimeSlot(): BelongsTo
    {
        return $this->belongsTo(UserLessonPackageTimeSlot::class, 'user_lesson_package_time_slot_id');
    }
}

