<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamScheduleSlotException extends Model
{
    use SoftDeletes;

    protected $table = 'team_schedule_slot_exceptions';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'team_schedule_slot_id' => 'int',
        'occurrence_date' => 'date:Y-m-d',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TeamScheduleSlot::class, 'team_schedule_slot_id');
    }
}
