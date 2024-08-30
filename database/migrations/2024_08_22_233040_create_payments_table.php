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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->Integer('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('team_title')->nullable();
            $table->dateTime('operation_date')->nullable();
            $table->string('payment_month')->nullable();
            $table->decimal('summ', 15, 2);
            $table->string('payment_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
