<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $table = 'locations';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'is_enabled' => 'bool',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function teamScheduleSlots(): HasMany
    {
        return $this->hasMany(TeamScheduleSlot::class, 'location_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'location_id');
    }
}

