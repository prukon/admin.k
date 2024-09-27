<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $table = 'logs'; // Явно указываем таблицу, если нужно


    protected $guarded = []; //разрешение на изменение данных в таблице}


    // Укажите, что поле created_at является датой
    protected $casts = [
        'created_at' => 'datetime',
    ];

    public $timestamps = false; // Отключаем автоматическое создание временных меток

    const CREATED_AT = 'created_at'; // Используем кастомное поле для времени создания

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Аксессор для получения названия типа лога
    public function getTypeLabelAttribute()
    {
        return self::$typeLabels[$this->type] ?? 'Неизвестный тип';
    }
}

