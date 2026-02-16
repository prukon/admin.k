<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_social_links', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_social_links', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('url');
            }
            if (!Schema::hasColumn('partner_social_links', 'sort')) {
                $table->unsignedSmallInteger('sort')->default(0)->after('is_enabled');
            }
        });

        // Индексы отдельным вызовом, чтобы не падать если уже существуют
        Schema::table('partner_social_links', function (Blueprint $table) {
            $table->index(['partner_id', 'is_enabled', 'sort'], 'partner_social_links_partner_enabled_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('partner_social_links', function (Blueprint $table) {
            // dropIndex по имени
            $table->dropIndex('partner_social_links_partner_enabled_sort_idx');

            if (Schema::hasColumn('partner_social_links', 'sort')) {
                $table->dropColumn('sort');
            }
            if (Schema::hasColumn('partner_social_links', 'is_enabled')) {
                $table->dropColumn('is_enabled');
            }
        });
    }
};

