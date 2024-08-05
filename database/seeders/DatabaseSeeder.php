<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Team;
use App\Models\TeamWeekday;
use App\Models\User;
use App\Models\Weekday;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        DB::table('weekdays')->insert([
            ['id' => 1, 'title' => 'Понедельник', 'titleEn' => 'Monday', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 2, 'title' => 'Вторник', 'titleEn' => 'Tuesday', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 3, 'title' => 'Среда', 'titleEn' => 'Wednesday', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 4, 'title' => 'Четверг', 'titleEn' => 'Thursday', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 5, 'title' => 'Пятница', 'titleEn' => 'Friday', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 6, 'title' => 'Суббота', 'titleEn' => 'Saturday', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 7, 'title' => 'Воскресенье', 'titleEn' => 'Sunday', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]);
        $weekdays = Weekday::all();


//        $teams = Team::factory(20)->create();

        // Заполнение таблицы teams
        DB::table('teams')->insert([
            ['id' => 1, 'title' => 'Дубль', 'image' => 'https://via.placeholder.com/640x480.png/004488?text=totam', 'is_enabled' => 1, 'order_by' => 10, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 2, 'title' => 'Легион 630', 'image' => 'https://via.placeholder.com/640x480.png/00bbaa?text=mollitia', 'is_enabled' => 1, 'order_by' => 20, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 3, 'title' => 'Феникс', 'image' => 'https://via.placeholder.com/640x480.png/001144?text=quas', 'is_enabled' => 1, 'order_by' => 30, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 4, 'title' => 'Штурм', 'image' => 'https://via.placeholder.com/640x480.png/0000bb?text=non', 'is_enabled' => 1, 'order_by' => 40, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 21, 'title' => 'Алмаз', 'image' => 'https://via.placeholder.com/640x480.png/0000bb?text=non', 'is_enabled' => 1, 'order_by' => 50, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]);
        $teams = Team::all();



        User::factory(200)->create();

        foreach ($teams as $team) {
            $WeekdayId = $weekdays->random(3)->pluck('id');
            $team->weekdays()->attach($WeekdayId);
        }


//       dd($teams);
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
