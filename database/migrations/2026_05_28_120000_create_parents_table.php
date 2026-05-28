<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('lastname', 100)->nullable();
            $table->string('firstname', 100)->nullable();
            $table->string('middlename', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('partner_id', 'parents_partner_id_idx');
            $table->unique('user_id', 'parents_user_id_unique');

            $table->foreign('partner_id', 'parents_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'parents_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parents');
    }
};
