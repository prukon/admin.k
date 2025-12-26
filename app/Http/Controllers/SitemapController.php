<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];

        // Главная страница
        $urls[] = [
            'loc' => url('/'),
            'changefreq' => 'daily',
            'priority' => '1.0',
            'lastmod' => now()->toAtomString(),
        ];

        // Лендинги под ниши
        $urls[] = [
            'loc' => url('/crm-dlya-futbolnoy-sekcii'),
            'changefreq' => 'weekly',
            'priority' => '0.9',
            'lastmod' => now()->toAtomString(),
        ];

        $urls[] = [
            'loc' => url('/crm-dlya-tancevalnoy-studii'),
            'changefreq' => 'weekly',
            'priority' => '0.9',
            'lastmod' => now()->toAtomString(),
        ];

        $urls[] = [
            'loc' => url('/crm-dlya-shkoly-edinoborstv'),
            'changefreq' => 'weekly',
            'priority' => '0.9',
            'lastmod' => now()->toAtomString(),
        ];

        $urls[] = [
            'loc' => url('/crm-dlya-detskogo-razvivayushchego-centra'),
            'changefreq' => 'weekly',
            'priority' => '0.9',
            'lastmod' => now()->toAtomString(),
        ];

        $urls[] = [
            'loc' => url('/crm-dlya-shkol-gimnastiki-i-akrobatiki'),
            'changefreq' => 'weekly',
            'priority' => '0.9',
            'lastmod' => now()->toAtomString(),
        ];

        $urls[] = [
            'loc' => url('/crm-dlya-detskih-yazykovyh-shkol'),
            'changefreq' => 'weekly',
            'priority' => '0.9',
            'lastmod' => now()->toAtomString(),
        ];

        // Публичная оферта (общая)
        $urls[] = [
            'loc' => url('/oferta'),
            'changefreq' => 'yearly',
            'priority' => '0.3',
            'lastmod' => now()->toAtomString(),
        ];

        // Политика конфиденциальности
        $urls[] = [
            'loc' => url('/privacy-policy'),
            'changefreq' => 'yearly',
            'priority' => '0.3',
            'lastmod' => now()->toAtomString(),
        ];

        // Пользовательское соглашение
        $urls[] = [
            'loc' => url('/terms'),
            'changefreq' => 'yearly',
            'priority' => '0.3',
            'lastmod' => now()->toAtomString(),
        ];

        // В sitemap осознанно НЕ добавляем:
        // - /contact/send (POST, служебный маршрут формы)
        // - /partner/oferta (партнерская оферта, не для поисковой выдачи)

        $xml = view('sitemap.xml', [
            'urls' => $urls,
        ])->render();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }
}
