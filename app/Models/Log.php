<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $table = 'logs'; // Явно указываем таблицу, если нужно


    protected $fillable = [
        'type',
        'author_id',
        'description',
    ];

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

    // Массив для отображения типов логов
    public static $typeLabels = [
        1 => 'Изменение цен',
        2 => 'Обновление',
        3 => 'Удаление',
        // Добавьте другие типы по необходимости
    ];

    // Аксессор для получения названия типа лога
    public function getTypeLabelAttribute()
    {
        return self::$typeLabels[$this->type] ?? 'Неизвестный тип';
    }
}

