<?php

//php artisan db:seed --class='Database\Seeders\PermissionSeeder' --force

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
//123
class PermissionSeeder extends Seeder
{

    public function run(): void
    {
        $now = Carbon::now();

        // Маппинг slug группы → id (чтобы не зависеть от номеров из дампа)
        $groupIdBySlug = DB::table('permission_groups')
            ->pluck('id', 'slug')
            ->all();

        // Полный список прав из permissions.sql, но с привязкой к group_slug. :contentReference[oaicite:3]{index=3}
        $permissions = [
            // group_slug mainMenu (id=1 в дампе)
            ['name'=>'dashboard.view','description'=>'Страница "Консоль"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>10],
            ['name'=>'reports.view','description'=>'Страница "Отчеты"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>15],
            ['name'=>'myPayments.view','description'=>'Страница "Мои платежи"','group_slug'=>'mainMenu','is_visible'=>0,'sort_order'=>16],
            ['name'=>'myGroup.view','description'=>'Страница "Моя группа"','group_slug'=>'mainMenu','is_visible'=>0,'sort_order'=>17],
            ['name'=>'setPrices.view','description'=>'Страница "Установка цен"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>20],
            ['name'=>'schedule.view','description'=>'Страница "Журнал расписания"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>30],
            ['name'=>'users.view','description'=>'Страница "Пользователи"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>40],
            ['name'=>'groups.view','description'=>'Страница "Группы"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>50],
            ['name'=>'contracts.view','description'=>'Страница "Договоры"','group_slug'=>'mainMenu','is_visible'=>0,'sort_order'=>51],
            ['name'=>'partner.view','description'=>'Страница "Партнеры"','group_slug'=>'mainMenu','is_visible'=>0,'sort_order'=>55],
            ['name'=>'settings.view','description'=>'Страница "Настройки"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>60],
            ['name'=>'settings.roles.view','description'=>'Страница "Настройки  -> Права и роли"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>61],
            ['name'=>'settings.paymentSystems.view','description'=>'Страница "Настройки  -> Платежные системы"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>62],
            ['name'=>'account.user.view','description'=>'Страница "Учетная запись -> Личные данные"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>65], // в дампе как id=35 (mainMenu) — оставляю как в дампе
            ['name'=>'account.partner.view','description'=>'Страница "Учетная запись -> Организация"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>70],
            ['name'=>'account.documents.view','description'=>'Страница "Учетная запись -> "Мои документы"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>70],

            ['name'=>'messages.view','description'=>'Страница "Сообщения"','group_slug'=>'mainMenu','is_visible'=>1,'sort_order'=>73],
            ['name'=>'leads.view','description'=>'Страница "Лиды"','group_slug'=>'mainMenu','is_visible'=>0,'sort_order'=>75],
            ['name'=>'servicePayments.view','description'=>'Страница "Оплата сервиса"','group_slug'=>'mainMenu','is_visible'=>0,'sort_order'=>80],
            ['name'=>'partnerWallet.view','description'=>'Страница "Кошелек"','group_slug'=>'mainMenu','is_visible'=>0,'sort_order'=>90],

            // account (id=2)
            ['name'=>'name_editing','description'=>'Изменение своего имени','group_slug'=>'account','is_visible'=>1,'sort_order'=>10],
            ['name'=>'account.user.birthdate.update','description'=>'Изменение своей даты рождения','group_slug'=>'account','is_visible'=>1,'sort_order'=>20],
            ['name'=>'changing_your_group','description'=>'Изменение своей группы','group_slug'=>'account','is_visible'=>0,'sort_order'=>30],
            ['name'=>'account.user.startDate.update','description'=>'Изменение даты своего начала занятий','group_slug'=>'account','is_visible'=>1,'sort_order'=>40],
            ['name'=>'changing_user_email','description'=>'Изменение своего email','group_slug'=>'account','is_visible'=>1,'sort_order'=>50],
            ['name'=>'account.user.phone.update','description'=>'Изменение своего телефона','group_slug'=>'account','is_visible'=>1,'sort_order'=>60],

            // users (id=3)
            ['name'=>'users.name.update','description'=>'Изменение имени','group_slug'=>'users','is_visible'=>1,'sort_order'=>0],
            ['name'=>'users.birthdate.update','description'=>'Изменение даты рождения','group_slug'=>'users','is_visible'=>1,'sort_order'=>0],
            ['name'=>'users.group.update','description'=>'Изменение группы','group_slug'=>'users','is_visible'=>1,'sort_order'=>0],
            ['name'=>'users.startDate.update','description'=>'Изменение даты начала занятий','group_slug'=>'users','is_visible'=>1,'sort_order'=>0],
            ['name'=>'users.email.update','description'=>'Изменение email','group_slug'=>'users','is_visible'=>1,'sort_order'=>0],
            ['name'=>'users.activity.update','description'=>'Изменение активности (отключение)','group_slug'=>'users','is_visible'=>1,'sort_order'=>0],
            ['name'=>'users.role.update','description'=>'Изменение роли (прав)','group_slug'=>'users','is_visible'=>1,'sort_order'=>0],
            ['name'=>'users.password.update','description'=>'Изменение пароля','group_slug'=>'users','is_visible'=>1,'sort_order'=>0],
            ['name'=>'users.phone.update','description'=>'Изменение телефона','group_slug'=>'users','is_visible'=>1,'sort_order'=>0],

            // misc (id=4)
            ['name'=>'paying_classes','description'=>'Оплата учебных занятий','group_slug'=>'misc','is_visible'=>0,'sort_order'=>100],
            ['name'=>'payment_clubfee','description'=>'Оплата клубного взноса','group_slug'=>'misc','is_visible'=>1,'sort_order'=>175],
            ['name'=>'change_history','description'=>'Просмотр истории изменений','group_slug'=>'misc','is_visible'=>0,'sort_order'=>180],
            ['name'=>'manage_roles','description'=>'Управление ролями','group_slug'=>'misc','is_visible'=>0,'sort_order'=>190],
            ['name'=>'changing_partner','description'=>'Изменение партнера','group_slug'=>'misc','is_visible'=>0,'sort_order'=>190],
            ['name'=>'setting_payment_systems','description'=>'Настройка платежных систем','group_slug'=>'misc','is_visible'=>0,'sort_order'=>200],
            ['name'=>'payment_method_T-Bank','description'=>'Способ оплаты "Т-Банк','group_slug'=>'misc','is_visible'=>0,'sort_order'=>200],
            ['name'=>'viewing_all_logs','description'=>'Просмотр всех логов','group_slug'=>'misc','is_visible'=>0,'sort_order'=>210],


//            ['name'=>'student_filter_console','description'=>'Фильтр учеников в консоли','group_slug'=>'misc','is_visible'=>0,'sort_order'=>170],

            // Доп. пункты из дампа (main menu)

        ];

        foreach ($permissions as $p) {
            $groupId = $groupIdBySlug[$p['group_slug']] ?? null;
            DB::table('permissions')->updateOrInsert(
                ['name' => $p['name']],
                [
                    'description'          => $p['description'],
                    'permission_group_id'  => $groupId,
                    'is_visible'           => $p['is_visible'],
                    'sort_order'           => $p['sort_order'],
                    'updated_at'           => $now,
                    'created_at'           => DB::raw("COALESCE(created_at, '{$now->toDateTimeString()}')")
                ]
            );
        }
    }
}
