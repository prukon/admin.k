<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->boolean('auto_attendance_enabled')->default(false)->after('freeze_days');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->dropColumn('auto_attendance_enabled');
        });
    }
};
