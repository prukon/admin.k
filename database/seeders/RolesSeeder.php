<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('roles')->upsert(
            [
                [
                    'id'         => 1,
                    'name'       => 'superadmin',
                    'label'      => 'Суперадмин',
                    'is_sistem'  => 1,
                    'is_visible' => 0,
                    'order_by'   => 1000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id'         => 2,
                    'name'       => 'admin',
                    'label'      => 'Администратор',
                    'is_sistem'  => 1,
                    'is_visible' => 1,
                    'order_by'   => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id'         => 3,
                    'name'       => 'user',
                    'label'      => 'Пользователь',
                    'is_sistem'  => 1,
                    'is_visible' => 1,
                    'order_by'   => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['id'], // жёсткий инвариант: id = 1,2,3
            ['name', 'label', 'is_sistem', 'is_visible', 'order_by', 'updated_at']
        );
    }
}