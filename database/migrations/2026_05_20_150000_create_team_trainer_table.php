<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_trainer', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('trainer_profile_id');

            $table->timestamps();

            $table->unique(['team_id', 'trainer_profile_id'], 'team_trainer_team_trainer_unique');
            $table->index(['partner_id', 'team_id'], 'team_trainer_partner_team_idx');
            $table->index(['partner_id', 'trainer_profile_id'], 'team_trainer_partner_trainer_idx');

            $table->foreign('partner_id', 'team_trainer_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('team_id', 'team_trainer_team_fk')
                ->references('id')
                ->on('teams')
                ->cascadeOnDelete();

            $table->foreign('trainer_profile_id', 'team_trainer_trainer_fk')
                ->references('id')
                ->on('trainer_profiles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_trainer');
    }
};
