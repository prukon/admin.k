<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamScheduleSlot extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'team_schedule_slots';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'team_id' => 'int',
        'location_id' => 'integer',
        'weekday' => 'int',
        'date_start' => 'date:Y-m-d',
        'date_end' => 'date:Y-m-d',
        'is_enabled' => 'bool',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TeamScheduleSlot $slot): void {
            if ($slot->isForceDeleting()) {
                return;
            }

            TeamScheduleSlotException::query()
                ->where('team_schedule_slot_id', $slot->id)
                ->get()
                ->each
                ->delete();
        });
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserTeamScheduleSlot::class, 'team_schedule_slot_id');
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(TeamScheduleSlotException::class, 'team_schedule_slot_id');
    }
}

