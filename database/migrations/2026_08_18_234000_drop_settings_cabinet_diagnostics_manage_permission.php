<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Убирает неиспользованное право settings.cabinetDiagnostics.manage.
 * Диагностика консоли завязана на роль superadmin, а не на матрицу прав
 * (роли superadmin в сетке «Права и роли» нет).
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'settings.cabinetDiagnostics.manage';

    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION_NAME)->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }

    public function down(): void
    {
        // Право сознательно не восстанавливаем: доступ только у superadmin.
    }
};
