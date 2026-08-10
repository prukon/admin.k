<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lesson_occurrence_status_event_trainers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_lesson_occurrence_status_event_id');
            $table->unsignedBigInteger('trainer_profile_id');

            $table->unique(
                ['user_lesson_occurrence_status_event_id', 'trainer_profile_id'],
                'ulosset_event_trainer_uq'
            );
            $table->index('trainer_profile_id', 'ulosset_trainer_idx');

            $table->foreign('user_lesson_occurrence_status_event_id', 'ulosset_event_fk')
                ->references('id')
                ->on('user_lesson_occurrence_status_events')
                ->cascadeOnDelete();

            $table->foreign('trainer_profile_id', 'ulosset_trainer_fk')
                ->references('id')
                ->on('trainer_profiles')
                ->cascadeOnDelete();
        });

        if (Schema::hasColumn('user_lesson_occurrence_status_events', 'trainer_profile_id')) {
            DB::table('user_lesson_occurrence_status_events')
                ->whereNotNull('trainer_profile_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows): void {
                    $insert = [];
                    foreach ($rows as $row) {
                        $insert[] = [
                            'user_lesson_occurrence_status_event_id' => (int) $row->id,
                            'trainer_profile_id' => (int) $row->trainer_profile_id,
                        ];
                    }
                    if ($insert !== []) {
                        DB::table('user_lesson_occurrence_status_event_trainers')->insertOrIgnore($insert);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lesson_occurrence_status_event_trainers');
    }
};
