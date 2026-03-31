<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('logs');
    }

    public function down(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('type');
            $table->integer('action')->nullable();
            $table->unsignedBigInteger('author_id');
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();
        });
    }
};


