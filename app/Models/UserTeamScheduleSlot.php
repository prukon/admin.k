<?php

namespace App\Models;

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

