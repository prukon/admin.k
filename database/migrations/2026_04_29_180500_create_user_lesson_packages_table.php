<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lesson_packages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('lesson_package_id')
                ->constrained('lesson_packages')
                ->cascadeOnDelete();

            $table->date('starts_at');
            $table->date('ends_at');

            $table->unsignedSmallInteger('lessons_total');
            $table->unsignedSmallInteger('lessons_remaining');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'starts_at', 'ends_at']);
            $table->index(['lesson_package_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lesson_packages');
    }
};

