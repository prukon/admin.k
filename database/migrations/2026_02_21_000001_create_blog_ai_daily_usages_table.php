<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_ai_daily_usages', function (Blueprint $table) {
            $table->id();

            $table->date('date')->unique();

            $table->decimal('reserved_usd', 10, 4)->default(0);
            $table->decimal('spent_usd', 10, 4)->default(0);

            $table->unsignedInteger('reserved_input_tokens')->default(0);
            $table->unsignedInteger('reserved_output_tokens')->default(0);
            $table->unsignedInteger('spent_input_tokens')->default(0);
            $table->unsignedInteger('spent_output_tokens')->default(0);

            $table->unsignedInteger('requests_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_ai_daily_usages');
    }
};

