<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleUser extends Model
{
    use HasFactory;

    protected $table = 'schedule_users'; //явное указание к какой таблице в БД привязана модель
    protected $guarded = []; //разрешение на изменение данных в таблице


    protected $casts = [
//        'date' => 'date',
        'date' => 'datetime:Y-m-d',
    ];

    public function statusRelation()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }


}

