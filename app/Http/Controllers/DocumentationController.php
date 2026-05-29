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
        'partner-scope-guide'         => 'Как работать с partner_id: обычный админ и страницы superadmin',
        'partner-context'             => 'Партнёр‑контекст и SetPartner (current_partner/anti‑leak/блокировки)',
        'partners-permissions'        => 'Партнёры: базовые роли и права по умолчанию (user/admin в разрезе партнёра)',
        'settings-roles-custom'       => 'Настройки: кастомные роли и стартовый набор прав (admin из конфига, UI без перезагрузки)',
        'settings-logs'               => 'Настройки → Логи: my_logs, фильтры (суперадмин/авторизации/unknown), SUPERADMIN_ALL_OR_FILTER',
        'reports-payments'            => 'Отчёт «Платежи» (админка): таблица, «Поля списка», права (доп. колонки, история T‑Bank)',
        'reports-admin'               => 'Отчёты (админка): фильтр по локации, AJAX/суммы (debt/ltv/monthly/payment-intents/fiscal-receipts)',
        'reusable-ui-partials'        => 'Переиспользование UI: toolbar, hover-list, Select2/DataTables ru',
        'tbank'                       => 'T‑Bank (мультирасчёты): настройки/комиссии/flow, СБП (QR) в CRM',
        'tbank-admin-payouts'          => 'T‑Bank: админка выплат (список, DataTables, карточка, права tbank.payouts.manage)',
        'tbank-refunds-payout-cancel'   => 'T‑Bank: возврат в отчёте «Платежи» и отмена отложенной выплаты (tinkoff_payments → tinkoff_payouts)',
        'queues-monitoring'             => 'Очереди в админке: мониторинг, доступы, queue.log, restart worker',
        'tests-standards'             => 'Требования к единообразию Feature‑тестов (партнёр/авторизация/права)',
        'lesson-packages'             => 'Абонементы: период, привязка к календарю, лимит строк, lessons_remaining и статусы',
        'school-schedule-calendar'    => 'Расписание школы: календарь, статусы, пробное, разовое занятие, JSON/API',
        'location-team-bindings'      => 'Локации ↔ группы (location_team): привязки, расписание, отчёты; users.location_id удалён',
        'admin-locations'             => 'Локации (админка): /admin/locations — view/manage, team_ids, hover-list, Select2',
        'admin-sport-types'           => 'Виды спорта (админка): /admin/sport-types — sport_types.view/manage, teams.sport_type_id, лендинг',
        'admin-teams'                 => 'Группы (админка): /admin/teams — groups.view, локации, тренер, расписание, вид спорта',
        'schedule-journal'            => 'Журнал расписания: /schedule, statuses, schedule_users, тренер при «Посетил»',
        'schedule-trainer-workload'   => 'Нагрузка тренеров: вкладка /schedule/trainer-workload, матрица, быстрый выбор месяца, AJAX data',
        'schedule-trainer-salary'     => 'ЗП тренеров: черновик, формула, autosave, слепки vN/batch, schedule.trainerSalary.*',
        'schedule-trainer-salary-sheets' => 'Листы ЗП: архив слепков (readonly), batch/snapshot, latest_only',
        'admin-trainers'              => 'Тренеры (админка): /admin/trainers, trainer_profiles, team_trainer, фильтр в отчётах',
        'parents-and-family-cabinet'  => 'Родители и семейный кабинет: parents, users.parent_id, переключение детей (братья), sidebarPanelIdentity, active_student',
        'admin-users'                 => 'Пользователи (админка): /admin/users, parents, группа ученика (без users.location_id)',
        'contracts'                   => 'Договоры (клиентские): карточка, родитель (parents), SMS/Подпислон, скачивание',
        'contract-templates'          => 'Шаблоны договоров DOCX: версии, поля, email, режим «форма клиенту»',
        'school-leads-widget'         => 'Заявки с сайта: виджет iframe, CRM, лид→клиент→договор, Telegram',
        'school-leads-landing'        => 'Страница заявки партнёра: /lead/{landingKey}, полная форма, source=landing',
        'blog'                        => 'Блог: /blog, админка (blog.view), ИИ, VK (kidscrm): анонс ИИ, очередь default',
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
