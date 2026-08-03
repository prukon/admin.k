<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Перенос payment.clubfee из группы misc в setPrices («Установка цен»).
 * Выдачи ролям (permission_role) не меняются.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'payment.clubfee';

    public function up(): void
    {
        $groupId = DB::table('permission_groups')->where('slug', 'setPrices')->value('id');
        if (! $groupId) {
            return;
        }

        DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->update([
                'permission_group_id' => $groupId,
                'sort_order'          => 23,
                'updated_at'          => Carbon::now(),
            ]);
    }

    public function down(): void
    {
        $groupId = DB::table('permission_groups')->where('slug', 'misc')->value('id');
        if (! $groupId) {
            return;
        }

        DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->update([
                'permission_group_id' => $groupId,
                'sort_order'          => 175,
                'updated_at'          => Carbon::now(),
            ]);
    }
};
