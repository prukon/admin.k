<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Weekday extends Model
{
    use HasFactory;

    /** Сокращения для таблиц (Пн., Вт., …). */
    public const SHORT_TITLES = [
        1 => 'Пн.',
        2 => 'Вт.',
        3 => 'Ср.',
        4 => 'Чт.',
        5 => 'Пт.',
        6 => 'Сб.',
        7 => 'Вс.',
    ];

    public function shortTitle(): string
    {
        return self::SHORT_TITLES[$this->id] ?? $this->title;
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_weekdays','weekday_id', 'team_id' );
    }
}
