<?php

namespace Tests\Feature\Crm\Contracts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

abstract class ContractsFeatureTestCase extends CrmTestCase
{
    protected const PERM_CONTRACTS_VIEW = 'contracts.view';

    protected function setUp(): void
    {
        parent::setUp();

        // В некоторых окружениях storage/ может быть read-only.
        // Storage::fake() использует storage_path('framework/testing/...') — поэтому переносим storage_path в /tmp.
        $storage = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_storage_'
            . (string) Str::uuid();

        if (!is_dir($storage)) {
            @mkdir($storage, 0777, true);
        }
        @chmod($storage, 0777);
        $this->app->useStoragePath($storage);

        // Страхуемся от 2FA-редиректов в окружениях, где 2FA может быть включена.
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        // Для большинства тестов нужен доступ к разделу "Договоры".
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);
    }

    protected function grantPermissionToRoleForPartner(int $roleId, int $partnerId, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partnerId,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}

