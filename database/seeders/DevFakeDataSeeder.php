<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DevFakeDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Важно: порядок сохранён как в твоём исходнике
        $this->call([
            DevPartnersSeeder::class,       // 1) партнеры
            DevTeamsSeeder::class,          // 2) команды + weekdays
            DevAdminsSeeder::class,         // 3) админы
            DevUsersSeeder::class,          // 4) юзеры
            DevPricesSeeder::class,         // 5) установка цен/долгов
            DevPaymentSystemsSeeder::class, // 6) платежные системы
        ]);
    }
}