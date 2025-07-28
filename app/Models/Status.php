<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Status extends Model
{

    protected $table = 'statuses';


    use SoftDeletes; // Включаем мягкое удаление

    // Если нужно разрешить массовое заполнение
    protected $fillable = [
        'partner_id',
        'name',
        'icon',
        'color',
        'is_system',
    ];

    // Или, начиная с Laravel 9, можно просто:
    // protected $guarded = [];

    // Мягкое удаление будет работать автоматически:
    // - при $status->delete() поле deleted_at заполнится датой
    // - при $status->restore() поле deleted_at обнулится
}
