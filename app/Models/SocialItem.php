<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialItem extends Model
{
    use HasFactory;

    protected $table = 'social_items'; // Явно указываем таблицу, если нужно
    protected $guarded = []; //разрешение на изменение данных в таблице}

}
