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
        Schema::create('schedule_users', function (Blueprint $table) {
            $table->id();
            $table->Integer('user_id');
            $table->date('date');
            $table->boolean('is_enabled')->default(1);
            $table->boolean('is_paid')->default(0);
            $table->boolean('is_hospital')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
           });
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_users');
    }
};
