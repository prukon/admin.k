<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_ai_generations', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('budget_date');
            $table->string('phase', 30)->nullable()->after('progress'); // text|cover|inline|done

            $table->boolean('want_cover_image')->default(false)->after('phase');
            $table->unsignedTinyInteger('inline_images_count')->default(0)->after('want_cover_image'); // 0..3

            $table->decimal('cost_text_usd', 10, 4)->nullable()->after('usage_output_tokens');
            $table->decimal('cost_images_usd', 10, 4)->nullable()->after('cost_text_usd');
            $table->decimal('cost_total_usd', 10, 4)->nullable()->after('cost_images_usd');
        });
    }

    public function down(): void
    {
        Schema::table('blog_ai_generations', function (Blueprint $table) {
            $table->dropColumn([
                'progress',
                'phase',
                'want_cover_image',
                'inline_images_count',
                'cost_text_usd',
                'cost_images_usd',
                'cost_total_usd',
            ]);
        });
    }
};

