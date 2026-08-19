<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * UI-нейминг учеников: роль «Клиент», группа прав «Управление клиентами».
 * Пункт меню / заголовок раздела остаются «Пользователи».
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        DB::table('roles')
            ->where('name', 'user')
            ->where('label', 'Пользователь')
            ->update([
                'label'      => 'Клиент',
                'updated_at' => $now,
            ]);

        DB::table('permission_groups')
            ->where('slug', 'users')
            ->where('name', 'Управление пользователями')
            ->update([
                'name'       => 'Управление клиентами',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $now = Carbon::now();

        DB::table('roles')
            ->where('name', 'user')
            ->where('label', 'Клиент')
            ->update([
                'label'      => 'Пользователь',
                'updated_at' => $now,
            ]);

        DB::table('permission_groups')
            ->where('slug', 'users')
            ->where('name', 'Управление клиентами')
            ->update([
                'name'       => 'Управление пользователями',
                'updated_at' => $now,
            ]);
    }
};
