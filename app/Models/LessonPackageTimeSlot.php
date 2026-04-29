<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonPackageTimeSlot extends Model
{
    protected $table = 'lesson_package_time_slots';

    protected $guarded = [];

    protected $casts = [
        'weekday' => 'int',
    ];

    public function lessonPackage(): BelongsTo
    {
        return $this->belongsTo(LessonPackage::class, 'lesson_package_id');
    }
}

