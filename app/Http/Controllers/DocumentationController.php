<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DocumentationController extends Controller
{
    /**
     * Заголовки страниц в оглавлении (slug без .html).
     *
     * @var array<string, string>
     */
    private const PAGE_TITLES = [
        'payments'                    => 'Оплаты (payables/payment_intents/payments/users_prices)',
        'partner-context'             => 'Партнёр‑контекст и SetPartner (current_partner/anti‑leak/блокировки)',
        'partners-permissions'        => 'Партнёры: базовые роли и права по умолчанию (user/admin в разрезе партнёра)',
        'settings-roles-custom'       => 'Настройки: кастомные роли и стартовый набор прав (admin из конфига, UI без перезагрузки)',
        'reports-payments'            => 'Отчёт «Платежи» (админка): таблица, «Поля списка», права (доп. колонки, история T‑Bank)',
        'reports-admin'               => 'Отчёты (админка): фильтр по локации, AJAX/суммы (debt/ltv/monthly/payment-intents/fiscal-receipts)',
        'tbank'                       => 'T‑Bank (мультирасчёты): настройки/комиссии/flow, СБП (QR) в CRM',
        'tbank-admin-payouts'          => 'T‑Bank: админка выплат (список, DataTables, карточка, права tbank.payouts.manage)',
        'tbank-refunds-payout-cancel'   => 'T‑Bank: возврат в отчёте «Платежи» и отмена отложенной выплаты (tinkoff_payments → tinkoff_payouts)',
        'queues-monitoring'             => 'Очереди в админке: мониторинг, доступы, queue.log, restart worker',
        'tests-standards'             => 'Требования к единообразию Feature‑тестов (партнёр/авторизация/права)',
        'lesson-packages'             => 'Абонементы: период, привязка к календарю, лимит строк, lessons_remaining и статусы',
        'school-schedule-calendar'    => 'Расписание школы: календарь, статусы (consumes_lesson), пробное, JSON/API',
        'admin-teams'                 => 'Группы (админка): /admin/teams, groups.view и schedule.view (колонка «Расписание», дни в модалках)',
        'admin-users'                 => 'Пользователи (админка): /admin/users, локация ученика (locations.view), доп. поля',
        'contracts'                   => 'Договоры (клиентские): скачивание в интерфейсе (CRM и «Мои документы»)',
        'school-leads-widget'         => 'Заявки с сайта: виджет, CRM, лид→клиент→договор, toolbar, Telegram',
    ];

    /**
     * @return array<string, string> slug => absolute path
     */
    private function pageFiles(): array
    {
        $dir = base_path('docs/documentation');
        $pages = [];

        foreach (glob($dir . '/*.html') ?: [] as $path) {
            $slug = basename($path, '.html');
            if ($slug === 'index') {
                continue;
            }
            $pages[$slug] = $path;
        }

        ksort($pages);

        return $pages;
    }

    private function normalizeSlug(string $page): string
    {
        return preg_replace('/\.html$/i', '', $page) ?? $page;
    }

    /**
     * Внутренняя документация проекта (не публичная).
     *
     * ВАЖНО: без произвольных путей (защита от path traversal).
     */
    public function index(): Response
    {
        $html = '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Документация проекта</title>'
            . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;line-height:1.5;color:#111;margin:0}'
            . '.wrap{max-width:980px;margin:0 auto;padding:24px}h1{font-size:26px;margin:0 0 12px}ul{margin:8px 0 8px 20px}'
            . 'a{color:#2563eb;text-decoration:none}a:hover{text-decoration:underline}.small{color:#555;font-size:13px}</style></head><body><div class="wrap">'
            . '<h1>Документация проекта</h1>'
            . '<div class="small">Раздел: <code>/docs/documentation</code> · короткая ссылка: <code>/doc</code></div>'
            . '<ul>';

        foreach ($this->pageFiles() as $slug => $_path) {
            $title = self::PAGE_TITLES[$slug] ?? $slug;
            $html .= '<li><a href="' . e(url('/docs/documentation/' . $slug)) . '">' . e($title) . '</a></li>';
        }

        $html .= '</ul></div></body></html>';

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function show(string $page): Response|RedirectResponse
    {
        $slug = $this->normalizeSlug($page);
        $files = $this->pageFiles();

        if (!isset($files[$slug])) {
            abort(404);
        }

        if (str_ends_with(strtolower($page), '.html')) {
            return redirect()->route('docs.documentation.show', ['page' => $slug], 301);
        }

        $path = $files[$slug];
        if (!is_file($path)) {
            abort(404);
        }

        return response(file_get_contents($path), 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
