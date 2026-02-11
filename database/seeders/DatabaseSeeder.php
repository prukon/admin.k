<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\SocialItem;
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



        // тест+прод
        $this->call(WeekdaysSeeder::class);         //ДНИ НЕДЕЛИ
        $this->call(RolesSeeder::class);            //СИСТЕМНЫЕ РОЛИ
        $this->call(PermissionGroupsSeeder::class); //ГРУППЫ ПРАВ
        $this->call(PermissionSeeder::class);       //ПРАВА (НАИМЕНОВАНИЯ)


        // 2. Фейковые данные
        if (app()->environment('local')) {
            $this->call(DevFakeDataSeeder::class);
            $this->call(UserRoleBasePermissionsSeeder::class); //ПРАВА ЮЗЕРУ
            $this->call(AdminRoleBasePermissionsSeeder::class); //ПРАВА АДМИНУ
            $this->call(IstokMenuSeeder::class);
        }

    }
}
