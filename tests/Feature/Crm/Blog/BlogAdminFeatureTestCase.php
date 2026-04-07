<?php

namespace Tests\Feature\Crm\Blog;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Админские feature-тесты блога: роль admin + явное право blog.view (как в Gate / can:blog.view).
 * Базовый набор прав админа из role_base_permissions может не включать блог — тесты не должны от этого зависеть.
 */
abstract class BlogAdminFeatureTestCase extends CrmTestCase
{
    protected const PERM_BLOG_VIEW = 'blog.view';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();

        $this->grantPermissionToRoleForPartner(
            (int) $this->user->role_id,
            (int) $this->partner->id,
            self::PERM_BLOG_VIEW
        );
    }

    protected function grantPermissionToRoleForPartner(int $roleId, int $partnerId, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $partnerId,
            'role_id' => $roleId,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
