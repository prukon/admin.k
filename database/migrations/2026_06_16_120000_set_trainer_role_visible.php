<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'trainer')
            ->update([
                'is_visible' => 1,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'trainer')
            ->update([
                'is_visible' => 0,
                'updated_at' => now(),
            ]);
    }
};
