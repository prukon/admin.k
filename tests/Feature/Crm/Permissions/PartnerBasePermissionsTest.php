<?php

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Crm\CrmTestCase;

class PartnerBasePermissionsTest extends CrmTestCase
{
    /** @var list<string> */
    private const BASE_ROLE_NAMES = ['user', 'admin', 'trainer'];

    public function test_creating_partner_assigns_base_permissions_for_user_admin_and_trainer_roles(): void
    {
        $partner = Partner::factory()->create();

        foreach (self::BASE_ROLE_NAMES as $roleName) {
            $this->assertPartnerHasExactlyConfiguredBasePermissions($partner->id, $roleName);
        }
    }

    public function test_base_permissions_are_isolated_between_partners(): void
    {
        $p1 = Partner::factory()->create();
        $p2 = Partner::factory()->create();

        $p1User = $this->permissionNamesForPartnerRole($p1->id, 'user');
        $p2User = $this->permissionNamesForPartnerRole($p2->id, 'user');

        $this->assertEqualsCanonicalizing($p1User, $p2User);

        $p1Admin = $this->permissionNamesForPartnerRole($p1->id, 'admin');
        $p2Admin = $this->permissionNamesForPartnerRole($p2->id, 'admin');

        $this->assertEqualsCanonicalizing($p1Admin, $p2Admin);

        $p1Trainer = $this->permissionNamesForPartnerRole($p1->id, 'trainer');
        $p2Trainer = $this->permissionNamesForPartnerRole($p2->id, 'trainer');

        $this->assertEqualsCanonicalizing($p1Trainer, $p2Trainer);
    }

    public function test_missing_permission_throws_and_partner_is_not_created(): void
    {
        $all = $this->basePermissionNamesAll();
        $this->assertNotEmpty($all, 'Base permission list in config is empty');

        $victim = $all[0];

        $this->assertDatabaseHas('permissions', ['name' => $victim]);

        DB::table('permissions')->where('name', $victim)->delete();

        $before = DB::table('partners')->count();

        try {
            Partner::factory()->create();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Missing required permissions', $e->getMessage());
            $this->assertStringContainsString($victim, $e->getMessage());
        }

        $after = DB::table('partners')->count();
        $this->assertSame($before, $after, 'Partner row should not be created when base permissions are missing');
    }

    public function test_permission_role_triplet_is_unique_and_insert_is_idempotent(): void
    {
        $partner = Partner::factory()->create();

        $rowsByRole = [];
        foreach (self::BASE_ROLE_NAMES as $roleName) {
            $rowsByRole[$roleName] = $this->buildPermissionRoleRowsForPartner($partner->id, $roleName);
        }

        $allRows = array_merge(...array_values($rowsByRole));
        $expected = count($allRows);
        $this->assertGreaterThan(0, $expected);

        $countBefore = DB::table('permission_role')->where('partner_id', $partner->id)->count();
        $this->assertSame($expected, $countBefore);

        DB::table('permission_role')->insertOrIgnore($allRows);

        $countAfter = DB::table('permission_role')->where('partner_id', $partner->id)->count();
        $this->assertSame($countBefore, $countAfter, 'Duplicate insertOrIgnore should not add rows');

        // Дополнительно: проверяем наличие UNIQUE индекса на тройку (для MySQL/MariaDB)
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $dbName = DB::connection()->getDatabaseName();

            $rows = DB::select(
                "SELECT NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
                 FROM information_schema.statistics
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = 'permission_role'
                 GROUP BY INDEX_NAME, NON_UNIQUE",
                [$dbName]
            );

            $has = false;
            foreach ($rows as $row) {
                $nonUnique = (int) ($row->NON_UNIQUE ?? 1);
                $cols = (string) ($row->cols ?? '');
                if ($nonUnique === 0 && $cols === 'partner_id,role_id,permission_id') {
                    $has = true;
                    break;
                }
            }

            $this->assertTrue($has, 'permission_role must have UNIQUE(partner_id, role_id, permission_id)');
        }
    }

    private function assertPartnerHasExactlyConfiguredBasePermissions(int $partnerId, string $roleName): void
    {
        $expected = $this->basePermissionNamesForRole($roleName);
        $this->assertNotEmpty($expected, "Config base permissions for role '{$roleName}' is empty");

        $actual = $this->permissionNamesForPartnerRole($partnerId, $roleName);

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    private function basePermissionNamesForRole(string $roleName): array
    {
        $names = config("role_base_permissions.roles.{$roleName}", []);
        $names = is_array($names) ? $names : [];
        $names = array_values(array_unique(array_map('trim', $names)));

        return array_values(array_filter($names, static fn ($v) => $v !== ''));
    }

    private function basePermissionNamesAll(): array
    {
        $names = [];
        foreach (self::BASE_ROLE_NAMES as $roleName) {
            $names = array_merge($names, $this->basePermissionNamesForRole($roleName));
        }

        return array_values(array_unique($names));
    }

    private function permissionNamesForPartnerRole(int $partnerId, string $roleName): array
    {
        $roleId = (int) DB::table('roles')->where('name', $roleName)->value('id');
        $this->assertGreaterThan(0, $roleId, "Role '{$roleName}' not found");

        $names = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permission_role.partner_id', $partnerId)
            ->where('permission_role.role_id', $roleId)
            ->pluck('permissions.name')
            ->all();

        return array_map('strval', $names);
    }

    private function buildPermissionRoleRowsForPartner(int $partnerId, string $roleName): array
    {
        $roleId = (int) DB::table('roles')->where('name', $roleName)->value('id');
        $this->assertGreaterThan(0, $roleId, "Role '{$roleName}' not found");

        $permissionNames = $this->basePermissionNamesForRole($roleName);
        $this->assertNotEmpty($permissionNames);

        $permissionIdByName = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->pluck('id', 'name')
            ->all();

        $missing = array_values(array_diff($permissionNames, array_keys($permissionIdByName)));
        $this->assertEmpty($missing, 'Missing permissions in DB: ' . implode(', ', $missing));

        $now = now();
        $rows = [];
        foreach ($permissionNames as $name) {
            $rows[] = [
                'partner_id' => $partnerId,
                'role_id' => $roleId,
                'permission_id' => (int) $permissionIdByName[$name],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}

