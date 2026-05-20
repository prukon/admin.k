<?php

namespace App\Models;

use App\Models\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Team extends Model
{
    use HasFactory;
    use Filterable;
    use SoftDeletes;

    protected $table = 'teams'; //явное указание к какой таблице в БД привязана модель
    protected $guarded = []; //разрешение на изменение данных в таблице

    protected $casts = [
        'is_enabled' => 'bool',
        'partner_id' => 'int',
        'default_duration_minutes' => 'int',
    ];

    public function teamPrices()
    {
        return $this->hasMany(TeamPrice::class, 'team_id', 'id');
    }

    public function weekdays()
    {
        return $this->belongsToMany(Weekday::class, 'team_weekdays','team_id', 'weekday_id' );
    }
    public function test() {
        return "test";
    }
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function scheduleSlots()
    {
        return $this->hasMany(TeamScheduleSlot::class, 'team_id');
    }

    public function trainerProfiles()
    {
        return $this->belongsToMany(
            TrainerProfile::class,
            'team_trainer',
            'team_id',
            'trainer_profile_id',
        )->withTimestamps()->withPivot('partner_id');
    }
}
