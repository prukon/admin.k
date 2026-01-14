<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class DocumentationController extends Controller
{
    /**
     * Внутренняя документация проекта (не публичная).
     *
     * ВАЖНО: без произвольных путей (защита от path traversal).
     */
    public function index(): Response
    {
        $items = [
            ['slug' => 'payments', 'title' => 'Оплаты (payables/payment_intents/payments/users_prices)'],
            ['slug' => 'tbank', 'title' => 'T‑Bank (мультирасчёты): настройки/комиссии/flow'],
        ];

        $html = '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Документация проекта</title>'
            . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;line-height:1.5;color:#111;margin:0}'
            . '.wrap{max-width:980px;margin:0 auto;padding:24px}h1{font-size:26px;margin:0 0 12px}ul{margin:8px 0 8px 20px}'
            . 'a{color:#2563eb;text-decoration:none}a:hover{text-decoration:underline}.small{color:#555;font-size:13px}</style></head><body><div class="wrap">'
            . '<h1>Документация проекта</h1>'
            . '<div class="small">Раздел: <code>/docs/documentation</code></div>'
            . '<ul>';

        foreach ($items as $it) {
            $html .= '<li><a href="' . e(url('/docs/documentation/' . $it['slug'])) . '">' . e($it['title']) . '</a></li>';
        }

        $html .= '</ul></div></body></html>';

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function show(string $page): Response
    {
        $map = [
            'payments' => base_path('docs/documentation/payments.html'),
            'tbank' => base_path('docs/documentation/tbank.html'),
        ];

        if (!isset($map[$page])) {
            abort(404);
        }

        $path = $map[$page];
        if (!is_file($path)) {
            abort(404);
        }

        return response(file_get_contents($path), 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}

