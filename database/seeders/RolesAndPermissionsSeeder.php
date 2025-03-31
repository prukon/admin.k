<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Роли
        $adminRole = Role::create(['name' => 'admin', 'label' => 'Администратор']);
        $managerRole = Role::create(['name' => 'manager', 'label' => 'Менеджер']);
        $userRole = Role::create(['name' => 'user', 'label' => 'Пользователь']);


// Добавляем новые права
        $reports = Permission::create([
            'name' => 'reports',
            'description' => 'Отчеты'
        ]);

        $setPrices = Permission::create([
            'name' => 'set_prices',
            'description' => 'Установка цен'
        ]);

        $scheduleJournal = Permission::create([
            'name' => 'schedule_journal',
            'description' => 'Журнал расписания'
        ]);

        $manageUsers = Permission::create([
            'name' => 'manage_users',
            'description' => 'Управление пользователями'
        ]);

        $manageGroups = Permission::create([
            'name' => 'manage_groups',
            'description' => 'Управление группами'
        ]);

        $generalSettings = Permission::create([
            'name' => 'general_settings',
            'description' => 'Общие настройки'
        ]);

        $manageRoles = Permission::create([
            'name' => 'manage_roles',
            'description' => 'Управление ролями'
        ]);

        $servicePayment = Permission::create([
            'name' => 'service_payment',
            'description' => 'Оплата сервиса'
        ]);

        $changeHistory = Permission::create([
            'name' => 'change_history',
            'description' => 'История изменений'
        ]);

        // Привязываем права к ролям
// 1) Админ получает всё
        $adminRole->permissions()->attach([
            $reports->id,
            $setPrices->id,
            $scheduleJournal->id,
            $manageUsers->id,
            $manageGroups->id,
            $generalSettings->id,
            $manageRoles->id,
            $servicePayment->id,
            $changeHistory->id
        ]);

// 2) Менеджеру — только три права
        $managerRole->permissions()->attach([
            $scheduleJournal->id,  // Журнал расписания
            $manageUsers->id,      // Управление пользователями
            $changeHistory->id     // История изменений
        ]);
    }
}
