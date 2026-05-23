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
            LessonOccurrenceStatusesSeeder::class, // статусы занятий (абонементы), идемпотентно по партнёрам
        ]);

        if (env('SEED_DEV_DATA', false)) {
            $this->call([
                DevPartnersSeeder::class, // добавляем случайных партнеров
                DevScheduleStatusesSeeder::class, // статусы посещаемости журнала /schedule (dev)
                DevUserRoleBasePermissionsSeeder::class, // базовые права роли user для всех партнёров (dev)
                DevAdminRoleBasePermissionsSeeder::class, // базовые права роли admin для всех партнёров (dev)
                DevTrainerRoleBasePermissionsSeeder::class, // базовые права роли trainer для всех партнёров (dev)
                DevTeamsSeeder::class, // добавляем команды
                DevLocationsSeeder::class, // локации (dev)
                DevAdminsSeeder::class, // добавляем администраторов
                DevUsersSeeder::class, // добавляем рандомных пользователей
                DevTrainersSeeder::class, // тренеры (dev)
                DevTrainerSalaryDefaultsSeeder::class, // оклад и ставка за тренировку у всех тренеров (dev)
                DevScheduleJournalSeeder::class, // журнал /schedule: посещаемость за 6 мес. (dev)
                DevSchoolLeadsSeeder::class, // заявки с сайта (dev)
                DevLessonPackagesSeeder::class, // шаблоны абонементов (dev)
                DevSchoolScheduleSeeder::class, // расписание школы и слоты (dev)
                DevLessonPackageAssignmentsSeeder::class, // назначения абонементов (dev)
                DevPricesSeeder::class, // добавляем цены
                DevPaymentSystemsSeeder::class,
                DevIstokMenuSeeder::class, // демо-партнёр «Исток», команды, меню (dev)
            ]);
        }
    } 
}