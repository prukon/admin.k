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

    // public static function info($string, array $array)
    // {
    // } // Используем кастомное поле для времени создания

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Аксессор для получения названия типа лога
    // public function getTypeLabelAttribute()
    // {
    //     return self::$typeLabels[$this->type] ?? 'Неизвестный тип';
    // }

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

    /**
     * Человекочитаемые подписи кодов action для UI и логов.
     *
     * @return array<int, string>
     */
    public static function actionLabels(): array
    {
        return [
            11 => 'Изм. цен во всех группах (Применить слева)',
            12 => 'Инд. изм. цен (Применить справа)',
            13 => 'Изм. цен в одной группе  (ок)',
            14 => 'Ручная отметка оплаты месяца (users_prices)',

            21 => 'Создание пользователя',
            22 => 'Обновление учетной записи в пользователях',
            23 => 'Обновление учетной записи',
            24 => 'Удаление пользователя в пользователях',
            25 => 'Изменение пароля (админ)',
            26 => 'Изменение пароля',
            27 => 'Изменение аватара (админ)',
            28 => 'Изменение аватара',
            29 => 'Удаление аватара',
            299 => 'Удаление аватара (админ)',

            210 => 'Изменение доп полей пользователя',
            211 => 'Изменение номера телефона',

            31 => 'Создание группы',
            32 => 'Изменение группы',
            33 => 'Удаление группы',

            40 => 'Авторизация',

            50 => 'Платежи',

            500 => 'Договор создан',

            510 => 'Создан запрос на подпись (create)',
            511 => 'Повторная отправка (успешно)',
            512 => 'Повторная отправка (ошибка)',
            513 => 'Первичная отправка (успешно)',
            514 => 'Первичная отправка (ошибка)',
            519 => 'Получатель открыл СМС',
            520 => 'Договор подписан',

            60 => 'Расписание',
            601 => 'Отмена пробного занятия в расписании',

            70 => 'Изменение настроек',

            710 => 'Создание роли',
            720 => 'Изменение роли',
            730 => 'Удаление роли',

            80 => 'Изменение партнера',
            81 => 'Создание партнера суперадмином',
            82 => 'Изменение партнера суперадмином',
            83 => 'Удаление партнера',

            90 => 'Создание статуса расписания',
            91 => 'Изменение статуса расписания',
            92 => 'Удаление статуса расписания',

            900 => 'Создание договора',
            901 => 'Изменение отправка договора в SMS',
            902 => 'Удаление договора',
        ];
    }

}
