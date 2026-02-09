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