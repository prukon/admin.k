<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $groupId = DB::table('permission_groups')
            ->where('slug', 'mainMenu')
            ->value('id');

        DB::table('permissions')->upsert([
            [
                'name' => 'reports.payments.payout_amount.column.view',
                'description' => 'Отчёт «Платежи»: колонка «Выплата»',
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => 22,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['name'], [
            'description',
            'permission_group_id',
            'is_visible',
            'sort_order',
            'updated_at',
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'reports.payments.payout_amount.column.view')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (! $adminRoleId) {
            return;
        }

        $partnerIds = DB::table('partners')->pluck('id');
        if ($partnerIds->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($partnerIds as $partnerId) {
            $rows[] = [
                'partner_id' => $partnerId,
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'reports.payments.payout_amount.column.view')
            ->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
