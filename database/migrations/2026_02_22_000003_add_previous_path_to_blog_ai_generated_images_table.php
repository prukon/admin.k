<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_ai_generated_images', function (Blueprint $table) {
            $table->string('previous_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('blog_ai_generated_images', function (Blueprint $table) {
            $table->dropColumn('previous_path');
        });
    }
};

