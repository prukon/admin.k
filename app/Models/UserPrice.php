<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPrice extends Model
{
    use HasFactory;

    protected $table = 'users_prices'; //явное указание к какой таблице в БД привязана модель
    protected $guarded = []; //разрешение на изменение данных в таблице}

    public function user() {
        return $this->belongsTo(User::class);
    }
}
