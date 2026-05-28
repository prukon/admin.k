<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_team', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('team_id');

            $table->timestamps();

            $table->unique(['location_id', 'team_id'], 'location_team_location_team_unique');
            $table->index(['partner_id', 'location_id'], 'location_team_partner_location_idx');
            $table->index(['partner_id', 'team_id'], 'location_team_partner_team_idx');

            $table->foreign('partner_id', 'location_team_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('location_id', 'location_team_location_fk')
                ->references('id')
                ->on('locations')
                ->cascadeOnDelete();

            $table->foreign('team_id', 'location_team_team_fk')
                ->references('id')
                ->on('teams')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_team');
    }
};
