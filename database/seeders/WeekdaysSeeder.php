<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WeekdaysSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('weekdays')->upsert(
            [
                ['id' => 1, 'title' => 'Понедельник', 'titleEn' => 'Monday',    'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'title' => 'Вторник',     'titleEn' => 'Tuesday',   'created_at' => $now, 'updated_at' => $now],
                ['id' => 3, 'title' => 'Среда',       'titleEn' => 'Wednesday', 'created_at' => $now, 'updated_at' => $now],
                ['id' => 4, 'title' => 'Четверг',     'titleEn' => 'Thursday',  'created_at' => $now, 'updated_at' => $now],
                ['id' => 5, 'title' => 'Пятница',     'titleEn' => 'Friday',    'created_at' => $now, 'updated_at' => $now],
                ['id' => 6, 'title' => 'Суббота',     'titleEn' => 'Saturday',  'created_at' => $now, 'updated_at' => $now],
                ['id' => 7, 'title' => 'Воскресенье', 'titleEn' => 'Sunday',    'created_at' => $now, 'updated_at' => $now],
            ],
            ['id'], // PK
            ['title', 'titleEn', 'updated_at'] // created_at не трогаем
        );
    }
}