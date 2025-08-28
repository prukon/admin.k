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
        $superAdminRole = Role::create(['name' => 'user', 'label' => 'Суперадмин']);
        $adminRole = Role::create(['name' => 'admin', 'label' => 'Администратор']);
        $userRole = Role::create(['name' => 'user', 'label' => 'Пользователь']);
        $managerRole = Role::create(['name' => 'manager', 'label' => 'Тренер']);





// Добавляем новые права
        $reports = Permission::create([
            'name' => 'reports',
            'description' => 'Страница "Отчеты"',
            'sort_order' => 10,
        ]);

        $setPrices = Permission::create([
            'name' => 'set_prices',
            'description' => 'Страница "Установка цен"',
            'sort_order' => 20,
        ]);

        $scheduleJournal = Permission::create([
            'name' => 'schedule_journal',
            'description' => 'Страница "Журнал расписания"',
            'sort_order' => 30,
        ]);

        $manageUsers = Permission::create([
            'name' => 'manage_users',
            'description' => 'Страница "Пользователи"',
            'sort_order' => 40,
        ]);

        $manageGroups = Permission::create([
            'name' => 'manage_groups',
            'description' => 'Страница "Группы"',
            'sort_order' => 50,
        ]);

        $generalSettings = Permission::create([
            'name' => 'general_settings',
            'description' => 'Страница "Настройки"',
            'sort_order' => 60,
        ]);

        $partnerCompany = Permission::create([
            'name' => 'partner_company',
            'description' => 'Страница "Учетная запись -> Организация"',
            'sort_order' => 70,
        ]);

        $servicePayment = Permission::create([
            'name' => 'service_payment',
            'description' => 'Страница "Оплата сервиса"',
            'sort_order' => 80,
        ]);

        $myPayments = Permission::create([
            'name' => 'my_payments',
            'description' => 'Страница "Мои платежи"',
            'sort_order' => 90,
        ]);

        $payingClasses = Permission::create([
            'name' => 'paying_classes',
            'description' => 'Оплата учебных занятий',
            'sort_order' => 100,
        ]);

        $paymentClubFee = Permission::create([
            'name' => 'payment_clubfee',
            'description' => 'Оплата клубного взноса',
            'sort_order' => 110,
        ]);

        $nameEditing = Permission::create([
            'name' => 'name_editing',
            'description' => 'Изменение имени пользователя',
            'sort_order' => 120,
        ]);

        $changingYourGroup = Permission::create([
            'name' => 'changing_your_group',
            'description' => 'Изменение группы пользователя',
            'sort_order' => 130,
        ]);

        $changingUserActivity = Permission::create([
            'name' => 'changing_user_activity',
            'description' => 'Изменение активности пользователя',
            'sort_order' => 140,
        ]);

        $changingUserRules = Permission::create([
            'name' => 'changing_user_rules',
            'description' => 'Изменение роли пользователя',
            'sort_order' => 150,
        ]);

        $changingUserEmail = Permission::create([
            'name' => 'changing_user_email',
            'description' => 'Изменение email пользователя',
            'sort_order' => 160,
        ]);

//        $studentFilterConsole = Permission::create([
//            'name' => 'student_filter_console',
//            'description' => 'Фильтр учеников в консоли',
//            'sort_order' => 170,
//        ]);

        $changeHistory = Permission::create([
            'name' => 'change_history',
            'description' => 'Просмотр истории изменений',
            'sort_order' => 180,
        ]);

        $manageRoles = Permission::create([
            'name' => 'manage_roles',
            'description' => 'Управление ролями',
            'sort_order' => 190,
        ]);


        // Привязываем права к ролям
// 1) Супер получает всё
        $superAdminRole->permissions()->attach([
            $reports->id,
            $setPrices->id,
            $scheduleJournal->id,
            $manageUsers->id,
            $manageGroups->id,
            $generalSettings->id,
            $partnerCompany->id,
            $servicePayment->id,
            $myPayments->id,
            $payingClasses->id,
            $paymentClubFee->id,
            $nameEditing->id,
            $changingYourGroup->id,
            $changingUserActivity->id,
            $changingUserRules->id,
            $changingUserEmail->id,
            $studentFilterConsole->id,
            $changeHistory->id,
            $manageRoles->id,
        ]);




// 1) Админ получает всё
        $adminRole->permissions()->attach([
            $reports->id,
            $setPrices->id,
            $scheduleJournal->id,
            $manageUsers->id,
            $manageGroups->id,
            $generalSettings->id,
            $partnerCompany->id,
            $servicePayment->id,
//            $myPayments->id,
//            $payingClasses->id,
//            $paymentClubFee->id,
            $nameEditing->id,
            $changingYourGroup->id,
            $changingUserActivity->id,
            $changingUserRules->id,
            $changingUserEmail->id,
            $studentFilterConsole->id,
            $changeHistory->id,
            $manageRoles->id,
        ]);
    }
}
