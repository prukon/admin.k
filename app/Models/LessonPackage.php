<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonPackage extends Model
{
    protected $table = 'lesson_packages';

    protected $guarded = [];

    protected $casts = [
        'duration_days' => 'int',
        'lessons_count' => 'int',
        'price_cents' => 'int',
        'freeze_enabled' => 'bool',
        'freeze_days' => 'int',
        'is_active' => 'bool',
    ];

    public function timeSlots(): HasMany
    {
        return $this->hasMany(LessonPackageTimeSlot::class, 'lesson_package_id');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserLessonPackage::class, 'lesson_package_id');
    }
}

