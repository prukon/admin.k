<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('trainers');

        Schema::create('trainer_profiles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('user_id');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique('user_id', 'trainer_profiles_user_id_unique');
            $table->index(['partner_id', 'is_enabled'], 'trainer_profiles_partner_enabled_idx');
            $table->index(['partner_id', 'sort_order'], 'trainer_profiles_partner_sort_idx');

            $table->foreign('partner_id', 'trainer_profiles_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'trainer_profiles_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_profiles');

        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->string('lastname')->nullable();
            $table->string('name');
            $table->string('photo_thumb_path')->nullable();
            $table->string('photo_large_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['partner_id', 'is_enabled'], 'trainers_partner_enabled_idx');
            $table->index(['partner_id', 'sort_order'], 'trainers_partner_sort_idx');
            $table->foreign('partner_id', 'trainers_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();
        });
    }
};
