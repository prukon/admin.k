<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lesson_package_freezes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_lesson_package_id');

            // Конкретная дата занятия
            $table->date('date');

            // Какой слот заморожен (fixed: lesson_package_time_slots)
            $table->unsignedBigInteger('lesson_package_time_slot_id')->nullable();

            // Какой слот заморожен (flexible: user_lesson_package_time_slots)
            $table->unsignedBigInteger('user_lesson_package_time_slot_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->string('reason')->nullable();

            $table->timestamps();

            // Нельзя дважды заморозить одно и то же занятие.
            // (Для fixed уникальность по lesson_package_time_slot_id, для flexible — по user_lesson_package_time_slot_id)
            $table->unique(
                ['user_lesson_package_id', 'date', 'lesson_package_time_slot_id'],
                'ulp_freezes_unique_fixed'
            );
            $table->unique(
                ['user_lesson_package_id', 'date', 'user_lesson_package_time_slot_id'],
                'ulp_freezes_unique_flexible'
            );

            $table->index(['user_lesson_package_id', 'date']);

            // FK constraints with short names (MySQL identifier limit).
            $table->foreign('user_lesson_package_id', 'ulp_freezes_ulp_fk')
                ->references('id')
                ->on('user_lesson_packages')
                ->cascadeOnDelete();

            $table->foreign('lesson_package_time_slot_id', 'ulp_freezes_lpts_fk')
                ->references('id')
                ->on('lesson_package_time_slots')
                ->nullOnDelete();

            $table->foreign('user_lesson_package_time_slot_id', 'ulp_freezes_ulpts_fk')
                ->references('id')
                ->on('user_lesson_package_time_slots')
                ->nullOnDelete();

            $table->foreign('created_by', 'ulp_freezes_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lesson_package_freezes');
    }
};

