<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lesson_packages', 'lessons_per_month')) {
            return;
        }

        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->dropColumn('lessons_per_month');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('lesson_packages', 'lessons_per_month')) {
            return;
        }

        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->unsignedSmallInteger('lessons_per_month')->nullable()->after('lessons_per_week');
        });
    }
};
