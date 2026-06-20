<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<array{name:string,description:string}>
     */
    private array $permissions = [
        [
            'name' => 'users.sex',
            'description' => 'Пол ученика (просмотр и редактирование в CRM)',
        ],
        [
            'name' => 'users.comment',
            'description' => 'Комментарий к ученику (просмотр и редактирование в CRM)',
        ],
        [
            'name' => 'account.user.sex.update',
            'description' => 'Изменение своего пола в личном кабинете',
        ],
    ];

    public function up(): void
    {
        $now = Carbon::now();
        $groupUsersId = DB::table('permission_groups')->where('slug', 'users')->value('id');
        $groupAccountId = DB::table('permission_groups')->where('slug', 'account')->value('id');

        foreach ($this->permissions as $permission) {
            $groupId = str_starts_with($permission['name'], 'account.')
                ? $groupAccountId
                : $groupUsersId;

            DB::table('permissions')->upsert(
                [[
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'permission_group_id' => $groupId,
                    'is_visible' => 1,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['name'],
                ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
            );
        }

        if (!Schema::hasTable('partners') || !Schema::hasTable('permission_role')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (!$adminRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['users.sex', 'users.comment'])
            ->pluck('id', 'name');

        $partnerIds = DB::table('partners')->pluck('id');
        $rows = [];

        foreach ($partnerIds as $partnerId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = [
                    'partner_id' => (int) $partnerId,
                    'role_id' => (int) $adminRoleId,
                    'permission_id' => (int) $permissionId,
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
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_column($this->permissions, 'name'))
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
