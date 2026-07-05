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
        'payments'                    => 'Оплаты: payables/intents/payments/users_prices, снимок payments.team_id, multi-team витрина',
        'partner-scope-guide'         => 'Как работать с partner_id: обычный админ и страницы superadmin',
        'partner-context'             => 'Партнёр‑контекст и SetPartner (current_partner/anti‑leak/блокировки)',
        'partners-permissions'        => 'Партнёры: базовые роли и права по умолчанию (user/admin в разрезе партнёра)',
        'settings-roles-custom'       => 'Настройки: кастомные роли и стартовый набор прав (admin из конфига, UI без перезагрузки)',
        'settings-permission-groups'  => 'Матрица прав: группы permissions (permission_groups), аккордеон «Права и роли»',
        'audit-my-logs'               => 'Аудит CRM (my_logs): AuditEvent, AuditLogger, event/level, без legacy type/action в runtime',
        'settings-logs'               => 'Настройки → Логи: вкладка my_logs, фильтры event/level, доступ, SUPERADMIN_ALL_OR_FILTER',
        'settings-payment-systems'    => 'Настройки → Платёжные системы: Robokassa per-partner, глобальный терминал T‑Bank, TbankTerminalConfig, права, миграция',
        'reports-payments'            => 'Отчёт «Платежи»: payments.team_id (колонка/фильтр), «Поля списка», права, история T‑Bank',
        'reports-admin'               => 'Отчёты (админка): KidsCrmDataTable, columns-settings, «Исходящие письма» (модалка, iframe HTML), фильтры, AJAX/суммы',
        'reusable-ui-partials'        => 'Переиспользование UI: KidsCrmTooltip / KidsCrmDataTable (link, bindNavLinks, icon, inline-select, миграция custom), toolbar, logModal, Select2',
        'tbank'                       => 'T‑Bank (мультирасчёты): глобальный терминал, комиссии, автовыплата, карточка платежа (организация, timeline), СБП (QR)',
        'tbank-admin-payouts'          => 'T‑Bank: админка выплат (список, колонка «Организация», DataTables, карточка, tbank.payouts.manage)',
        'tbank-refunds-payout-cancel'   => 'T‑Bank: возврат в отчёте «Платежи» и отмена отложенной выплаты (tinkoff_payments → tinkoff_payouts)',
        'queues-monitoring'             => 'Очереди в админке: мониторинг, доступы, queue.log, restart worker',
        'tests-standards'             => 'Требования к единообразию Feature‑тестов (партнёр/авторизация/права)',
        'dev-seed-data'               => 'Dev-фикстуры: SEED_DEV_DATA, цепочка Dev*-сидеров, юр. лица, T‑Bank, ограничения prod',
        'lesson-packages'             => 'Абонементы: шаблоны (автосписание), назначения, период, календарь, lessons_remaining',
        'school-schedule-calendar'    => 'Расписание школы: календарь, inline-панели привязки, подписи кнопок, JSON/API',
        'location-team-bindings'      => 'Объекты ↔ группы (teams.location_id): одна группа — один объект, sync, лендинг, слоты, отчёты',
        'directories-hierarchy'       => 'Справочники: иерархия Район → Объект → Группа; вкладки, DirectoriesMenu (подпись сайдбара), права, БД, лендинг',
        'admin-districts'             => 'Районы (админка): /admin/districts — districts.view, CRUD, hard delete, вкладка в «Справочники»',
        'admin-locations'             => 'Объекты (админка): /admin/locations — view/manage, district_id, team_ids, вкладка в «Справочники»',
        'admin-sport-types'           => 'Виды спорта (админка): /admin/sport-types — sport_types.view/manage, вкладка в «Справочники», teams.sport_type_id, лендинг',
        'admin-teams'                 => 'Группы (админка): /admin/teams — groups.view, вкладка в «Справочники», month_price, объекты, тренер, расписание',
        'schedule-journal'            => 'Журнал расписания: /schedule, statuses, schedule_users, тренер при «Посетил»',
        'schedule-trainer-workload'   => 'Нагрузка тренеров: вкладка /schedule/trainer-workload, матрица, быстрый выбор месяца, AJAX data',
        'schedule-trainer-salary'     => 'ЗП тренеров: черновик, формула, autosave, слепки vN/batch, schedule.trainerSalary.*',
        'schedule-trainer-salary-sheets' => 'Листы ЗП: архив слепков (readonly), batch/snapshot, latest_only',
        'admin-trainers'              => 'Тренеры (админка): /admin/trainers, вкладка в разделе «Пользователи», trainer_profiles, team_trainer, фильтр в отчётах',
        'admin-users-section'         => 'Раздел «Пользователи» (вкладки): ученики, тренеры, администраторы, /admin/roles/{name}, UsersSectionTabsResolver',
        'admin-role-staff'            => 'Администраторы и кастомные роли: /admin/administrators, /admin/roles/{name}, RoleStaffUserController, users.role.update',
        'parents-and-family-cabinet'  => 'Родители и семейный кабинет: parents, users.parent_id, переключение детей (братья), sidebarPanelIdentity, active_student',
        'dashboard-cabinet'           => 'Консоль (/cabinet): блоки оплат (доп./абонементы/сезоны), setPrices.cabinetSeasons.view, селект группы, семейный контекст',
        'student-team-membership'     => 'Ученик ↔ группы (M:N team_user): pivot, users_prices.team_id, payments.team_id, отчёты, ЛК',
        'admin-users'                 => 'Ученики (админка): /admin/users только role=user, импорт Excel (users.import), родители, договор, welcome-письмо, пол, комментарий, team_ids',
        'contracts'                   => 'Договоры (клиентские): PDF и режим «форма клиенту», карточка, revoke/refund, вкладка «Шаблоны»',
        'contract-templates'          => 'Шаблоны DOCX: модалки, fields_schema, fill_sort_order, email, версии',
        'account-contract-fill'       => 'Заполнение договора родителем: кабинет, fill/generate/sign, sync профиля',
        'account-partner-organization' => 'Организация партнёра: ЛК и админка, PartnerLegacyLegalFields, оплаты без fallback на partners',
        'school-leads-widget'         => 'Заявки с сайта: виджет iframe, CRM, лид→клиент (welcome-письмо), договор, статусы, Telegram',
        'school-leads-landing'        => 'Страница заявки партнёра: /lead/{landingSlug}, каскад район→объект→услуга, district_id',
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
