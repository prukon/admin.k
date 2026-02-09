<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminRoleBasePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $partnerId = 1;

        // 1. Находим роль admin
        $role = DB::table('roles')
            ->where('name', 'admin')
            ->first();

        if (!$role) {
            \Log::warning('[Seeder] Role "admin" not found');
            return;
        }

        // 2. Набор базовых прав администратора
        $permissions = DB::table('permissions')
            ->whereIn('name', [
                'dashboard.view',
                'reports.view',
                'setPrices.view',
                'schedule.view',
                'users.view',
                'users.view',
                'groups.view',
                'contracts.view',
                'settings.view',
                'settings.roles.view',
                'settings.paymentSystems.view',
                'account.user.view',
                'account.partner.view',
                'account.documents.view',
                'servicePayments.view',
                'partnerWallet.view',
                'name_editing',
                'account.user.birthdate.update',
                'changing_your_group',
                'account.user.startDate.update',
                'changing_user_email',
                'account.user.phone.update',
                'users.name.update',
                'users.birthdate.update',
                'users.group.update',
                'users.startDate.update',
                'users.email.update',
                'users.activity.update',
                'users.role.update',
                'users.password.update',
                'users.phone.update',
                'payment_clubfee',
                'change_history',
                'manage_roles',
            ])
            ->get();

        if ($permissions->isEmpty()) {
            \Log::warning('[Seeder] Admin permissions not found');
            return;
        }

        foreach ($permissions as $permission) {
            // 3. Проверяем, есть ли уже связка
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

        \Log::info('[Seeder] Admin role base permissions seeded', [
            'partner_id' => $partnerId,
            'role_id'    => $role->id,
        ]);
    }
}