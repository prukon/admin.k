<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLessonPackageTimeSlot extends Model
{
    protected $table = 'user_lesson_package_time_slots';

    protected $guarded = [];

    protected $casts = [
        'weekday' => 'int',
        'user_lesson_package_id' => 'int',
    ];

    public function userLessonPackage(): BelongsTo
    {
        return $this->belongsTo(UserLessonPackage::class, 'user_lesson_package_id');
    }
}

