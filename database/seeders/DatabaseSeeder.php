<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */

    public function run(): void
    {
        $this->call([
            WeekdaysSeeder::class, // добавляем дни недели
            RolesSeeder::class, // добавляем роли
            PermissionGroupsSeeder::class, // добавляем группы разрешений
            PermissionSeeder::class, // добавляем разрешения
            SocialNetworksSeeder::class, // добавляем социальные сети
        ]);

        if (env('SEED_DEV_DATA', false)) {
            $this->call([
                DevPartnersSeeder::class, // добавляем случайных партнеров
                UserRoleBasePermissionsSeeder::class, // добавляем разрешения для пользователей
                AdminRoleBasePermissionsSeeder::class, // добавляем разрешения для администраторов
                DevTeamsSeeder::class, // добавляем команды
                DevAdminsSeeder::class, // добавляем администраторов
                DevUsersSeeder::class, // добавляем рандомных пользователей
                DevPricesSeeder::class, // добавляем цены
                DevPaymentSystemsSeeder::class,
                IstokMenuSeeder::class, // добавляем меню для Истока
            ]);
        }
    } 
}