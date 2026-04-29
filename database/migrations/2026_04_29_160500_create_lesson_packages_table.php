<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_packages', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // fixed: есть шаблон слотов (дни недели + время)
            // flexible: шаблонов нет, график будет задаваться на уровне назначения ученику
            // no_schedule: пакет без расписания (разовые/пакеты занятий)
            $table->string('schedule_type', 20);

            $table->unsignedSmallInteger('duration_days');
            $table->unsignedSmallInteger('lessons_count');

            // стоимость в копейках (целое число)
            $table->unsignedInteger('price_cents');

            $table->boolean('freeze_enabled')->default(false);
            $table->unsignedSmallInteger('freeze_days')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['schedule_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_packages');
    }
};

