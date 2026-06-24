<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Права доступа к страницам верхнего уровня — в группу «Главное меню».
     * sort_order синхронизирован с порядком бокового меню (sidebar.blade.php).
     * Маппинг синхронизирован с PermissionSeeder.
     */
    public function up(): void
    {
        $now = Carbon::now();

        $mainMenuId = DB::table('permission_groups')->where('slug', 'mainMenu')->value('id');
        if ($mainMenuId === null) {
            return;
        }

        /** @var array<string, int> $sortOrderByPermissionName */
        $sortOrderByPermissionName = [
            'dashboard.view'      => 10,
            'reports.view'        => 20,
            'myPayments.view'     => 30,
            'myGroup.view'        => 35,
            'setPrices.view'      => 40,
            'schedule.view'       => 50,
            'users.view'          => 60,
            'groups.view'         => 70,
            'contracts.view'      => 80,
            'settings.view'       => 90,
            'blog.view'           => 95,
            'documentations.view' => 100,
            'messages.view'       => 110,
            'partner.view'        => 120,
        ];

        foreach ($sortOrderByPermissionName as $permissionName => $sortOrder) {
            DB::table('permissions')
                ->where('name', $permissionName)
                ->update([
                    'permission_group_id' => $mainMenuId,
                    'sort_order'          => $sortOrder,
                    'updated_at'          => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Намеренно без отката: чисто UI-перегруппировка.
    }
};
