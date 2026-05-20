<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DevTrainerRoleBasePermissionsSeeder extends Seeder
{
    use GuardsDevSeedData;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $role = DB::table('roles')
            ->where('name', 'trainer')
            ->first();

        if (! $role) {
            Log::warning('[Seeder] Role "trainer" not found');

            return;
        }

        $partnerIds = DB::table('partners')->pluck('id');

        if ($partnerIds->isEmpty()) {
            Log::warning('[Seeder] No partners found, skip seeding trainer permissions');

            return;
        }

        $permissionNames = config('role_base_permissions.roles.trainer', []);
        $permissionNames = is_array($permissionNames) ? $permissionNames : [];
        $permissionNames = array_values(array_unique(array_map('trim', $permissionNames)));

        if ($permissionNames === []) {
            return;
        }

        $permissions = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->get(['id', 'name']);

        $foundNames = $permissions->pluck('name')->all();
        $missingNames = array_values(array_diff($permissionNames, $foundNames));

        if (! empty($missingNames)) {
            throw new RuntimeException('[Seeder] Some trainer permissions were not found: ' . implode(', ', $missingNames));
        }

        $now = now();
        $rows = [];

        foreach ($partnerIds as $partnerId) {
            foreach ($permissions as $permission) {
                $rows[] = [
                    'partner_id' => $partnerId,
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }

        Log::info('[Seeder] Trainer role base permissions seeded for all partners', [
            'partners_count' => $partnerIds->count(),
            'role_id' => $role->id,
            'permissions' => $permissions->pluck('name')->values()->all(),
        ]);
    }
}
