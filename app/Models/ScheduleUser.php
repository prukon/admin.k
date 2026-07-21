<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleUser extends Model
{
    use HasFactory;

    protected $table = 'schedule_users';

    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime:Y-m-d',
        'lesson_occurrence_status_id' => 'int',
        'trainer_profile_id' => 'int',
    ];

    public function statusRelation(): BelongsTo
    {
        return $this->belongsTo(LessonOccurrenceStatus::class, 'lesson_occurrence_status_id');
    }

    public function lessonOccurrenceStatus(): BelongsTo
    {
        return $this->statusRelation();
    }

    public function trainerProfile(): BelongsTo
    {
        return $this->belongsTo(TrainerProfile::class, 'trainer_profile_id');
    }
}
