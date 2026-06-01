<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('my_logs', function (Blueprint $table) {
            $table->string('event', 80)->nullable()->after('action');
            $table->string('level', 20)->nullable()->after('event');

            $table->index(['partner_id', 'created_at'], 'my_logs_partner_created_index');
            $table->index(['partner_id', 'event', 'created_at'], 'my_logs_partner_event_created_index');
            $table->index(['partner_id', 'level', 'created_at'], 'my_logs_partner_level_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('my_logs', function (Blueprint $table) {
            $table->dropIndex('my_logs_partner_created_index');
            $table->dropIndex('my_logs_partner_event_created_index');
            $table->dropIndex('my_logs_partner_level_created_index');

            $table->dropColumn(['event', 'level']);
        });
    }
};
