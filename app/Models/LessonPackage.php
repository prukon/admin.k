<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class LessonPackage extends Model
{
    protected $table = 'lesson_packages';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'duration_days' => 'int',
        'lessons_count' => 'int',
        'price_cents' => 'int',
        'freeze_enabled' => 'bool',
        'freeze_days' => 'int',
        'is_active' => 'bool',
    ];

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

