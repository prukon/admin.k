<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_team_schedule_slots', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('team_schedule_slot_id');

            $table->date('starts_at');
            $table->date('ends_at')->default('9999-12-31');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'team_schedule_slot_id', 'starts_at'], 'utss_user_slot_start_unique');
            $table->index(['partner_id', 'user_id'], 'utss_partner_user_idx');
            $table->index(['team_schedule_slot_id'], 'utss_slot_idx');

            $table->foreign('partner_id', 'utss_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'utss_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('team_schedule_slot_id', 'utss_slot_fk')
                ->references('id')
                ->on('team_schedule_slots')
                ->cascadeOnDelete();

            $table->foreign('created_by', 'utss_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_team_schedule_slots');
    }
};

