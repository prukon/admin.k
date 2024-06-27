<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $table = 'teams'; //явное указание к какой таблице в БД привязана модель
    protected $guarded = []; //разрешение на изменение данных в таблице


    public function weekdays()
    {
        return $this->belongsToMany(Weekday::class, 'team_weekdays','team_id', 'weekday_id' );
    }
}
