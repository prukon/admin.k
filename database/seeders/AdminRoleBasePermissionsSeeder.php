<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminRoleBasePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Находим роль admin
        $role = DB::table('roles')
            ->where('name', 'admin')
            ->first();

        if (!$role) {
            Log::warning('[Seeder] Role "admin" not found');
            return;
        }

        // 2) Берём ВСЕХ партнёров (а не id=1)
        $partnerIds = DB::table('partners')->pluck('id');

        if ($partnerIds->isEmpty()) {
            Log::warning('[Seeder] No partners found, skip seeding admin permissions');
            return;
        }

        // 3) Набор базовых прав администратора
        // (убрал дубль users.view, плюс тримим на всякий случай)
        $permissionNames = [
            'dashboard.view',
            'reports.view',
            'setPrices.view',
            'schedule.view',
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
        ];

        $permissionNames = array_values(array_unique(array_map('trim', $permissionNames)));

        // 4) Находим permissions по именам
        $permissions = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->get(['id', 'name']);

        if ($permissions->isEmpty()) {
            Log::warning('[Seeder] Admin permissions not found for provided names', [
                'names' => $permissionNames,
            ]);
            return;
        }

        // 5) Логируем отсутствующие permissions (ловит опечатки и "разъехавшиеся" нейминги)
        $foundNames = $permissions->pluck('name')->all();
        $missingNames = array_values(array_diff($permissionNames, $foundNames));

        if (!empty($missingNames)) {
            Log::warning('[Seeder] Some admin permissions were not found', [
                'missing' => $missingNames,
            ]);
        }

        // 6) Формируем пачку вставок для ВСЕХ партнёров
        $now = now();
        $rows = [];

        foreach ($partnerIds as $partnerId) {
            foreach ($permissions as $permission) {
                $rows[] = [
                    'partner_id'    => $partnerId,
                    'role_id'       => $role->id,
                    'permission_id' => $permission->id,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        // 7) Вставляем без дублей
        // Идеально, если в БД есть UNIQUE(partner_id, role_id, permission_id)
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }

        Log::info('[Seeder] Admin role base permissions seeded for all partners', [
            'partners_count' => $partnerIds->count(),
            'role_id'        => $role->id,
            'permissions'    => $permissions->pluck('name')->values()->all(),
        ]);
    }
}