<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Чат: messages.view видно в матрице и выдано базовым ролям user/admin/trainer.
 */
return new class extends Migration
{
    private const PERMISSION = 'messages.view';

    /** @var list<string> */
    private const BACKFILL_ROLE_NAMES = ['user', 'admin', 'trainer'];

    public function up(): void
    {
        $now = Carbon::now();

        DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->update([
                'is_visible' => 1,
                'updated_at' => $now,
            ]);

        if (! Schema::hasTable('partners') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = (int) DB::table('permissions')->where('name', self::PERMISSION)->value('id');
        if ($permissionId <= 0) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', self::BACKFILL_ROLE_NAMES)
            ->pluck('id', 'name');

        $partnerIds = DB::table('partners')->pluck('id');
        $rows = [];

        foreach ($partnerIds as $partnerId) {
            foreach (self::BACKFILL_ROLE_NAMES as $roleName) {
                $roleId = $roleIds[$roleName] ?? null;
                if (! $roleId) {
                    continue;
                }

                $rows[] = [
                    'partner_id' => (int) $partnerId,
                    'role_id' => (int) $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->update([
                'is_visible' => 0,
                'updated_at' => Carbon::now(),
            ]);
    }
};
