<?php

namespace App\Models;

use App\Services\LessonPackages\SchoolScheduleTrialLessonsCounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTeamScheduleSlot extends Model
{
    use HasFactory;

    protected $table = 'user_team_schedule_slots';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'user_id' => 'int',
        'team_schedule_slot_id' => 'int',
        'is_trial_lesson' => 'bool',
        'trial_lessons_remaining' => 'int',
        'trial_lessons_total' => 'int',
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
        'created_by' => 'int',
    ];

    protected static function booted(): void
    {
        static::created(function (UserTeamScheduleSlot $row): void {
            if ($row->countsTowardSchoolTrialLessons()) {
                app(SchoolScheduleTrialLessonsCounter::class)->increment((int) $row->user_id);
            }
        });

        static::updated(function (UserTeamScheduleSlot $row): void {
            $counter = app(SchoolScheduleTrialLessonsCounter::class);
            $wasCounted = $row->countedTowardSchoolTrialLessonsOriginally();
            $isCounted = $row->countsTowardSchoolTrialLessons();
            $previousUserId = (int) $row->getOriginal('user_id');
            $currentUserId = (int) $row->user_id;

            if ($wasCounted && $isCounted && $previousUserId === $currentUserId) {
                return;
            }

            if ($wasCounted) {
                $counter->decrement($previousUserId);
            }
            if ($isCounted) {
                $counter->increment($currentUserId);
            }
        });

        static::deleted(function (UserTeamScheduleSlot $row): void {
            if ($row->countsTowardSchoolTrialLessons()) {
                app(SchoolScheduleTrialLessonsCounter::class)->decrement((int) $row->user_id);
            }
        });
    }

    public function countsTowardSchoolTrialLessons(): bool
    {
        return (bool) $this->is_trial_lesson && $this->user_lesson_package_id === null;
    }

    private function countedTowardSchoolTrialLessonsOriginally(): bool
    {
        return (bool) $this->getOriginal('is_trial_lesson') && $this->getOriginal('user_lesson_package_id') === null;
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TeamScheduleSlot::class, 'team_schedule_slot_id');
    }

    public function userLessonPackage(): BelongsTo
    {
        return $this->belongsTo(UserLessonPackage::class, 'user_lesson_package_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

