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
use Illuminate\Support\Str;


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
            ['id' => 1, 'name' => 'admin',                  'team_id' => 4, 'is_enabled' => 1, 'image' => "admin.jpg",                      'image_crop' => "admin_crop",                           'start_date' => null, 'birthday' => null, 'email' => 'oabernathy@example.org',                    'role' => 'admin','email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oU3', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 2, 'name' => 'user1',                  'team_id' => 2, 'is_enabled' => 1, 'image' => "image1.jpg",                     'image_crop' => "user1_crop.jpg",                       'start_date' => null, 'birthday' => null, 'email' => 'user1@example.org',                         'role' => 'user', 'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oU3', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 3, 'name' => 'user2',                  'team_id' => 5, 'is_enabled' => 1, 'image' => "image2.jpg",                     'image_crop' => "user2_crop.jpg",                       'start_date' => null, 'birthday' => null, 'email' => 'user2@example.org',                         'role' => 'user', 'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oU3', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 4, 'name' => 'user3',                  'team_id' => 7, 'is_enabled' => 1, 'image' => "image3.jpg",                     'image_crop' => "user3_crop.jpg",                       'start_date' => null, 'birthday' => null, 'email' => 'user3@example.org',                         'role' => 'user', 'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oU3', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 5, 'name' => 'admin@admin.ru',         'team_id' => 7, 'is_enabled' => 1, 'image' => "image_admin.jpg",                'image_crop' => "admin_crop.jpg",                       'start_date' => null, 'birthday' => null, 'email' => 'admin@admin.ru',                            'role' => 'admin',  'email_verified_at' => Carbon::now(), 'password' => '$2y$12$wI.nc4BT.Uek3z2CShmQn.gBxRvX8D0cIFm6dWyG3SWPfFH3u6Jea', 'remember_token' => 'MN8iqh4oU4', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 6, 'name' => 'Устьян Евгений',         'team_id' => 7, 'is_enabled' => 1, 'image' => "image_ustyan_evgeniy.jpg",       'image_crop' => "ustyan_evgeniy_crop.jpg",              'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oU5', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 7, 'name' => 'Федюшин Иван',           'team_id' => 4, 'is_enabled' => 1, 'image' => "image_fedyushin_ivan.jpg",       'image_crop' => "fedyushin_ivan_crop.jpg",              'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oU6', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 8, 'name' => 'Белявский Елисей',       'team_id' => 4, 'is_enabled' => 1, 'image' => "image_belyavskiy_elisey.jpg",    'image_crop' => "belyavskiy_elisey_crop.jpg",           'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oU7', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 9, 'name' => 'Бакиров Егор',           'team_id' => 4, 'is_enabled' => 1, 'image' => "image_bakirov_egor.jpg",         'image_crop' => "bakirov_egor_crop.jpg",                'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oU8', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 10, 'name' => 'Павлов Александр',      'team_id' => 4, 'is_enabled' => 1, 'image' => "image_pavlov_alexander.jpg",     'image_crop' => "pavlov_alexander_crop.jpg",            'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oU9', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 11, 'name' => 'Панов Никита',          'team_id' => 2, 'is_enabled' => 1, 'image' => "image_panov_nikita.jpg",         'image_crop' => "panov_nikita_crop.jpg",                'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUA', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 12, 'name' => 'Васильев Виктор',       'team_id' => 5, 'is_enabled' => 1, 'image' => "image_vasilev_viktor.jpg",       'image_crop' => "vasilev_viktor_crop.jpg",              'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUB', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 13, 'name' => 'Петров Владислав',      'team_id' => 1, 'is_enabled' => 1, 'image' => "image_petrov_vladislav.jpg",     'image_crop' => "petrov_vladislav_crop.jpg",            'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUC', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 14, 'name' => 'Атамян Артур',          'team_id' => 5, 'is_enabled' => 1, 'image' => "image_atamyan_arthur.jpg",       'image_crop' => "atamyan_arthur_crop.jpg",              'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUD', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 15, 'name' => 'Михайлов Иван',         'team_id' => 4, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 16, 'name' => 'Апреликов Владимир',    'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 17, 'name' => 'Журавель Артем',        'team_id' => 4, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 18, 'name' => 'Шевцов Ерёма',          'team_id' => 4, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 19, 'name' => 'Хорошилов Иван',        'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 20, 'name' => 'Лавров Владимир',       'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 21, 'name' => 'Петров Ярослав',        'team_id' => 1, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 22, 'name' => 'Огур Александр',        'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 23, 'name' => 'Чайковский Илья',       'team_id' => 4, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 24, 'name' => 'Антадзе Авто',          'team_id' => 4, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 25, 'name' => 'Белявский Тимофей',     'team_id' => 4, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 26, 'name' => 'Суворов Кирилл',        'team_id' => 4, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 27, 'name' => 'Ильин Матвей',          'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 28, 'name' => 'Савилов Кирилл',        'team_id' => 1, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 29, 'name' => 'Иванов Всеволод',       'team_id' => 1, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 30, 'name' => 'Коршиков Артём',        'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 31, 'name' => 'Котов Егор',            'team_id' => 1, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 32, 'name' => 'Лепехин Константин',    'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 33, 'name' => 'Мячин Лука',            'team_id' => 1, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 34, 'name' => 'Никишов Вячеслав',      'team_id' => 1, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 35, 'name' => 'Карсаков Елисей',       'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 36, 'name' => 'Усиков Илья',           'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 37, 'name' => 'Кусмарцев Денис',       'team_id' => 1, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 38, 'name' => 'Юбрин Даниил',          'team_id' => 1, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 39, 'name' => 'Кривелев Павел',        'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 40, 'name' => 'Божбов Константин',     'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 41, 'name' => 'Негазов Георгий',       'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 42, 'name' => 'Антадзе Сандро',        'team_id' => 4, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 43, 'name' => 'Акимов Кирилл',         'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 44, 'name' => 'Панченко Матвей',       'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 45, 'name' => 'Дикович Ярослав',       'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 46, 'name' => 'Антадзе Дато',          'team_id' => 1, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 47, 'name' => 'Павлов Савелий',        'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 48, 'name' => 'Шмаков Сергей',         'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 49, 'name' => 'Журин Олег',            'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 50, 'name' => 'Бабиченко Александр',   'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 51, 'name' => 'Иванов Никита',         'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 52, 'name' => 'Лаврентьев Роман',      'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 53, 'name' => 'Антадзе Авто',          'team_id' => 7, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 54, 'name' => 'Шелыгин Кирилл',        'team_id' => 3, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 55, 'name' => 'Игнатенко Дима',        'team_id' => 3, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 56, 'name' => 'Тяпов Егор',            'team_id' => 3, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 57, 'name' => 'Бальский Павел',        'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 58, 'name' => 'Мардашов Вячеслав',     'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 59, 'name' => 'Швецов Максим',         'team_id' => 4, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 60, 'name' => 'Бушуев Никита',         'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 61, 'name' => 'Сильванович Семен',     'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 62, 'name' => 'Игорь Лютый',           'team_id' => 7, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 63, 'name' => 'Кожуров Никита',        'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 64, 'name' => 'Малышенков Павел',      'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 65, 'name' => 'Анциферов Леонид',      'team_id' => 3, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 66, 'name' => 'Белов Федор',           'team_id' => 1, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 67, 'name' => 'Семенов Матвей',        'team_id' => 3, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 68, 'name' => 'Сандалкин Владимир',    'team_id' => 3, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 69, 'name' => 'Савилов Александр',     'team_id' => 2, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 70, 'name' => 'Гавришев Николай',      'team_id' => 5, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            ['id' => 71, 'name' => 'Новоторов Георгий',     'team_id' => 4, 'is_enabled' => 1, 'image' => "",                               'image_crop' => "",                                     'start_date' => null, 'birthday' => null, 'email' => Str::random(8) . '@example.com',       'role' => 'user',   'email_verified_at' => Carbon::now(), 'password' => '', 'remember_token' => 'MN8iqh4oUE', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null],
            // ... далее аналогично для всех остальных имен

        ]);

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
