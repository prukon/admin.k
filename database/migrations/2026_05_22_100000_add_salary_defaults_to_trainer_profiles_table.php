<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table) {
            $table->decimal('default_base_salary', 12, 2)->default(0)->after('sort_order');
            $table->decimal('default_rate_per_training', 12, 2)->default(0)->after('default_base_salary');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table) {
            $table->dropColumn(['default_base_salary', 'default_rate_per_training']);
        });
    }
};
