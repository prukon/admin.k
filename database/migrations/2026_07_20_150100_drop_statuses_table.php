<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Справочник statuses журнала /schedule больше не используется.
 * Единый справочник — lesson_occurrence_statuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('statuses');
    }

    public function down(): void
    {
        if (Schema::hasTable('statuses')) {
            return;
        }

        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }
};
