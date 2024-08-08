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


        // Заполнение таблицы teams
        DB::table('teams')->insert([
            ['id' => 1, 'title' => 'Дубль', 'image' => 'https://via.placeholder.com/640x480.png/004488?text=totam', 'is_enabled' => 1, 'order_by' => 10, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 2, 'title' => 'Легион 630', 'image' => 'https://via.placeholder.com/640x480.png/00bbaa?text=mollitia', 'is_enabled' => 1, 'order_by' => 20, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 3, 'title' => 'Феникс', 'image' => 'https://via.placeholder.com/640x480.png/001144?text=quas', 'is_enabled' => 1, 'order_by' => 30, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 4, 'title' => 'Штурм', 'image' => 'https://via.placeholder.com/640x480.png/0000bb?text=non', 'is_enabled' => 1, 'order_by' => 40, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 5, 'title' => 'Алмаз', 'image' => 'https://via.placeholder.com/640x480.png/0000bb?text=non', 'is_enabled' => 1, 'order_by' => 50, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]);
        //        $teams = Team::factory(20)->create();
        $teams = Team::all();



        DB::table('users')->insert([
            ['id' => 1, 'name' => 'admin', 'team_id' => 4, 'is_enabled' => 1, 'image' => "admin.jpg", 'image_crop' => "admin_crop", 'birthday'=> "22.11.1989", 'email'=> 'oabernathy@example.org', 'role'=> 'admin', 'email_verified_at'=> '2024-08-06 11:02:45', 'password'=> '', 'remember_token'=> 'MN8iqh4oU3', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 2, 'name' => 'user1', 'team_id' => 2, 'is_enabled' => 1, 'image' => "image1.jpg", 'image_crop' => "user1_crop.jpg", 'birthday'=> "1.06.1989", 'email'=> 'user1@example.org', 'role'=> 'user', 'email_verified_at'=> '2024-08-06 11:02:45', 'password'=> '', 'remember_token'=> 'MN8iqh4oU3', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 3, 'name' => 'user2', 'team_id' => 5, 'is_enabled' => 1, 'image' => "image2.jpg", 'image_crop' => "user2_crop.jpg", 'birthday'=> "2.06.1989", 'email'=> 'user2@example.org', 'role'=> 'user', 'email_verified_at'=> '2024-08-06 11:02:45', 'password'=> '', 'remember_token'=> 'MN8iqh4oU3', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 4, 'name' => 'user3', 'team_id' => 7, 'is_enabled' => 1, 'image' => "image3.jpg", 'image_crop' => "user3_crop.jpg", 'birthday'=> "3.06.1989", 'email'=> 'user3@example.org', 'role'=> 'user', 'email_verified_at'=> '2024-08-06 11:02:45', 'password'=> '', 'remember_token'=> 'MN8iqh4oU3', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
        ]);
        //        User::factory(200)->create();

//            спасибо
//Сделай тоже самое для
//        INSERT INTO `users` (`id`, `name`, `team_id`, `is_enabled`, `image`, `image_crop`, `birthday`, `email`, `role`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
//    (1, 'admin', 4, 1, '', '', NULL, 'oabernathy@example.org', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'MN8iqh4oU3', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(2, 'Устьян Евгений', 3, 1, '', '', NULL, 'kuvalis.florence@example.org', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', '2uWYXulOUm', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(3, 'Федюшин Иван', 3, 1, '', '', NULL, 'mariam59@example.net', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'T4BaxBSnkG', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(4, 'Белявский Елисей', 2, 1, '', '', NULL, 'xkuhn@example.net', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'cwMS1dFyam', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(5, 'Бакиров Егор', 2, 1, '', '', NULL, 'kschroeder@example.com', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'IpIW52mlwG', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(6, 'Павлов Александр', 3, 1, '', '', NULL, 'lou.barrows@example.org', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'pUu4AkYlAa', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(7, 'Панов Никита', 3, 1, '', '', NULL, 'pollich.art@example.com', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'Wpa7MGiN2Q', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(8, 'Васильев Виктор', 1, 1, '', '', NULL, 'hilda79@example.net', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'UDOGyRXO6e', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(9, 'Петров Владислав', 4, 1, '', '', NULL, 'kristoffer.rosenbaum@example.com', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'H4r7X99ZTa', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(10, 'Атамян Артур', 3, 1, '', '', NULL, 'loberbrunner@example.com', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'xRnl3wvZmH', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(11, 'Михайлов Иван', 5, 1, '', '', NULL, 'krystal.roberts@example.org', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', '649ZlNviNl', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(12, 'Апреликов Владимир', 4, 1, '', '', NULL, 'ottis.kiehn@example.com', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'NrO1W498BU', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(13, 'Журавель Артем', 2, 1, '', '', NULL, 'adam.grady@example.com', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'knuNtW73o0', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(14, 'Шевцов Ерёма', 5, 1, '', '', NULL, 'von.lolita@example.net', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'iqZaxfmYtS', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(15, 'Хорошилов Иван', 2, 1, '', '', NULL, 'rollin19@example.com', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'tH1Tl2WYoi', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(16, 'Лавров Владимир', 4, 1, '', '', NULL, 'waters.steve@example.net', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'GXHQvybTg9', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(17, 'Голубев Николай', 2, 1, '', '', NULL, 'emmie.schulist@example.org', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'PpGoURhdsK', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(18, 'Иванов Александр', 3, 1, '', '', NULL, 'metz.josiane@example.org', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', '2HnKtZqjOh', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(19, 'Михайлов Олег', 4, 1, '', '', NULL, 'gulgowski.cielo@example.org', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'yS0J9nQsmM', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(20, 'Воробьёв Валентин', 1, 1, '', '', NULL, 'fkub@example.net', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'qMoNzH5T0S', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(21, 'Юрков Виктор', 2, 1, '', '', NULL, 'hudson.kassulke@example.org', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'aPQBaLDE0W', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(22, 'Перов Юрий', 1, 1, '', '', NULL, 'mraz.mckayla@example.net', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'ssF37abFy9', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(23, 'Иванова Марина', 3, 1, '', '', NULL, 'veronica91@example.org', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'YLRAB3vMFy', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(24, 'Волков Сергей', 4, 1, '', '', NULL, 'kaci76@example.net', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'cqfrFv0aaL', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL),
//(25, 'Шестаков Василий', 1, 1, '', '', NULL, 'bward@example.net', '', '2024-08-06 11:02:45', '$2y$12$AOaz4uytIO/Cavd.3Wbq0uMjui.Vb6lgAxz503yx3uOAUadO3dn8a', 'hYFlxbJzrn', '2024-08-06 11:02:46', '2024-08-06 11:02:46', NULL);





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
