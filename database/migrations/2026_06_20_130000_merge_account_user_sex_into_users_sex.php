<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        $now = Carbon::now();
        $usersSexId = DB::table('permissions')->where('name', 'users.sex')->value('id');
        $accountSexId = DB::table('permissions')->where('name', 'account.user.sex.update')->value('id');

        if (!$usersSexId || !$accountSexId) {
            return;
        }

        $legacyAssignments = DB::table('permission_role')
            ->where('permission_id', $accountSexId)
            ->get(['partner_id', 'role_id']);

        $rows = [];

        foreach ($legacyAssignments as $assignment) {
            $rows[] = [
                'partner_id'    => (int) $assignment->partner_id,
                'role_id'       => (int) $assignment->role_id,
                'permission_id' => (int) $usersSexId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }

        DB::table('permission_role')->where('permission_id', $accountSexId)->delete();
        DB::table('permissions')->where('id', $accountSexId)->delete();

        DB::table('permissions')
            ->where('id', $usersSexId)
            ->update([
                'description' => 'Пол ученика (просмотр и редактирование в CRM и личном кабинете)',
                'updated_at'  => $now,
            ]);
    }

    public function down(): void
    {
        // Обратная миграция не восстанавливает account.user.sex.update — право удалено намеренно.
    }
};
