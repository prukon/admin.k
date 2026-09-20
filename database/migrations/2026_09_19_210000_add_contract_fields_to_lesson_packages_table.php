<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->unsignedSmallInteger('lessons_per_week')->nullable()->after('price_cents');
            $table->unsignedSmallInteger('lessons_per_month')->nullable()->after('lessons_per_week');
            $table->unsignedSmallInteger('lesson_duration_minutes')->nullable()->after('lessons_per_month');
            $table->unsignedInteger('lesson_price_cents')->nullable()->after('lesson_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->dropColumn([
                'lessons_per_week',
                'lessons_per_month',
                'lesson_duration_minutes',
                'lesson_price_cents',
            ]);
        });
    }
};
