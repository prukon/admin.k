<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_package_time_slots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lesson_package_id')
                ->constrained('lesson_packages')
                ->cascadeOnDelete();

            // 1..7 (ПН..ВС)
            $table->unsignedTinyInteger('weekday');

            $table->time('time_start');
            $table->time('time_end');

            $table->timestamps();

            $table->unique(
                ['lesson_package_id', 'weekday', 'time_start', 'time_end'],
                'lesson_package_time_slots_unique_slot'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_package_time_slots');
    }
};

