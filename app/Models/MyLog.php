<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MyLog extends Model
{
    protected $table = 'my_logs'; // Явно указываем таблицу, если нужно
    protected $guarded = []; //разрешение на изменение данных в таблице}

    // Укажите, что поле created_at является датой
    protected $casts = [
        'created_at' => 'datetime',
        'user_id'     => 'integer',   // <-- поле, добавленное в БД
        'partner_id'  => 'integer',
        'author_id'   => 'integer',

    ];

    public $timestamps = false; // Отключаем автоматическое создание временных меток

    const CREATED_AT = 'created_at';

    public static function info($string, array $array)
    {
    } // Используем кастомное поле для времени создания

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Аксессор для получения названия типа лога
    public function getTypeLabelAttribute()
    {
        return self::$typeLabels[$this->type] ?? 'Неизвестный тип';
    }

    // Полиморфная связь для навигации (по желанию)
    public function target(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'target_type', 'target_id');
    }

    protected static function booted(): void
    {
        static::creating(function (MyLog $log) {
            // 🔹 user_id — если не задан, а пользователь авторизован
            if (empty($log->user_id) && auth()->check()) {
                $log->user_id = null;
            }

            // 🔹 partner_id — если не задан, а контекст партнёра доступен
            if (empty($log->partner_id) && app()->bound('current_partner')) {
                $currentPartner = app('current_partner');
                if ($currentPartner && isset($currentPartner->id)) {
                    $log->partner_id = $currentPartner->id;
                }
            }

            if (empty($log->author_id) && auth()->check()) {
                $log->author_id = auth()->id();
            }

        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
