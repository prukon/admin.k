<?php

namespace App\Models;

use App\Models\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;
    use HasFactory;
    use Filterable;

    protected $table = 'settings'; //явное указание к какой таблице в БД привязана модель

    protected $guarded = []; //разрешение на изменение данных в таблице


}
