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

            ['name' => "admin@admin.ru",            'team_id' => 1, 'birthday' => null,             'start_date' => "2018-02-22", 'email' => "admin@admin.ru",                        'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2018-02-22 10:38:00",     'role' => 'admin',  'password' => '$2y$12$wI.nc4BT.Uek3z2CShmQn.gBxRvX8D0cIFm6dWyG3SWPfFH3u6Jea', ],
            ['name' => "user@user.ru",              'team_id' => 1, 'birthday' => "1989-06-01",     'start_date' => "2018-07-22", 'email' => "user@user.ru" ,                         'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2018-07-22 16:01:00",     'role' => 'user',   'password' => '$2y$12$nC02CRQ7SJ85bd89/Qoh7eYvTaWOMiIytU3p9B1.xACMzAgrefUNK', ],
            ['name' => "Федюшин Иван",              'team_id' => 4, 'birthday' => null,             'start_date' => "2018-11-12", 'email' => "test9@mail.ru",                         'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2018-11-12 17:32:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Белявский Елисей",          'team_id' => 4, 'birthday' => "2013-01-14",     'start_date' => "2018-11-12", 'email' => "germina99@mail.ru",                     'is_enabled' => 1,    'image_crop' => "xz8jmGjthN.png",               'created_at' => "2018-11-12 17:37:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Бакиров Егор",              'team_id' => 4, 'birthday' => null,             'start_date' => "2018-11-12", 'email' => "test12@mail.ru",                        'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2018-11-12 17:40:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Павлов Александр",          'team_id' => 4, 'birthday' => "2011-10-26",     'start_date' => "2018-11-12", 'email' => "glamm32@gmail.com",                     'is_enabled' => 1,    'image_crop' => "Erqz1QAvXd.png",               'created_at' => "2018-11-12 18:06:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Панов Никита",              'team_id' => 2, 'birthday' => null,             'start_date' => "2018-11-12", 'email' => "volpan@yandex.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2018-11-12 18:47:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Васильев Виктор",           'team_id' => 5, 'birthday' => null,             'start_date' => "2018-12-06", 'email' => "chalyshewa@yandex.ru",                  'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2018-12-06 14:22:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Петров Владислав",          'team_id' => 1, 'birthday' => null,             'start_date' => "2018-12-06", 'email' => "test41@mail.ru",                        'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2018-12-06 15:10:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Атамян Артур",              'team_id' => 5, 'birthday' => "2020-08-17",     'start_date' => "2019-04-08", 'email' => "test17@mail.ru",                        'is_enabled' => 1,    'image_crop' => "ATj3APBI3l.png",               'created_at' => "2019-04-08 11:57:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Михайлов Иван",             'team_id' => 4, 'birthday' => "2012-10-23",     'start_date' => "2019-09-06", 'email' => "test120@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2019-09-06 10:50:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Апреликов Владимир",        'team_id' => 5, 'birthday' => null,             'start_date' => "2019-09-06", 'email' => "test124@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2019-09-06 11:03:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Журавель Артем",            'team_id' => 4, 'birthday' => "2020-09-01",     'start_date' => "2019-09-06", 'email' => "test126@mail.ru",                       'is_enabled' => 1,    'image_crop' => "cufC6DuXJC.png",               'created_at' => "2019-09-06 11:06:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Шевцов Ерёма",              'team_id' => 4, 'birthday' => "2011-11-15",     'start_date' => "2019-09-06", 'email' => "9666411@bk.ru",                         'is_enabled' => 1,    'image_crop' => "94CvKDMGu2.png",               'created_at' => "2019-09-06 11:17:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Хорошилов Иван",            'team_id' => 2, 'birthday' => null,             'start_date' => "2019-09-06", 'email' => "test131@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2019-09-06 11:51:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Лавров Владимир",           'team_id' => 2, 'birthday' => "2019-10-15",     'start_date' => "2019-09-06", 'email' => "lav_m@list.ru",                         'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2019-09-06 11:59:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Петров Ярослав",            'team_id' => 1, 'birthday' => "2009-11-19",     'start_date' => "2019-09-08", 'email' => "test151@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2019-09-08 12:31:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Огур Александр",            'team_id' => 5, 'birthday' => null,             'start_date' => "2019-09-19", 'email' => "test172@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2019-09-19 16:38:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Чайковский Илья",           'team_id' => 4, 'birthday' => "2019-10-06",     'start_date' => "2019-09-19", 'email' => "test175@mail.ru",                       'is_enabled' => 1,    'image_crop' => "KLuE5PjmHw.png",               'created_at' => "2019-09-19 16:41:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Пименов Даниэль",           'team_id' => 3, 'birthday' => null,             'start_date' => "2019-09-24", 'email' => "test178@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2019-09-24 18:01:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Белявский Тимофей",         'team_id' => 4, 'birthday' => "2010-11-30",     'start_date' => "2019-10-03", 'email' => "test182@mail.ru",                       'is_enabled' => 1,    'image_crop' => "ULJJP0ESVy.png",               'created_at' => "2019-10-03 13:32:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Суворов Кирилл",            'team_id' => 4, 'birthday' => null,             'start_date' => "2019-10-05", 'email' => "irinapuch@mail.ru",                     'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2019-10-05 20:44:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Ильин Матвей",              'team_id' => 5, 'birthday' => "2019-10-01",     'start_date' => "2019-10-05", 'email' => "test191@mail.ru",                       'is_enabled' => 1,    'image_crop' => "oqi6fx54Ft.png",               'created_at' => "2019-10-05 20:45:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Савилов Кирилл",            'team_id' => 1, 'birthday' => "2011-04-15",     'start_date' => "2020-01-23", 'email' => "test205@mail.ru",                       'is_enabled' => 1,    'image_crop' => "ip26Ndw8Ow.png",               'created_at' => "2020-01-23 12:25:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Иванов Всеволод",           'team_id' => 1, 'birthday' => null,             'start_date' => "2020-09-14", 'email' => "eugeniakurteva@gmail.com",              'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2020-09-14 20:26:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Коршиков Артём",            'team_id' => 2, 'birthday' => "2012-08-02",     'start_date' => "2020-09-14", 'email' => "vk-ppk@yandex.ru",                      'is_enabled' => 1,    'image_crop' => "oMmKUVP8oz.png",               'created_at' => "2020-09-14 21:00:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Котов Егор",                'team_id' => 1, 'birthday' => "2009-04-09",     'start_date' => "2020-09-19", 'email' => "test422@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2020-09-19 16:04:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Лепехин Константин",        'team_id' => 2, 'birthday' => null,             'start_date' => "2020-09-23",  'email' => "test425@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2020-09-23 8:32:00",    'role' => 'user',   'password' => '', ],
            ['name' => "Мячин Лука",                'team_id' => 1, 'birthday' => null,             'start_date' => "2020-09-25", 'email' => "test403@mail.ru",                       'is_enabled' => 1,    'image_crop' => "HwCFpb2ZJf.png",               'created_at' => "2020-09-25 13:20:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Никишов Вячеслав",          'team_id' => 1, 'birthday' => "2009-01-21",     'start_date' => "2020-09-25", 'email' => "test433@mail.ru",                       'is_enabled' => 1,    'image_crop' => "z8nZhGzkHC.png",               'created_at' => "2020-09-25 14:24:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Карсаков Елисей",           'team_id' => 5, 'birthday' => "2013-01-06",     'start_date' => "2020-11-05", 'email' => "test480@mail.ru",                       'is_enabled' => 1,    'image_crop' => "ScAJ2bh7TY.png",               'created_at' => "2020-11-05 19:48:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Усиков Илья",               'team_id' => 5, 'birthday' => null,             'start_date' => "2021-04-18", 'email' => "iusikov@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2021-04-18 10:02:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Кусмарцев Денис",           'team_id' => 1, 'birthday' => "2021-12-09",     'start_date' => "2021-09-06", 'email' => "test705@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2021-09-06 21:50:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Юбрин Даниил",              'team_id' => 1, 'birthday' => "2008-07-03",     'start_date' => "2021-09-06", 'email' => "test706@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2021-09-06 21:51:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Кривелев Павел",            'team_id' => 2, 'birthday' => null,             'start_date' => "2021-09-06", 'email' => "test713@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2021-09-06 22:01:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Божбов Константин",         'team_id' => 2, 'birthday' => "2012-06-23",     'start_date' => "2021-09-06", 'email' => "lobol@yandex.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2021-09-06 22:02:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Негазов Георгий",           'team_id' => 5, 'birthday' => "2021-10-01",     'start_date' => "2021-09-17", 'email' => "test722@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2021-09-17 14:26:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Антадзе Сандро",            'team_id' => 4, 'birthday' => null,             'start_date' => "2021-09-29", 'email' => "test740@mail.ru",                       'is_enabled' => 1,    'image_crop' => "Dy97AS0Jq4.png",               'created_at' => "2021-09-29 19:59:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Акимов Кирилл",             'team_id' => 2, 'birthday' => "2021-10-01",     'start_date' => "2021-09-29", 'email' => "p9219004159@yandex.ru",                 'is_enabled' => 1,    'image_crop' => "TDceruuuQy.png",               'created_at' => "2021-09-29 20:10:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Панченко Матвей",           'team_id' => 2, 'birthday' => "2009-03-24",     'start_date' => "2021-09-30", 'email' => "pan72@bk.ru",                           'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2021-09-30 9:07:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Дикович Ярослав",           'team_id' => 2, 'birthday' => "2012-08-03",     'start_date' => "2021-11-29", 'email' => "test770@mail.ru",                       'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2021-11-29 11:05:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Антадзе Дато",              'team_id' => 1, 'birthday' => null,             'start_date' => "2021-12-20", 'email' => "test888@mail.ru",                       'is_enabled' => 1,    'image_crop' => "5JAeMKyHTU.png",               'created_at' => "2021-12-20 13:55:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Павлов Савелий",            'team_id' => 2, 'birthday' => "2011-07-09",     'start_date' => "2022-09-06", 'email' => "seregamarkx@mail.ru",                   'is_enabled' => 1,    'image_crop' => "HVuAttlfnS.png",               'created_at' => "2022-09-06 17:47:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Шмаков Сергей",             'team_id' => 5, 'birthday' => null,             'start_date' => "2022-09-06", 'email' => "test1008@mail.ru",                      'is_enabled' => 1,    'image_crop' => "Ll7aFSDHfa.png",               'created_at' => "2022-09-06 17:49:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Журин Олег",                'team_id' => 2, 'birthday' => "2012-03-27",     'start_date' => "2022-09-09", 'email' => "pavel-zhurin@mail.ru",                  'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2022-09-09 12:26:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Бабиченко Александр",       'team_id' => 2, 'birthday' => "2012-06-01",     'start_date' => "2022-09-11", 'email' => "test1013@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2022-09-11 17:01:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Иванов Никита",             'team_id' => 2, 'birthday' => "2012-11-13",     'start_date' => "2022-09-18", 'email' => "test1015@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2022-09-18 16:30:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Лаврентьев Роман",          'team_id' => 2, 'birthday' => "2023-02-26",     'start_date' => "2022-09-22", 'email' => "test1020@mail.ru",                      'is_enabled' => 1,    'image_crop' => "AzM5UfPJ46.png",               'created_at' => "2022-09-22 17:58:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Антадзе Авто",              'team_id' => 4, 'birthday' => null,             'start_date' => "2022-10-02", 'email' => "test1027@mail.ru",                      'is_enabled' => 1,    'image_crop' => "NRVQ65XuZl.png",               'created_at' => "2022-10-02 9:12:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Шелыгин Кирилл",            'team_id' => 3, 'birthday' => "2015-07-24",     'start_date' => "2022-10-04", 'email' => "test1040@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2022-10-04 10:23:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Игнатенко Дима",            'team_id' => 3, 'birthday' => null,             'start_date' => "2022-10-04", 'email' => "test1042@mail.ru",                      'is_enabled' => 1,    'image_crop' => "lHhpGkEuLu.png",               'created_at' => "2022-10-04 10:30:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Тяпов Егор",                'team_id' => 3, 'birthday' => "2013-09-19",     'start_date' => "2022-10-04", 'email' => "vpegasik@yandex.ru",                    'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2022-10-04 11:19:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Бальский Павел",            'team_id' => 5, 'birthday' => "2013-08-07",     'start_date' => "2022-10-06", 'email' => "o.balskaya@yandex.ru",                  'is_enabled' => 1,    'image_crop' => "UjJQCLg0MB.png",               'created_at' => "2022-10-06 21:28:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Мардашов Вячеслав",         'team_id' => 2, 'birthday' => "2024-04-02",     'start_date' => "2022-10-20", 'email' => "test1060@mail.ru",                      'is_enabled' => 1,    'image_crop' => "PSR4lBzq6N.png",               'created_at' => "2022-10-20 16:11:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Швецов Максим",             'team_id' => 4, 'birthday' => null,             'start_date' => "2022-11-22", 'email' => "test1069@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2022-11-22 9:14:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Бушуев Никита",             'team_id' => 2, 'birthday' => null,             'start_date' => "2023-04-20", 'email' => "test1082@mail.ru",                      'is_enabled' => 1,    'image_crop' => "4yOUpfssUq.png",               'created_at' => "2023-04-20 12:21:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Сильванович Семен",         'team_id' => 5, 'birthday' => "2012-12-25",     'start_date' => "2023-05-18", 'email' => "test1091@mail.ru",                      'is_enabled' => 1,    'image_crop' => "179NaLJhGY.png",               'created_at' => "2023-05-18 10:57:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Игорь Лютый",               'team_id' => 0, 'birthday' => null,             'start_date' => "2023-08-31", 'email' => "info@fc-istok.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2023-08-31 21:28:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Кожуров Никита",            'team_id' => 2, 'birthday' => "2011-11-30",     'start_date' => "2023-09-12", 'email' => "milanikson@gmail.com",                  'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2023-09-12 19:53:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Малышенков Павел",          'team_id' => 5, 'birthday' => "2023-07-21",     'start_date' => "2023-09-12", 'email' => "test1502@mail.ru",                      'is_enabled' => 1,    'image_crop' => "1HZmymMuxa.png",               'created_at' => "2023-09-12 20:04:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Анциферов Леонид",          'team_id' => 3, 'birthday' => "2015-12-09",     'start_date' => "2023-09-12", 'email' => "test1503@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2023-09-12 20:08:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Белов Федор",               'team_id' => 1, 'birthday' => null,             'start_date' => "2023-09-12", 'email' => "test1505@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2023-09-12 20:25:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Семенов Матвей",            'team_id' => 3, 'birthday' => null,             'start_date' => "2023-10-14", 'email' => "test1515@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2023-10-14 19:37:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Сандалкин Владимир",        'team_id' => 3, 'birthday' => null,             'start_date' => "2024-01-16", 'email' => "sandalkinvladimir@sandalkinvladimir.ru",'is_enabled' => 1,    'image_crop' => "LHm7ucw5fA.png",               'created_at' => "2024-01-16 15:16:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Савилов Александр",         'team_id' => 2, 'birthday' => "2013-10-29",     'start_date' => "2024-02-05", 'email' => "test1077@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2024-02-05 11:26:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Гавришев Николай",          'team_id' => 5, 'birthday' => null,             'start_date' => "2024-02-21", 'email' => "test1080@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2024-02-21 13:31:00",     'role' => 'user',   'password' => '', ],
            ['name' => "Новоторов Георгий",         'team_id' => 4, 'birthday' => null,             'start_date' => "2024-04-02", 'email' => "test1081@mail.ru",                      'is_enabled' => 1,    'image_crop' => "",                             'created_at' => "2024-04-02 9:13:00",      'role' => 'user',   'password' => '', ],
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
