<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserLessonOccurrenceStatusEvent extends Model
{
    protected $table = 'user_lesson_occurrence_status_events';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'user_id' => 'int',
        'team_schedule_slot_id' => 'int',
        'occurrence_date' => 'date:Y-m-d',
        'user_lesson_package_id' => 'integer',
        'lesson_occurrence_status_id' => 'int',
        'trainer_profile_id' => 'int',
        'created_by' => 'int',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function teamScheduleSlot(): BelongsTo
    {
        return $this->belongsTo(TeamScheduleSlot::class, 'team_schedule_slot_id');
    }

    public function userLessonPackage(): BelongsTo
    {
        return $this->belongsTo(UserLessonPackage::class, 'user_lesson_package_id');
    }

    public function lessonOccurrenceStatus(): BelongsTo
    {
        return $this->belongsTo(LessonOccurrenceStatus::class, 'lesson_occurrence_status_id');
    }

    /**
     * Legacy/primary: первый тренер из списка (для обратной совместимости).
     */
    public function trainerProfile(): BelongsTo
    {
        return $this->belongsTo(TrainerProfile::class, 'trainer_profile_id');
    }

    /**
     * Все тренеры, которым идёт зачёт тренировки (ЗП / нагрузка).
     */
    public function trainerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            TrainerProfile::class,
            'user_lesson_occurrence_status_event_trainers',
            'user_lesson_occurrence_status_event_id',
            'trainer_profile_id'
        )->orderBy('user_lesson_occurrence_status_event_trainers.id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
