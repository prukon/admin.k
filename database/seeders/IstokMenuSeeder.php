<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class IstokMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Тестовый партнёр (Исток)
        DB::table('partners')->updateOrInsert(
            ['id' => 1],
            [
                'order_by' => 0,
                'is_enabled' => 1,
                'business_type' => 'individual_entrepreneur',
                'title' => 'Школа футбола "Исток"',
                'organization_name' => null,
                'tax_id' => '860904518893',
                'kpp' => null,
                'registration_number' => '315784700040909',
                'address' => null,
                'phone' => null,
                'email' => '-',
                'city' => null,
                'zip' => null,
                'ceo' => null,
                'wallet_balance' => 0,
                'website' => null,
                'sms_name' => null,
                'bank_name' => null,
                'bank_bik' => null,
                'bank_account' => null,
                'activity_start_date' => null,
                'tinkoff_partner_id' => null,
                'sm_register_status' => null,
                'bank_details_version' => null,
                'bank_details_last_updated_at' => null,
                'sm_details_template' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        // 2. Демонстрационные команды истока
        DB::table('teams')->upsert(
            [
                [
                    'id' => 1,
                    'title' => 'Дубль',
                    'image' => 'https://via.placeholder.com/640x480.png/004488?text=totam',
                    'is_enabled' => 1,
                    'partner_id' => 1,
                    'order_by' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => 2,
                    'title' => 'Легион 630',
                    'image' => 'https://via.placeholder.com/640x480.png/00bbaa?text=mollitia',
                    'is_enabled' => 1,
                    'partner_id' => 1,
                    'order_by' => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => 3,
                    'title' => 'Феникс',
                    'image' => 'https://via.placeholder.com/640x480.png/001144?text=quas',
                    'is_enabled' => 1,
                    'partner_id' => 1,
                    'order_by' => 30,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => 4,
                    'title' => 'Штурм',
                    'image' => 'https://via.placeholder.com/640x480.png/0000bb?text=non',
                    'is_enabled' => 1,
                    'partner_id' => 1,
                    'order_by' => 40,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => 5,
                    'title' => 'Алмаз',
                    'image' => 'https://via.placeholder.com/640x480.png/0000bb?text=non',
                    'is_enabled' => 1,
                    'partner_id' => 1,
                    'order_by' => 50,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['id'], // уникальный ключ
            ['title', 'image', 'is_enabled', 'order_by', 'updated_at']
        );


        //2. Меню в шапке
        $items = [
            [
                'name' => 'Главная',
                'link' => 'https://fc-istok.ru/',
                'target_blank' => 1,
            ],
            [
                'name' => 'Расписание занятий',
                'link' => 'https://fc-istok.ru/schedule.html',
                'target_blank' => 1,
            ],
            [
                'name' => 'Контакты',
                'link' => 'https://fc-istok.ru/#b6',
                'target_blank' => 1,
            ],
        ];
        foreach ($items as $item) {
            DB::table('menu_items')->updateOrInsert(
            // критерий уникальности — выбери, как логичнее:
            // по link или по name
                ['link' => $item['link']],
                [
                    'name' => $item['name'],
                    'target_blank' => $item['target_blank'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }


        //3. Сссылки соц сетей

        // Список соц сетей
        DB::table('social_items')->upsert(
            [
                ['id' => 1,  'name' => 'vk.com',        'link' => null, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2,  'name' => 'YouTube.com',   'link' => null, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 4,  'name' => 'facebook.com',  'link' => null, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 5,  'name' => 'Instagram.com', 'link' => null, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 8,  'name' => 'Telegram.org',  'link' => null, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 9, 'name' => 'TikTok.com',    'link' => null, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 10, 'name' => 'WhatsApp.com',  'link' => null, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 11, 'name' => 'Vimeo.com',     'link' => null, 'created_at' => $now, 'updated_at' => $now],
            ],
            ['id'],
            ['name', 'link', 'updated_at']
        );


    }
}