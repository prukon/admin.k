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
        'money'                       => 'Деньги: канон копеек (BIGINT *_cents), App\\Support\\Money, округление, скидки % вариант А, персональная скидка ученика',
        'payments'                    => 'Оплаты: payables/intents/payments/users_prices, снимок payments.team_id, multi-team витрина, семейная оплата activeStudent (FamilyPaymentPayerResolver)',
        'partner-scope-guide'         => 'Как работать с partner_id: обычный админ и страницы superadmin',
        'partner-context'             => 'Партнёр‑контекст и SetPartner (current_partner/anti‑leak/блокировки)',
        'partners-permissions'        => 'Партнёры: базовые роли и права; опциональные (users.discount.manage, customPayments, account.user.team.update, …)',
        'settings-roles-custom'       => 'Настройки: кастомные роли и стартовый набор прав (admin из конфига, UI без перезагрузки)',
        'settings-permission-groups'  => 'Матрица прав: группы permissions (permission_groups), аккордеон «Права и роли»',
        'audit-my-logs'               => 'Аудит CRM (my_logs): AuditEvent, AuditLogger, event/level, без legacy type/action в runtime',
        'settings-logs'               => 'Настройки → Логи: вкладка my_logs, фильтры event/level, доступ, SUPERADMIN_ALL_OR_FILTER',
        'settings-payment-systems'    => 'Настройки → Платёжные системы: Robokassa per-partner, глобальный терминал T‑Bank, TbankTerminalConfig, права, миграция',
        'reports-payments'            => 'Отчёт «Платежи»: payments.team_id (колонка/фильтр), «Поля списка», «Показать N» (page_length), права, история T‑Bank; ФИО = payments.user_id (семья — выбранный ребёнок)',
        'reports-admin'               => 'Отчёты (админка): KidsCrmDataTable, columns-settings, «Показать N» (page_length), «Исходящие письма» (модалка, iframe HTML), фильтры, AJAX/суммы',
        'reusable-ui-partials'        => 'Переиспользование UI: KidsCrmTooltip / KidsCrmDataTable (persistPageLength), toolbar, logModal, Select2 (в т.ч. тренеры журнала)',
        'tbank'                       => 'T‑Bank (мультирасчёты): глобальный терминал, комиссии, автовыплата, карточка платежа (организация, timeline), СБП (QR)',
        'tbank-admin-payouts'          => 'T‑Bank: админка выплат (список, колонка «Организация», DataTables, «Показать N» / page_length, карточка, tbank.payouts.manage)',
        'tbank-refunds-payout-cancel'   => 'T‑Bank: возврат в отчёте «Платежи» и отмена отложенной выплаты (tinkoff_payments → tinkoff_payouts)',
        'queues-monitoring'             => 'Очереди в админке: мониторинг, доступы, queue.log, restart worker',
        'tests-standards'             => 'Требования к единообразию Feature‑тестов (партнёр/авторизация/права)',
        'dev-seed-data'               => 'Dev-фикстуры: SEED_DEV_DATA, цепочка Dev*-сидеров, юр. лица, T‑Bank, ограничения prod',
        'lesson-packages'             => 'Абонементы: шаблоны, назначения, автопролонгация fixed (новый цикл = текущий % скидки × цена шаблона), billing_month / период, ends_at, конфликт fixed, статусы, Excel',
        'postpay'                     => 'Постоплата (postpay): шаблон, users_prices без ULP, скидка по снимку строки, журнал create_postpay, лок после оплаты; право lessonPackages.type.postpay',
        'lesson-packages-type-postpay' => 'Право lessonPackages.type.postpay: выбор типа «Постоплата», скрытое, не в базовых ролях',
        'school-schedule-calendar'    => 'Расписание школы: календарь, разовое fee_amount_default = каталог × % карточки, assign-fixed конфликт, inline-панели, выгрузка Excel, JSON/API',
        'location-team-bindings'      => 'Объекты ↔ группы (teams.location_id): одна группа — один объект, sync, лендинг, слоты, отчёты',
        'directories-hierarchy'       => 'Справочники: иерархия Район → Объект → Группа; вкладки (+ Абонементы), DirectoriesMenu, права, БД, лендинг',
        'admin-districts'             => 'Районы (админка): /admin/districts — districts.view, CRUD, hard delete, вкладка в «Справочники»',
        'admin-locations'             => 'Объекты (админка): /admin/locations — view/manage, district_id, team_ids, вкладка в «Справочники»',
        'admin-sport-types'           => 'Виды спорта (админка): /admin/sport-types — sport_types.view/manage, вкладка в «Справочники», teams.sport_type_id, лендинг',
        'admin-teams'                 => 'Группы (админка): /admin/teams — groups.view, вкладка в «Справочники», month_price_cents, объекты, тренер, расписание',
        'schedule-journal'            => 'Журнал /schedule: раскладка fixed/flexible, колонка оплаты месяца по группам (users_prices / effective_is_paid), разовое (create_new — сумма и снимок персональной скидки), статусы, мультитренеры при «Посетил» (pivot → ЗП/нагрузка)',
        'schedule-trainer-workload'   => 'Нагрузка тренеров: /schedule/trainer-workload, pivot event_trainers, матрица, AJAX data',
        'schedule-trainer-salary'     => 'ЗП тренеров: схемы classic и kansas, типы тренера, модалка «Настройки месяца», смена схемы без слепка, *_cents, pivot визита, autosave, слепки',
        'schedule-trainer-salary-sheets' => 'Листы ЗП: архив слепков (readonly), batch/snapshot, latest_only',
        'admin-trainers'              => 'Тренеры (админка): /admin/trainers, trainer_profiles, типы тренера (Канзас), team_trainer, мультитренеры в журнале при «Посетил»',
        'admin-users-section'         => 'Раздел «Пользователи» (вкладки): ученики, тренеры, администраторы, /admin/roles/{name}, UsersSectionTabsResolver',
        'admin-role-staff'            => 'Администраторы и кастомные роли: /admin/administrators, /admin/roles/{name}, RoleStaffUserController, users.role.update, send_welcome_email при create',
        'parents-and-family-cabinet'  => 'Родители и семейный кабинет: parents, parent_id, переключение детей, оплата месяца активного ученика (FamilyPaymentPayerResolver), добавление группы (account.user.team.update), active_student',
        'dashboard-cabinet'           => 'Консоль (/cabinet): блоки оплат по cabinetPackages.* / cabinetSeasons / customPayments, селект группы фильтрует сезоны и абонементы по team_id, добавление группы в ЛК (account.user.team.update)',
        'setting-prices-custom-payments' => 'Установка цен → Дополнительные платежи: team_id, customPayments.view (не в базовых ролях), manualPaid.manage',
        'setting-prices-monthly-users' => 'Установка цен: бывшие участники + персональная скидка ученика + sync users_prices↔ULP (billing_month, кнопка «+» в журнале)',
        'setting-prices-payment-notifications' => 'Установка цен → Уведомления: конструктор правил email по users_prices, setPrices.paymentNotifications.manage, cron 10:00 MSK',
        'set-prices-package-assignments' => 'Право setPrices.packageAssignments.view: вкладка назначений в админке, не в базовых ролях',
        'set-prices-cabinet-packages' => 'Права setPrices.cabinetPackages.*: отображение типов абонементов на консоли, не в базовых ролях',
        'student-team-membership'     => 'Ученик ↔ группы (M:N team_user): pivot, отчёты, ЛК read-only + attach из сайдбара',
        'admin-users'                 => 'Ученики (админка): /admin/users только role=user, «Показать N» (page_length), телефон родителя в таблице, адрес проживания (users.address → {{child_address}}), ФИО ученика в родительном (users.full_name_genitive → {{child_full_name_genitive}}), импорт Excel, родители, договор, welcome-письмо, пол, комментарий, персональная скидка % (users.discount.manage), team_ids',
        'contracts'                   => 'Договоры (клиентские): PDF и режим «форма клиенту», блок без юрлица группы, карточка, revoke/refund, вкладка «Шаблоны»',
        'contract-templates'          => 'Шаблоны DOCX: fields_schema, {{contract_date}} / системные поля, разрывы Word w:t, «Юр. лицо», email, версии',
        'account-contract-fill'       => 'Заполнение договора родителем: fill/generate/sign, каунтер активных договоров в сайдбаре и на вкладке «Мои документы», авто {{contract_date}}, юрлицо группы',
        'account-partner-organization' => 'Организация партнёра: ЛК и админка, метрики списка /admin/partners (короткие заголовки, оборот и комиссия платформы по снимку выплаты), PartnerLegacyLegalFields, оплаты без fallback на partners',
        'school-leads-widget'         => 'Заявки с сайта: виджет iframe, CRM (порядок колонок ребёнок→родитель→статус→объект→секция→телефон, «Показать N» / page_length), лид→клиент (welcome-письмо), статус после клиента, договор, статусы, Telegram',
        'school-leads-landing'        => 'Страница заявки партнёра: /lead/{landingSlug}, каскад район→объект→услуга, district_id',
        'blog'                        => 'Блог: /blog, админка (blog.view), ИИ, VK (kidscrm): анонс ИИ, очередь default',
        'admin-legal-entities'        => 'Юр. лица: /admin/legal-entities, bank_corr_account, sm-register, displayTitle, LegalEntityResolver, плейсхолдеры договоров',
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
     * Главная `/docs/documentation` (`/doc`) — файл `docs/documentation/index.html`
     * (анонсы фич + список разделов). Отдельные страницы — `/{slug}`.
     *
     * ВАЖНО: без произвольных путей (защита от path traversal).
     */
    public function index(): Response
    {
        $path = base_path('docs/documentation/index.html');
        if (is_file($path)) {
            return response((string) file_get_contents($path), 200)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        // Fallback, если index.html отсутствует: список страниц из PAGE_TITLES.
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
