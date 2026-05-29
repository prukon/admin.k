<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_types', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->unique(['partner_id', 'name'], 'sport_types_partner_name_unique');
            $table->index(['partner_id', 'is_enabled'], 'sport_types_partner_enabled_idx');

            $table->foreign('partner_id', 'sport_types_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_types');
    }
};
