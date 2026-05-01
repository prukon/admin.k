<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamScheduleSlot extends Model
{
    use HasFactory;

    protected $table = 'team_schedule_slots';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'team_id' => 'int',
        'location_id' => 'int',
        'weekday' => 'int',
        'date_start' => 'date:Y-m-d',
        'date_end' => 'date:Y-m-d',
        'is_enabled' => 'bool',
    ];

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
}

