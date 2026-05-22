<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('statuses') || Schema::hasColumn('statuses', 'sort_order')) {
            return;
        }

        Schema::table('statuses', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_system');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('statuses') || ! Schema::hasColumn('statuses', 'sort_order')) {
            return;
        }

        Schema::table('statuses', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
