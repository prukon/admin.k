<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_weekdays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('weekday_id');

            $table->index('team_id','team_weekday_team_idx');
            $table->index('weekday_id','team_weekday_weekday_idx');

//            $table->foreign('team_id','team_weekday_team_fk')->on('teams')->references('id');

            $table->timestamps();
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_weekdays');
    }
};
