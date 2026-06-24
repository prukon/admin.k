<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Право directories.view для пункта меню «Справочники».
     * scheduleSlots.view и schoolLeads.view — в группу «Главное меню» (пункты «Расписание школы», «Лиды»).
     * Маппинг синхронизирован с PermissionSeeder.
     */
    public function up(): void
    {
        $now = Carbon::now();

        $mainMenuId = DB::table('permission_groups')->where('slug', 'mainMenu')->value('id');
        if ($mainMenuId === null) {
            return;
        }

        DB::table('permissions')->upsert(
            [[
                'name'                => 'directories.view',
                'description'         => 'Страница "Справочники"',
                'permission_group_id' => $mainMenuId,
                'is_visible'          => 0,
                'sort_order'          => 65,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        /** @var array<string, int> $mainMenuSortOrder */
        $mainMenuSortOrder = [
            'dashboard.view'      => 10,
            'reports.view'        => 20,
            'myPayments.view'     => 30,
            'myGroup.view'        => 35,
            'setPrices.view'      => 40,
            'schedule.view'       => 50,
            'scheduleSlots.view'  => 52,
            'schoolLeads.view'    => 54,
            'users.view'          => 60,
            'directories.view'    => 65,
            'groups.view'         => 70,
            'contracts.view'      => 80,
            'settings.view'       => 90,
            'blog.view'           => 95,
            'documentations.view' => 100,
            'messages.view'       => 110,
            'partner.view'        => 120,
        ];

        foreach ($mainMenuSortOrder as $permissionName => $sortOrder) {
            DB::table('permissions')
                ->where('name', $permissionName)
                ->update([
                    'permission_group_id' => $mainMenuId,
                    'sort_order'          => $sortOrder,
                    'updated_at'          => $now,
                ]);
        }

        $directoriesPermId = DB::table('permissions')->where('name', 'directories.view')->value('id');
        if ($directoriesPermId === null) {
            return;
        }

        $sourcePermIds = DB::table('permissions')
            ->whereIn('name', ['groups.view', 'locations.view', 'districts.view', 'sport_types.view'])
            ->pluck('id')
            ->all();

        if ($sourcePermIds === []) {
            return;
        }

        $pairs = DB::table('permission_role')
            ->whereIn('permission_id', $sourcePermIds)
            ->select(['partner_id', 'role_id'])
            ->distinct()
            ->get();

        $rows = [];
        foreach ($pairs as $pair) {
            $rows[] = [
                'partner_id'    => $pair->partner_id,
                'role_id'       => $pair->role_id,
                'permission_id' => $directoriesPermId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'directories.view')->value('id');
        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
