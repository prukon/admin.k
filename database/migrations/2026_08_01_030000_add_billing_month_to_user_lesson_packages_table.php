<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->date('billing_month')->nullable()->after('team_id');
            $table->index(
                ['user_id', 'team_id', 'billing_month'],
                'user_lesson_packages_user_team_billing_month_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->dropIndex('user_lesson_packages_user_team_billing_month_idx');
            $table->dropColumn('billing_month');
        });
    }
};
