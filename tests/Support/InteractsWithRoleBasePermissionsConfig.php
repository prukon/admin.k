<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait InteractsWithRoleBasePermissionsConfig
{
    /** @var list<string> */
    protected const PARTNER_BASE_ROLE_NAMES = ['user', 'admin', 'trainer'];

    /**
     * @return list<string>
     */
    protected function basePermissionNamesForRole(string $roleName): array
    {
        $names = config("role_base_permissions.roles.{$roleName}", []);
        $names = is_array($names) ? $names : [];
        $names = array_values(array_unique(array_map('trim', $names)));

        return array_values(array_filter($names, static fn ($v) => $v !== ''));
    }

    /**
     * @return list<string>
     */
    protected function basePermissionNamesAll(): array
    {
        $names = [];
        foreach (self::PARTNER_BASE_ROLE_NAMES as $roleName) {
            $names = array_merge($names, $this->basePermissionNamesForRole($roleName));
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string> имена прав из config, которых нет в глобальной таблице permissions
     */
    protected function missingGlobalBasePermissionNames(): array
    {
        $names = $this->basePermissionNamesAll();
        if ($names === []) {
            return [];
        }

        $found = DB::table('permissions')
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $found = array_map('strval', $found);

        return array_values(array_diff($names, $found));
    }

    /**
     * @return list<string> системные роли из config, которых нет в таблице roles
     */
    protected function missingGlobalBaseRoleNames(): array
    {
        $found = DB::table('roles')
            ->whereIn('name', self::PARTNER_BASE_ROLE_NAMES)
            ->pluck('name')
            ->all();

        $found = array_map('strval', $found);

        return array_values(array_diff(self::PARTNER_BASE_ROLE_NAMES, $found));
    }

    protected function assertGlobalBaseRolesExist(string $messagePrefix = ''): void
    {
        $missing = $this->missingGlobalBaseRoleNames();
        $prefix = $messagePrefix !== '' ? $messagePrefix . ' ' : '';

        $this->assertSame(
            [],
            $missing,
            $prefix . 'Missing roles in `roles` table (run RolesSeeder on deploy): ' . implode(', ', $missing)
        );
    }

    protected function assertGlobalBasePermissionsExist(string $messagePrefix = ''): void
    {
        $missing = $this->missingGlobalBasePermissionNames();
        $prefix = $messagePrefix !== '' ? $messagePrefix . ' ' : '';

        $this->assertSame(
            [],
            $missing,
            $prefix . 'Missing permissions in `permissions` table (run PermissionSeeder on deploy): ' . implode(', ', $missing)
        );
    }
}
