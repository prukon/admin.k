<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('districts', 'deleted_at')) {
            return;
        }

        // Районы удалялись только soft delete без привязанных объектов — можно убрать навсегда.
        DB::table('districts')->whereNotNull('deleted_at')->delete();

        Schema::table('districts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('districts', 'deleted_at')) {
            return;
        }

        Schema::table('districts', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
