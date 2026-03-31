<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('social_items');
    }

    public function down(): void
    {
        Schema::create('social_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable()->after('id');
            $table->string('name');
            $table->string('link')->nullable();
            $table->timestamps();
        });
    }
};

