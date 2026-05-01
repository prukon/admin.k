<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_occurrence_statuses', function (Blueprint $table) {
            $table->boolean('consumes_lesson')->default(false)->after('is_active');
        });

        DB::table('lesson_occurrence_statuses')
            ->whereIn('code', ['attended', 'not_attended'])
            ->update(['consumes_lesson' => true]);
    }

    public function down(): void
    {
        Schema::table('lesson_occurrence_statuses', function (Blueprint $table) {
            $table->dropColumn('consumes_lesson');
        });
    }
};
