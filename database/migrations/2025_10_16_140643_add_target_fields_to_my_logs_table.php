<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('my_logs', function (Blueprint $table) {
            $table->string('target_type', 120)->nullable()->after('action');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->string('target_label', 255)->nullable()->after('target_id');

            $table->index(['target_type', 'target_id'], 'my_logs_target_index');
            $table->index(['partner_id', 'type', 'action', 'created_at'], 'my_logs_filter_index');
        });
    }

    public function down(): void
    {
        Schema::table('my_logs', function (Blueprint $table) {
            $table->dropIndex('my_logs_target_index');
            $table->dropIndex('my_logs_filter_index');

            $table->dropColumn(['target_type', 'target_id', 'target_label']);
        });
    }
};
