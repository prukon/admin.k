<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserRoleBasePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $partnerId = 1;

        // 1. Находим роль user
        $role = DB::table('roles')
            ->where('name', 'user')
            ->first();

        if (!$role) {
            \Log::warning('[Seeder] Role "user" not found');
            return;
        }

        // 2. Находим нужные permissions
        $permissions = DB::table('permissions')
            ->whereIn('name', [
                'dashboard.view',
                'myPayments.view',
                'myGroup.view',
                'account.user.view',
                'account.partner.view',
                'account.documents.view',
                'account.user.birthdate.update',
                'changing_user_email',
                'account.user.phone.update',
                'paying_classes ',
                'payment_clubfee',
            ])
            ->get();

        if ($permissions->isEmpty()) {
            \Log::warning('[Seeder] Permissions not found');
            return;
        }

        foreach ($permissions as $permission) {
            // 3. Проверяем, нет ли уже такой связки
            $exists = DB::table('permission_role')
                ->where('partner_id', $partnerId)
                ->where('role_id', $role->id)
                ->where('permission_id', $permission->id)
                ->exists();

            if ($exists) {
                continue;
            }

            // 4. Вставляем
            DB::table('permission_role')->insert([
                'partner_id'    => $partnerId,
                'role_id'       => $role->id,
                'permission_id' => $permission->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        \Log::info('[Seeder] User role base permissions seeded', [
            'partner_id' => $partnerId,
            'role_id'    => $role->id,
        ]);
    }
}