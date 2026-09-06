<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * reports.payments.commission_total.view: скрыть в матрице (is_visible=0).
 * Существующие строки permission_role не трогает: выдачу уже сняли вручную,
 * новым партнёрам право не выдаётся через config/role_base_permissions.php.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'reports.payments.commission_total.view';

    public function up(): void
    {
        $now = Carbon::now();

        DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->update([
                'is_visible' => 0,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Право остаётся скрытым; выдачу ролям не восстанавливаем.
    }
};
