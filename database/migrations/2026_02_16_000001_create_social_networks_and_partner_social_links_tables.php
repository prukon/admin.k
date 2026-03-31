<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_networks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();        // stable идентификатор (vk, telegram, ...)
            $table->string('title');                     // отображаемое название
            $table->string('domain')->nullable();        // опционально: vk.com, telegram.org и т.п.
            $table->string('icon')->nullable();          // опционально: имя иконки/класс/путь
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('partner_social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('social_network_id')->constrained('social_networks')->cascadeOnDelete();
            $table->string('url')->nullable();
            $table->timestamps();

            $table->unique(['partner_id', 'social_network_id'], 'partner_social_links_partner_network_uq');
            $table->index(['partner_id'], 'partner_social_links_partner_idx');
        });

        // Базовый набор соцсетей. Это справочник, им управляем через БД дальше.
        // Вставляем только если таблица пустая (чтобы не перетирать кастомизации).
        if (DB::table('social_networks')->count() === 0) {
            $now = now();
            DB::table('social_networks')->insert([
                ['code' => 'vk',        'title' => 'VK',        'domain' => 'vk.com',        'icon' => null, 'sort' => 10, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'youtube',   'title' => 'YouTube',   'domain' => 'youtube.com',   'icon' => null, 'sort' => 20, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'facebook',  'title' => 'Facebook',  'domain' => 'facebook.com',  'icon' => null, 'sort' => 30, 'is_enabled' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'instagram', 'title' => 'Instagram', 'domain' => 'instagram.com', 'icon' => null, 'sort' => 40, 'is_enabled' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'telegram',  'title' => 'Telegram',  'domain' => 'telegram.org',  'icon' => null, 'sort' => 50, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'tiktok',    'title' => 'TikTok',    'domain' => 'tiktok.com',    'icon' => null, 'sort' => 60, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'whatsapp',  'title' => 'WhatsApp',  'domain' => 'whatsapp.com',  'icon' => null, 'sort' => 70, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'vimeo',     'title' => 'Vimeo',     'domain' => 'vimeo.com',     'icon' => null, 'sort' => 80, 'is_enabled' => 0, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_social_links');
        Schema::dropIfExists('social_networks');
    }
};

