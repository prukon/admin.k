<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->boolean('publish_to_vk')->default(true)->after('published_at');
            $table->string('vk_message', 500)->nullable()->after('publish_to_vk');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['publish_to_vk', 'vk_message']);
        });
    }
};
