<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamPrice extends Model
{
    use HasFactory;

    protected $table = 'team_prices'; //явное указание к какой таблице в БД привязана модель
    protected $guarded = []; //разрешение на изменение данных в таблице}


    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'id');
    }
}