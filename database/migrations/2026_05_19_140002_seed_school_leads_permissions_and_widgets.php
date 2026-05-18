<?php

use App\Services\PartnerWidgetService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $groupId = DB::table('permission_groups')
            ->where('slug', 'mainMenu')
            ->value('id');

        $definitions = [
            [
                'name'        => 'schoolLeads.view',
                'description' => 'Страница "Заявки с сайта"',
                'sort_order'  => 76,
            ],
            [
                'name'        => 'schoolWidget.view',
                'description' => 'Страница "Виджет заявок"',
                'sort_order'  => 77,
            ],
        ];

        foreach ($definitions as $definition) {
            DB::table('permissions')->upsert([
                [
                    'name'                => $definition['name'],
                    'description'         => $definition['description'],
                    'permission_group_id' => $groupId,
                    'is_visible'          => 0,
                    'sort_order'          => $definition['sort_order'],
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ],
            ], ['name'], [
                'description',
                'permission_group_id',
                'is_visible',
                'sort_order',
                'updated_at',
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['schoolLeads.view', 'schoolWidget.view'])
            ->pluck('id')
            ->all();

        if (empty($permissionIds) || !Schema::hasTable('partners') || !Schema::hasTable('permission_role')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (!$adminRoleId) {
            return;
        }

        $partnerIds = DB::table('partners')->pluck('id');
        $rows = [];

        foreach ($partnerIds as $partnerId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = [
                    'partner_id'    => (int) $partnerId,
                    'role_id'       => (int) $adminRoleId,
                    'permission_id' => (int) $permissionId,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }

        if (Schema::hasTable('partner_widgets')) {
            app(PartnerWidgetService::class)->ensureForAllPartners();
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['schoolLeads.view', 'schoolWidget.view'])
            ->pluck('id')
            ->all();

        if (!empty($permissionIds)) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
