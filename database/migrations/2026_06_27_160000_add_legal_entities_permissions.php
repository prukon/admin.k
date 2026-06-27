<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'directories')->value('id')
            ?? DB::table('permission_groups')->where('slug', 'mainMenu')->value('id');

        DB::table('permissions')->upsert(
            [
                [
                    'name' => 'legal_entities.view',
                    'description' => 'Справочники: юр. лица (просмотр)',
                    'permission_group_id' => $groupId,
                    'is_visible' => 0,
                    'sort_order' => 41,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'legal_entities.manage',
                    'description' => 'Справочники: юр. лица (создание/редактирование)',
                    'permission_group_id' => $groupId,
                    'is_visible' => 0,
                    'sort_order' => 42,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        $directoriesViewId = DB::table('permissions')->where('name', 'directories.view')->value('id');
        $legalEntitiesViewId = DB::table('permissions')->where('name', 'legal_entities.view')->value('id');

        if ($directoriesViewId && $legalEntitiesViewId && Schema::hasTable('permission_role')) {
            $rows = DB::table('permission_role')
                ->where('permission_id', $legalEntitiesViewId)
                ->select('partner_id', 'role_id')
                ->get();

            $grantRows = [];
            foreach ($rows as $row) {
                $grantRows[] = [
                    'partner_id' => (int) $row->partner_id,
                    'role_id' => (int) $row->role_id,
                    'permission_id' => (int) $directoriesViewId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($grantRows, 1000) as $chunk) {
                DB::table('permission_role')->insertOrIgnore($chunk);
            }
        }
    }

    public function down(): void
    {
        $names = ['legal_entities.view', 'legal_entities.manage'];

        $permissionIds = DB::table('permissions')->whereIn('name', $names)->pluck('id');

        if ($permissionIds->isNotEmpty() && Schema::hasTable('permission_role')) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('name', $names)->delete();
    }
};
