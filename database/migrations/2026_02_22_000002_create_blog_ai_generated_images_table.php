<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_ai_generated_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blog_ai_generation_id')->constrained('blog_ai_generations')->cascadeOnDelete();
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->nullOnDelete();

            $table->string('kind', 20); // cover|inline
            $table->string('aspect', 10)->nullable(); // og|4:3|1:1

            $table->text('prompt');
            $table->string('alt', 255)->nullable();

            $table->string('status', 20)->index(); // queued|running|succeeded|failed
            $table->text('error_message')->nullable();

            $table->string('output_format', 10)->nullable(); // webp|png|jpeg
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('path')->nullable(); // public disk path

            $table->decimal('cost_usd', 10, 4)->nullable();

            $table->timestamps();

            $table->index(['blog_ai_generation_id', 'created_at']);
            $table->index(['blog_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_ai_generated_images');
    }
};

