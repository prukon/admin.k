<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_ai_generations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts');
            $table->foreignId('blog_category_id')->nullable()->constrained('blog_categories');

            $table->string('action', 30); // new_post|improve|seo|checklist|regenerate
            $table->string('status', 20)->index(); // queued|running|succeeded|failed

            $table->text('prompt_user');
            $table->longText('prompt_template_snapshot');

            $table->string('model', 255);
            $table->unsignedInteger('max_output_tokens')->default(1800);

            $table->json('request_payload')->nullable();
            $table->longText('response_raw')->nullable();
            $table->json('response_json')->nullable();

            $table->unsignedInteger('usage_input_tokens')->nullable();
            $table->unsignedInteger('usage_output_tokens')->nullable();
            $table->decimal('cost_usd', 10, 4)->nullable();
            $table->decimal('reserved_usd', 10, 4)->nullable();

            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['blog_post_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_ai_generations');
    }
};

