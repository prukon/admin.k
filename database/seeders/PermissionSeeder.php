<?php

// php artisan db:seed --class='Database\Seeders\PermissionSeeder' --force

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Маппинг slug группы → id (не завязаны на конкретные номера)
        // ВАЖНО: PermissionGroupsSeeder должен быть запущен раньше.
        $groupIdBySlug = DB::table('permission_groups')
            ->pluck('id', 'slug')
            ->all();

        // Полный список прав с привязкой к group_slug.
        $permissions = [
            // group_slug mainMenu
            ['name' => 'dashboard.view',                 'description' => 'Страница "Консоль"',                            'group_slug' => 'mainMenu', 'is_visible' => 1, 'sort_order' => 10],
            ['name' => 'reports.view',                   'description' => 'Страница "Отчеты"',                             'group_slug' => 'mainMenu', 'is_visible' => 1, 'sort_order' => 15],
            ['name' => 'reports.payment.intents.view',   'description' => 'Страница "Платежные запросы"',                  'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 16],
            ['name' => 'reports.fiscal.receipts.view',   'description' => 'Страница "Чеки"',                               'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 16],
            ['name' => 'reports.additional.value.view',  'description' => 'Страница "Отчеты (доп. значения)"',             'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 17],
            ['name' => 'reports.payments.totals.net_to_partner.view', 'description' => 'Отчёт «Платежи»: итог «К выплате» в шапке', 'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 18],
            ['name' => 'reports.payments.totals.payout_amount.view', 'description' => 'Отчёт «Платежи»: итог «Выплата» в шапке', 'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 19],
            ['name' => 'reports.payments.totals.platform_commission.view', 'description' => 'Отчёт «Платежи»: итог комиссии платформы в шапке', 'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 20],
            ['name' => 'myPayments.view',                'description' => 'Страница "Мои платежи"',                        'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 16],
            ['name' => 'myGroup.view',                   'description' => 'Страница "Моя группа"',                         'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 17],
            ['name' => 'setPrices.view',                 'description' => 'Страница "Установка цен"',                      'group_slug' => 'mainMenu', 'is_visible' => 1, 'sort_order' => 20],
            ['name' => 'setPrices.manualPaid.manage',    'description' => 'Установка цен: ручная отметка оплаты месяца',    'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 21],
            ['name' => 'schedule.view',                  'description' => 'Страница "Журнал расписания"',                  'group_slug' => 'mainMenu', 'is_visible' => 1, 'sort_order' => 30],
            ['name' => 'users.view',                     'description' => 'Страница "Пользователи"',                       'group_slug' => 'mainMenu', 'is_visible' => 1, 'sort_order' => 40],
            ['name' => 'groups.view',                    'description' => 'Страница "Группы"',                             'group_slug' => 'mainMenu', 'is_visible' => 1, 'sort_order' => 50],
            ['name' => 'contracts.view',                 'description' => 'Страница "Договоры"',                           'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 51],
            ['name' => 'contracts.sync',                 'description' => 'Договоры: синхронизация статуса с Подпислон',   'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 52],
            ['name' => 'partner.view',                   'description' => 'Страница "Партнеры"',                           'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 55],
            ['name' => 'partner.switch',                 'description' => 'Переключение партнёра (контекст)',              'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 56],
            ['name' => 'settings.view',                  'description' => 'Страница "Настройки"',                          'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 60],
            ['name' => 'settings.roles.view',            'description' => 'Страница "Настройки  -> Права и роли"',         'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 61],
            ['name' => 'settings.paymentSystems.view',   'description' => 'Страница "Настройки  -> Платежные системы"',    'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 62],
            ['name' => 'settings.queues.view',           'description' => 'Страница "Настройки -> Очереди"',               'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 63],
            ['name' => 'account.user.view',              'description' => 'Страница "Учетная запись -> Личные данные"',    'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 65],
            ['name' => 'account.partner.view',           'description' => 'Страница "Учетная запись -> Организация"',      'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 70],
            ['name' => 'account.documents.view',         'description' => 'Страница "Учетная запись -> "Мои документы"',   'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 70],
            ['name' => 'messages.view',                  'description' => 'Страница "Сообщения"',                          'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 73],
            ['name' => 'blog.view',                      'description' => 'Страница "Блог"',                               'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 74],
            ['name' => 'leads.view',                     'description' => 'Страница "Лиды"',                               'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 75],
            ['name' => 'servicePayments.view',           'description' => 'Страница "Оплата сервиса"',                     'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 80],
            ['name' => 'partnerWallet.view',             'description' => 'Страница "Кошелек"',                            'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 90],
            ['name' => 'documentations.view',            'description' => 'Страница "Документация"',                       'group_slug' => 'mainMenu', 'is_visible' => 0, 'sort_order' => 71],

            // account
            ['name' => 'account.user.name.update',      'description' => 'Изменение своего имени',                         'group_slug' => 'account',  'is_visible' => 1, 'sort_order' => 10],
            ['name' => 'account.user.birthdate.update',  'description' => 'Изменение своей даты рождения',                  'group_slug' => 'account',  'is_visible' => 1, 'sort_order' => 20],
            ['name' => 'account.user.team.update',       'description' => 'Изменение своей группы',                         'group_slug' => 'account',  'is_visible' => 0, 'sort_order' => 30],
            ['name' => 'account.user.startDate.update',  'description' => 'Изменение даты своего начала занятий',           'group_slug' => 'account',  'is_visible' => 0, 'sort_order' => 40],
            ['name' => 'account.user.email.update',      'description' => 'Изменение своего email',                         'group_slug' => 'account',  'is_visible' => 1, 'sort_order' => 50],
            ['name' => 'account.user.phone.update',      'description' => 'Изменение своего телефона',                      'group_slug' => 'account',  'is_visible' => 1, 'sort_order' => 60],
            ['name' => 'account.partner.update',         'description' => 'Изменение данных организации',                   'group_slug' => 'account',  'is_visible' => 0, 'sort_order' => 70],

            // users
            ['name' => 'users.name.update',              'description' => 'Изменение имени',                                'group_slug' => 'users',    'is_visible' => 1, 'sort_order' => 0],
            ['name' => 'users.birthdate.update',         'description' => 'Изменение даты рождения',                        'group_slug' => 'users',    'is_visible' => 1, 'sort_order' => 0],
            ['name' => 'users.group.update',             'description' => 'Изменение группы',                               'group_slug' => 'users',    'is_visible' => 1, 'sort_order' => 0],
            ['name' => 'users.startDate.update',         'description' => 'Изменение даты начала занятий',                  'group_slug' => 'users',    'is_visible' => 1, 'sort_order' => 0],
            ['name' => 'users.email.update',             'description' => 'Изменение email',                                'group_slug' => 'users',    'is_visible' => 1, 'sort_order' => 0],
            ['name' => 'users.activity.update',          'description' => 'Изменение активности (отключение)',              'group_slug' => 'users',    'is_visible' => 1, 'sort_order' => 0],
            ['name' => 'users.role.update',              'description' => 'Изменение роли (прав)',                          'group_slug' => 'users',    'is_visible' => 0, 'sort_order' => 0],
            ['name' => 'users.password.update',          'description' => 'Изменение пароля',                               'group_slug' => 'users',    'is_visible' => 1, 'sort_order' => 0],
            ['name' => 'users.phone.update',             'description' => 'Изменение телефона',                             'group_slug' => 'users',    'is_visible' => 1, 'sort_order' => 0],

            // misc
            ['name' => 'paying.classes',                 'description' => 'Оплата учебных занятий',                         'group_slug' => 'misc',     'is_visible' => 0, 'sort_order' => 100],
            ['name' => 'payment.clubfee',                'description' => 'Оплата клубного взноса',                         'group_slug' => 'misc',     'is_visible' => 0, 'sort_order' => 175],
            // ['name' => 'change_history',                 'description' => 'Просмотр истории изменений',                     'group_slug' => 'misc',     'is_visible' => 0, 'sort_order' => 180],
            // ['name' => 'manage_roles',                   'description' => 'Управление ролями',                              'group_slug' => 'misc',     'is_visible' => 0, 'sort_order' => 190],
            // ['name' => 'setting_payment_systems',        'description' => 'Настройка платежных систем',                     'group_slug' => 'misc',     'is_visible' => 0, 'sort_order' => 200],
            ['name' => 'payment.method.robokassa',       'description' => 'Способ оплаты «Робокасса»',                      'group_slug' => 'paymentMethods', 'is_visible' => 0, 'sort_order' => 10],
            ['name' => 'payment.method.tbankCard',       'description' => 'Способ оплаты «Т‑Банк» (карта, мультирасчёты)',  'group_slug' => 'paymentMethods', 'is_visible' => 0, 'sort_order' => 20],
            ['name' => 'payment.method.tbankSBP',        'description' => 'Способ оплаты «Т‑Банк» (СБП / QR)',               'group_slug' => 'paymentMethods', 'is_visible' => 0, 'sort_order' => 30],
            ['name' => 'manage.payment.method.tbank',   'description' => 'Управление интеграцией оплаты "Т-Банк" (админ)', 'group_slug' => 'paymentMethods', 'is_visible' => 0, 'sort_order' => 40],
            ['name' => 'tbank.payouts.manage',           'description' => 'Управление выплатами T‑Bank (бухгалтер)',        'group_slug' => 'paymentMethods', 'is_visible' => 0, 'sort_order' => 50],
            ['name' => 'viewing.all.logs',               'description' => 'Просмотр всех логов',                            'group_slug' => 'misc',     'is_visible' => 0, 'sort_order' => 210],
            ['name' => 'settings.commission',            'description' => 'Настройка комиссий ТБанк',                       'group_slug' => 'paymentMethods', 'is_visible' => 0, 'sort_order' => 60],
            ['name' => 'settings.force2fa.admins',       'description' => 'Управление обязательной 2FA для админов',         'group_slug' => 'misc',     'is_visible' => 0, 'sort_order' => 221],
            ['name' => 'settings.queues.manage',         'description' => 'Управление очередями (restart worker)',          'group_slug' => 'misc',     'is_visible' => 0, 'sort_order' => 222],
            ['name' => 'settings.registration.manage',   'description' => 'Настройки: регистрация на сайте (вкл/выкл)',       'group_slug' => 'misc',     'is_visible' => 0, 'sort_order' => 223],
        ];

        // Готовим данные для upsert: name как уникальный ключ, created_at задаём только "на создание"
        $rows = [];

        foreach ($permissions as $p) {
            $groupId = $groupIdBySlug[$p['group_slug']] ?? null;

            $rows[] = [
                'name'               => $p['name'],
                'description'        => $p['description'],
                'permission_group_id'=> $groupId,
                'is_visible'         => $p['is_visible'],
                'sort_order'         => $p['sort_order'],
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        DB::table('permissions')->upsert(
            $rows,
            ['name'], // уникальность по имени права
            [
                // поля, которые обновляем при повторном запуске
                'description',
                'permission_group_id',
                'is_visible',
                'sort_order',
                'updated_at',
                // created_at НЕ обновляем
            ]
        );
    }
}