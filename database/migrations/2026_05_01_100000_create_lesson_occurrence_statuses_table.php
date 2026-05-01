<?php

use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_occurrence_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('title');
            $table->string('color', 7)->default('#6c757d');
            $table->string('icon', 191)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['partner_id', 'code'], 'los_partner_code_unique');
            $table->index(['partner_id', 'sort_order', 'id'], 'los_partner_sort_idx');
        });

        $partnerIds = DB::table('partners')->pluck('id');
        foreach ($partnerIds as $pid) {
            LessonOccurrenceStatusesSeeder::ensureForPartner((int) $pid);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_occurrence_statuses');
    }
};
