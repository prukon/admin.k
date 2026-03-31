<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialNetworksSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Важно: сидер НЕ перетирает существующие записи.
        // Он лишь добавляет отсутствующие codes (безопасно для управления через админку).
        $defaults = [
            ['code' => 'vk',        'title' => 'VK',        'domain' => 'vk.com',        'icon' => 'fa-brands fa-vk',        'sort' => 10, 'is_enabled' => 1],
            ['code' => 'youtube',   'title' => 'YouTube',   'domain' => 'youtube.com',   'icon' => 'fa-brands fa-youtube',   'sort' => 20, 'is_enabled' => 1],
            ['code' => 'facebook',  'title' => 'Facebook',  'domain' => 'facebook.com',  'icon' => 'fa-brands fa-facebook',  'sort' => 30, 'is_enabled' => 0],
            ['code' => 'instagram', 'title' => 'Instagram', 'domain' => 'instagram.com', 'icon' => 'fa-brands fa-instagram', 'sort' => 40, 'is_enabled' => 0],
            ['code' => 'telegram',  'title' => 'Telegram',  'domain' => 'telegram.org',  'icon' => 'fa-brands fa-telegram',  'sort' => 50, 'is_enabled' => 1],
            ['code' => 'tiktok',    'title' => 'TikTok',    'domain' => 'tiktok.com',    'icon' => 'fa-brands fa-tiktok',    'sort' => 60, 'is_enabled' => 1],
            ['code' => 'whatsapp',  'title' => 'WhatsApp',  'domain' => 'whatsapp.com',  'icon' => 'fa-brands fa-whatsapp',  'sort' => 70, 'is_enabled' => 1],
            ['code' => 'vimeo',     'title' => 'Vimeo',     'domain' => 'vimeo.com',     'icon' => 'fa-brands fa-vimeo',     'sort' => 80, 'is_enabled' => 0],
        ];

        foreach ($defaults as $row) {
            // Важно: insertOrIgnore не обновляет существующие строки.
            DB::table('social_networks')->insertOrIgnore([
                'code' => $row['code'],
                'title' => $row['title'],
                'domain' => $row['domain'],
                'icon' => $row['icon'],
                'sort' => $row['sort'],
                'is_enabled' => $row['is_enabled'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Безопасные "дополнения": если поле ещё пустое (null) — проставим дефолт,
            // не перетирая вручную настроенные значения в БД.
            DB::table('social_networks')
                ->where('code', $row['code'])
                ->whereNull('icon')
                ->update(['icon' => $row['icon'], 'updated_at' => $now]);

            DB::table('social_networks')
                ->where('code', $row['code'])
                ->whereNull('domain')
                ->update(['domain' => $row['domain'], 'updated_at' => $now]);
        }
    }
}

