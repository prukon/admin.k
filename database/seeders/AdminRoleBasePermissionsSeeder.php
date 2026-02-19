<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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

        // 3) Набор базовых прав администратора берём из конфига (единый источник правды)
        $permissionNames = config('role_base_permissions.roles.admin', []);
        $permissionNames = is_array($permissionNames) ? $permissionNames : [];
        $permissionNames = array_values(array_unique(array_map('trim', $permissionNames)));

        // 4) Находим permissions по именам
        $permissions = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->get(['id', 'name']);

        if ($permissions->isEmpty()) {
            throw new RuntimeException('[Seeder] Admin permissions not found for provided names: ' . implode(', ', $permissionNames));
        }

        // 5) Логируем отсутствующие permissions (ловит опечатки и "разъехавшиеся" нейминги)
        $foundNames = $permissions->pluck('name')->all();
        $missingNames = array_values(array_diff($permissionNames, $foundNames));

        if (!empty($missingNames)) {
            throw new RuntimeException('[Seeder] Some admin permissions were not found: ' . implode(', ', $missingNames));
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