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

        // Из permission_groups.sql: id 1..4 с уникальным slug. :contentReference[oaicite:2]{index=2}
        $groups = [
            ['slug' => 'mainMenu', 'name' => 'Главное меню',           'description' => null, 'is_visible' => 1, 'sort_order' => 10],
            ['slug' => 'account',  'name' => 'Учетная запись',         'description' => null, 'is_visible' => 1, 'sort_order' => 20],
            ['slug' => 'users',    'name' => 'Управление пользователями','description' => null,'is_visible' => 1, 'sort_order' => 30],
            ['slug' => 'misc',     'name' => 'Разное',                  'description' => null, 'is_visible' => 1, 'sort_order' => 999],
        ];

        foreach ($groups as $g) {
            DB::table('permission_groups')->updateOrInsert(
                ['slug' => $g['slug']],
                [
                    'name'        => $g['name'],
                    'description' => $g['description'],
                    'is_visible'  => $g['is_visible'],
                    'sort_order'  => $g['sort_order'],
                    'updated_at'  => $now,
                    // created_at не трогаем, если запись уже существует
                    'created_at'  => DB::raw("COALESCE(created_at, '{$now->toDateTimeString()}')")
                ]
            );
        }
    }
}
