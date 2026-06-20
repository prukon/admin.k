<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PermissionGroupsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $groups = [
            [
                'slug'        => 'mainMenu',
                'name'        => 'Главное меню',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 10,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'reports',
                'name'        => 'Отчёты',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 11,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'schedule',
                'name'        => 'Расписание',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 12,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'directories',
                'name'        => 'Справочники',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 13,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'lessonPackages',
                'name'        => 'Абонементы',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 14,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'setPrices',
                'name'        => 'Установка цен',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 15,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'contracts',
                'name'        => 'Договоры',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 16,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'leads',
                'name'        => 'Заявки и лиды',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 17,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'partner',
                'name'        => 'Партнёры и сервис',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 18,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'account',
                'name'        => 'Учетная запись',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 20,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'users',
                'name'        => 'Управление пользователями',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 30,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'settings',
                'name'        => 'Настройки',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 32,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'paymentMethods',
                'name'        => 'Способы оплаты',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 35,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'misc',
                'name'        => 'Разное',
                'description' => null,
                'is_visible'  => 1,
                'sort_order'  => 999,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        DB::table('permission_groups')->upsert(
            $groups,
            ['slug'], // уникальность по slug
            [
                // что обновляем при повторных запусках
                'name',
                'description',
                'is_visible',
                'sort_order',
                'updated_at',
                // created_at сознательно НЕ трогаем
            ]
        );
    }
}
