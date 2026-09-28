<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_activity_ip', 45)->nullable()->after('last_seen_at');
            $table->string('last_activity_user_agent', 512)->nullable()->after('last_activity_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_activity_ip', 'last_activity_user_agent']);
        });
    }
};
