<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Скрывает все права группы schoolSchedule в матрице (is_visible=0).
 * Существующие строки permission_role не трогает: новым партнёрам права
 * не выдаются через config/role_base_permissions.php.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const PERMISSION_NAMES = [
        'scheduleSlots.view',
        'scheduleSlots.manage',
        'scheduleSlots.table',
        'lessonPackages.export',
        'setPrices.packageAssignments.view',
        'lessonPackages.manualPaid.manage',
    ];

    public function up(): void
    {
        $now = Carbon::now();

        DB::table('permissions')
            ->whereIn('name', self::PERMISSION_NAMES)
            ->update([
                'is_visible' => 0,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $now = Carbon::now();

        DB::table('permissions')
            ->where('name', 'lessonPackages.export')
            ->update([
                'is_visible' => 1,
                'updated_at' => $now,
            ]);
    }
};
