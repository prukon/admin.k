<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $table = 'locations';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'district_id' => 'int',
        'is_enabled' => 'bool',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function adminUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'location_admin_user', 'location_id', 'user_id')
            ->withPivot('partner_id')
            ->withTimestamps();
    }

    public function teamScheduleSlots(): HasMany
    {
        return $this->hasMany(TeamScheduleSlot::class, 'location_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'location_id');
    }
}

