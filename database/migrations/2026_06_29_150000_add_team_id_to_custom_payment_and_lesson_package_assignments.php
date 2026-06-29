<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_custom_payment', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('user_id');
            $table->index(['partner_id', 'team_id'], 'idx_user_custom_payment_partner_team');
        });

        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('user_id');
            $table->index('team_id', 'idx_user_lesson_packages_team_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_custom_payment', function (Blueprint $table) {
            $table->dropIndex('idx_user_custom_payment_partner_team');
            $table->dropColumn('team_id');
        });

        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->dropIndex('idx_user_lesson_packages_team_id');
            $table->dropColumn('team_id');
        });
    }
};
