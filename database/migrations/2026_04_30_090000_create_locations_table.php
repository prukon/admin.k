<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->unique(['partner_id', 'name'], 'locations_partner_name_unique');
            $table->index(['partner_id', 'is_enabled'], 'locations_partner_enabled_idx');

            $table->foreign('partner_id', 'locations_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};

