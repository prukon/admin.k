<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_ai_generations', function (Blueprint $table) {
            $table->foreignId('blog_ai_generated_image_id')
                ->nullable()
                ->after('blog_post_id')
                ->constrained('blog_ai_generated_images');
        });
    }

    public function down(): void
    {
        Schema::table('blog_ai_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blog_ai_generated_image_id');
        });
    }
};

