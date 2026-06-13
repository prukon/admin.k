<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['partner_id', 'name'], 'districts_partner_name_unique');
            $table->index(['partner_id', 'is_enabled'], 'districts_partner_enabled_idx');
            $table->index(['partner_id', 'sort_order'], 'districts_partner_sort_idx');

            $table->foreign('partner_id', 'districts_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
