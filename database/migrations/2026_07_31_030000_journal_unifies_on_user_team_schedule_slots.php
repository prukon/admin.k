<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Журнал /schedule переводится на user_team_schedule_slots.
 * schedule_users больше не используется (на проде не было боевых данных).
 * trainer_profile_id на статусах — для «Посетил» в журнале и нагрузки тренеров.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('schedule_users');

        Schema::table('user_lesson_occurrence_status_events', function (Blueprint $table) {
            $table->unsignedBigInteger('trainer_profile_id')->nullable()->after('lesson_occurrence_status_id');
            $table->string('comment', 2000)->nullable()->after('trainer_profile_id');

            $table->foreign('trainer_profile_id', 'ulosse_trainer_profile_fk')
                ->references('id')
                ->on('trainer_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_lesson_occurrence_status_events', function (Blueprint $table) {
            $table->dropForeign('ulosse_trainer_profile_fk');
            $table->dropColumn(['trainer_profile_id', 'comment']);
        });

        Schema::create('schedule_users', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->date('date');
            $table->boolean('is_enabled')->nullable();
            $table->boolean('is_paid')->nullable();
            $table->boolean('is_hospital')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('lesson_occurrence_status_id')->nullable();
            $table->unsignedBigInteger('trainer_profile_id')->nullable();
            $table->timestamps();

            $table->foreign('lesson_occurrence_status_id')
                ->references('id')
                ->on('lesson_occurrence_statuses')
                ->nullOnDelete();
            $table->foreign('trainer_profile_id')
                ->references('id')
                ->on('trainer_profiles')
                ->nullOnDelete();
            $table->index(['trainer_profile_id', 'date']);
        });
    }
};
