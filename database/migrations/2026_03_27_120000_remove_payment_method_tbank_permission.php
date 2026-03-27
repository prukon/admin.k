<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('permissions')->where('name', 'payment.method.tbank')->value('id');
        if ($id === null) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }

    public function down(): void
    {
        // Восстановление намеренно не делаем: право заменено на payment.method.tbankCard / tbankSBP / robokassa.
    }
};
