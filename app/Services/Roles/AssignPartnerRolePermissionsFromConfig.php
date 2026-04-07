<?php

namespace App\Services\Roles;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AssignPartnerRolePermissionsFromConfig
{
    /**
     * Назначает права роли в контексте партнёра по списку имён из config('role_base_permissions.roles.{key}').
     *
     * Имена, отсутствующие в таблице permissions, пропускаются; в лог пишется предупреждение.
     *
     * @return array{permission_ids: int[], missing_permission_names: string[]}
     */
    public function assignFromConfigRoleKey(int $roleId, int $partnerId, string $configRoleKey = 'admin'): array
    {
        $permissionNames = config("role_base_permissions.roles.{$configRoleKey}", []);
        $permissionNames = is_array($permissionNames) ? $permissionNames : [];
        $permissionNames = array_values(array_unique(array_filter(array_map('trim', $permissionNames))));

        if ($permissionNames === []) {
            return ['permission_ids' => [], 'missing_permission_names' => []];
        }

        $permissions = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->orderBy('id')
            ->get(['id', 'name']);

        $foundNames = $permissions->pluck('name')->all();
        $missingNames = array_values(array_diff($permissionNames, $foundNames));

        if ($missingNames !== []) {
            Log::warning('AssignPartnerRolePermissionsFromConfig: имена прав из конфига не найдены в БД', [
                'config_role_key'            => $configRoleKey,
                'role_id'                    => $roleId,
                'partner_id'                 => $partnerId,
                'missing_permission_names'   => $missingNames,
            ]);
        }

        if ($permissions->isEmpty()) {
            return ['permission_ids' => [], 'missing_permission_names' => $missingNames];
        }

        $now = now();
        $rows = [];

        foreach ($permissions as $permission) {
            $rows[] = [
                'partner_id'    => $partnerId,
                'role_id'       => $roleId,
                'permission_id' => (int) $permission->id,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }

        $permissionIds = $permissions->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return [
            'permission_ids'           => $permissionIds,
            'missing_permission_names' => $missingNames,
        ];
    }
}
