<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lesson_occurrence_status_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('team_schedule_slot_id');
            $table->date('occurrence_date');
            $table->unsignedBigInteger('user_lesson_package_id');
            $table->unsignedBigInteger('lesson_occurrence_status_id');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index(
                ['partner_id', 'team_schedule_slot_id', 'occurrence_date'],
                'ulosse_partner_slot_date_idx'
            );
            $table->index(
                ['partner_id', 'user_id', 'occurrence_date'],
                'ulosse_partner_user_date_idx'
            );
            $table->index(
                [
                    'partner_id',
                    'user_id',
                    'team_schedule_slot_id',
                    'occurrence_date',
                    'user_lesson_package_id',
                ],
                'ulosse_occurrence_lookup_idx'
            );

            $table->foreign('partner_id', 'ulosse_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'ulosse_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('team_schedule_slot_id', 'ulosse_slot_fk')
                ->references('id')
                ->on('team_schedule_slots')
                ->cascadeOnDelete();

            $table->foreign('user_lesson_package_id', 'ulosse_ulp_fk')
                ->references('id')
                ->on('user_lesson_packages')
                ->cascadeOnDelete();

            $table->foreign('lesson_occurrence_status_id', 'ulosse_los_fk')
                ->references('id')
                ->on('lesson_occurrence_statuses')
                ->restrictOnDelete();

            $table->foreign('created_by', 'ulosse_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lesson_occurrence_status_events');
    }
};
