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
        'payments'                    => 'Оплаты: payables/intents/payments/users_prices, снимок payments.team_id, multi-team витрина, семейная оплата activeStudent (FamilyPaymentPayerResolver), публичная СБП-ссылка месяца /pm/{code}',
        'partner-scope-guide'         => 'Как работать с partner_id: обычный админ и страницы superadmin',
        'partner-context'             => 'Партнёр‑контекст и SetPartner (current_partner/anti‑leak/блокировки)',
        'partner-wallet'              => 'Кошелёк партнёра: /partner-wallet, изоляция STRICT_CURRENT, история договоров/SMS, @error native POST, wallet_balance_cents, partnerWallet.view',
        'partner-service-payments'    => 'Оплата сервиса: /partner-payment, изоляция STRICT_CURRENT, CreatePartnerServicePaymentRequest, servicePayments.view',
        'partners-permissions'        => 'Партнёры: базовые роли и права; опциональные (users.discount.manage, customPayments, account.user.team.update, …)',
        'settings-roles-custom'       => 'Настройки: кастомные роли и стартовый набор прав (admin из конфига, UI без перезагрузки)',
        'settings-permission-groups'  => 'Матрица прав: группы permissions (permission_groups), аккордеон «Права и роли»',
        'audit-my-logs'               => 'Аудит CRM (my_logs): AuditEvent, AuditLogger, event/level, без legacy type/action в runtime',
        'settings-logs'               => 'Настройки → Логи: вкладка my_logs, фильтры event/level, доступ, SUPERADMIN_ALL_OR_FILTER',
        'settings-payment-systems'    => 'Настройки → Платёжные системы: Robokassa per-partner, глобальный терминал T‑Bank, TbankTerminalConfig, права, миграция',
        'reports-payments'            => 'Отчёт «Платежи»: payments.team_id (колонка/фильтр), «Поля списка», «Показать N» (page_length), права, история T‑Bank; ФИО = payments.user_id (семья — выбранный ребёнок)',
        'reports-admin'               => 'Отчёты (админка): KidsCrmDataTable, columns-settings, «Показать N» (page_length), «Исходящие письма» (модалка, iframe HTML, mailable_class из Mailable / X-Mailable-Class; Laravel 10 сам ключ не ставит; пульт Welcome), фильтры, AJAX/суммы',
        'reusable-ui-partials'        => 'Переиспользование UI: KidsCrmTooltip (непрозрачный ховер) / KidsCrmDataTable (persistPageLength), toolbar, logModal, Select2 (в т.ч. тренеры журнала)',
        'admin-layout-sidebar'        => 'Кабинет: стабильный сайдбар и ширина (layout_wide, scrollbar-gutter в admin2, без OverlayScrollbars, локальный Font Awesome 6.5.1 вместо Kit/CDN)',
        'session-lifetime'            => 'Сессия 30 дней (SESSION_LIFETIME=43200), «Запомнить меня» по умолчанию на /login, HTML 403 гостя без admin2',
        'tbank'                       => 'T‑Bank (мультирасчёты): глобальный терминал, комиссии, автовыплата, карточка платежа (организация, timeline), СБП (QR); публичные /pm и /p без логина — не /tinkoff/qr',
        'tbank-admin-payouts'          => 'T‑Bank: админка выплат (список, колонка «Организация», DataTables, «Показать N» / page_length, карточка, tbank.payouts.manage)',
        'tbank-refunds-payout-cancel'   => 'T‑Bank: возврат в отчёте «Платежи» и отмена отложенной выплаты (tinkoff_payments → tinkoff_payouts)',
        'queues-monitoring'             => 'Очереди в админке: мониторинг, доступы, queue.log, restart worker',
        'tests-standards'             => 'Требования к единообразию Feature‑тестов (партнёр/авторизация/права, лог последнего PHPUnit в storage/logs)',
        'dev-seed-data'               => 'Dev-фикстуры: SEED_DEV_DATA, цепочка Dev*-сидеров, юр. лица, T‑Bank, ограничения prod',
        'lesson-packages'             => 'Абонементы: шаблоны, назначения, короткая ссылка /p/{code} и SMS на 1 сегмент (70 ₽ с баланса; причина ошибки sms.ru в модалке), автопролонгация fixed (новый цикл = текущий % скидки × цена шаблона), billing_month / период, ends_at, конфликт fixed, статусы, Excel',
        'postpay'                     => 'Постоплата (postpay): шаблон, users_prices без ULP, скидка по снимку строки, журнал create_postpay, лок после оплаты; право lessonPackages.type.postpay',
        'lesson-packages-type-postpay' => 'Право lessonPackages.type.postpay: выбор типа «Постоплата», скрытое, не в базовых ролях',
        'school-schedule-calendar'    => 'Расписание школы: календарь, разовое fee_amount_default = каталог × % карточки, assign-fixed конфликт, inline-панели, выгрузка Excel, JSON/API',
        'location-team-bindings'      => 'Объекты ↔ группы (teams.location_id): одна группа — один объект, sync, лендинг, слоты, отчёты',
        'directories-hierarchy'       => 'Справочники: иерархия Район → Объект → Группа; вкладки (+ Абонементы), DirectoriesMenu, права, БД, лендинг',
        'admin-districts'             => 'Районы (админка): /admin/districts — districts.view, CRUD, hard delete, вкладка в «Справочники»',
        'admin-locations'             => 'Объекты (админка): /admin/locations — view/manage, district_id, team_ids, вкладка в «Справочники»',
        'admin-sport-types'           => 'Виды спорта (админка): /admin/sport-types — sport_types.view/manage, вкладка в «Справочники», teams.sport_type_id, лендинг',
        'admin-teams'                 => 'Группы (админка): /admin/teams — groups.view, вкладка в «Справочники», create/edit/delete без success-модалки (toast #kidsMainToast + DataTables ajax.reload), month_price_cents, объекты, тренер, расписание',
        'schedule-journal'            => 'Журнал /schedule: пагинация 100 и поиск q, раскладка fixed/flexible, колонка оплаты месяца по группам (users_prices / effective_is_paid), разовое (create_new — сумма и снимок персональной скидки), статусы, мультитренеры при «Посетил» (pivot → ЗП/нагрузка)',
        'schedule-trainer-workload'   => 'Нагрузка тренеров: /schedule/trainer-workload, pivot event_trainers, матрица, AJAX data',
        'schedule-trainer-salary'     => 'ЗП тренеров: схемы classic, kansas и sales (% от продаж), типы тренера, модалка «Настройки месяца», целые средние Канзаса (факт вверх после десятой, база только целое), смена схемы без слепка, *_cents, pivot визита, autosave, слепки',
        'schedule-trainer-salary-sheets' => 'Листы ЗП: архив слепков (readonly), batch/snapshot, latest_only',
        'admin-trainers'              => 'Тренеры (админка): /admin/trainers, trainer_profiles, типы тренера (Канзас), team_trainer, мультитренеры в журнале при «Посетил», форма «Изменить пароль» (display:block из‑за CSS .change-pass-wrap)',
        'admin-users-section'         => 'Раздел «Пользователи» (вкладки): сайдбар/h4 «Пользователи», вкладка «Клиенты» (ученики, label «Клиент»), тренеры, администраторы, /admin/roles/{name}, UsersSectionTabsResolver',
        'admin-role-staff'            => 'Администраторы и кастомные роли: /admin/administrators, /admin/roles/{name}, RoleStaffUserController, users.role.update, send_welcome_email при create',
        'parents-and-family-cabinet'  => 'Родители и семейный кабинет: parents, parent_id, переключение детей, оплата месяца активного ученика (FamilyPaymentPayerResolver), добавление группы (account.user.team.update), колокольчик attach (текст с родителем), свой пароль в ЛК (тот же → 422 под полем), active_student',
        'dashboard-cabinet'           => 'Консоль (/cabinet): блоки оплат по cabinetPackages.* / cabinetSeasons / customPayments, шапки сезонов от текущего учебного года (сент–авг) до 2021–2022, селект группы фильтрует сезоны и абонементы по team_id, добавление группы в ЛК (account.user.team.update), in-app уведомление admin/trainer (текст с родителем / без), системные мониторы (settings.systemMonitors.view, переключатель в шапке, оверлей Reverb, оверлей онлайн по партнёрам, оверлей Пульт — Сегодня/Вчера: оборотка/комиссия/успешные T‑Bank за календарный день Europe/Moscow, leftover «—», ховер last_message 500, ViewException, ring errors.recent, Welcome: mailable_class / тема «Доступ в личный кабинет», не точка чата)',
        'setting-prices-custom-payments' => 'Установка цен → Дополнительные платежи: team_id, customPayments.view (не в базовых ролях; update/delete), manualPaid.manage (только селект статуса)',
        'setting-prices-monthly-users' => 'Установка цен: бывшие участники + персональная скидка ученика + sync users_prices↔ULP (billing_month, кнопка «+» в журнале)',
        'setting-prices-payment-notifications' => 'Установка цен → Уведомления: конструктор правил email по users_prices, {{pay_url}} / автоблок СБП /pm/{code}, setPrices.paymentNotifications.manage, cron 10:00 MSK',
        'in-app-notifications' => 'In-app уведомления CRM: колокольчик (3 последних), лента, ссылки в тексте, рассылка superadmin, автособытие attach группы (admin/trainer школы, текст с родителем / без), автособытие «договор подписан» (только admin школы, 7 суток), inAppNotifications.view/manage',
        'chat' => 'Чат CRM (Сообщения): 1-на-1 и групповые чаты внутри школы, авто-чат учебной группы (threads.team_id), имя группы в списке не «Диалог» (пустое — «Группа», text-align left), бейдж badge-info без вспышки в открытом диалоге, messages.view в базовых ролях, скрытое messages.threads.delete (корзина в шапке, soft-delete, не team_id), Reverb без wsPath без HTTP-опроса inbox, сортировка inbox: непрочитанные затем last_message_id пустые внизу (не updated_at), оверлей статуса процесса/сокета при персональном флаге и settings.systemMonitors.view, оверлей онлайн по партнёрам (не точка чата), оверлей Пульт (Сегодня/Вчера: оборотка/комиссия/успешные T‑Bank за календарный день Europe/Moscow, leftover «—», ховер last_message 500, ViewException, ring errors.recent, Welcome: mailable_class / тема «Доступ в личный кабинет»), онлайн (ping без messages.view), подзаголовок шапки (участники / был(а) в сети), галочки исходящего в списке, карточка собеседника из шапки, название партнёра в модалках Контакт и Группа (не Аккаунт, не шапка), состав группы из шапки (добавить/удалить — admin/superadmin, покинуть — любой, 0 участников soft-delete кроме team_id), черновик на сервере (превью «Черновик:»), смайлы в композере и реакции на сообщения, фильтр контактов по учебной группе, создание группы (название + участники), мобильная нижняя панель с планшета (992px): Личные — !is_group, Чаты — #groupThreads is_group без поиска и сплит-бейджи, inbox JSON смешанный без unread_private, шапка кабинета видна, в диалоге низ скрыт, prefetch истории, зум выключен только на /chat, Vite CSS/JS только на /chat, суперадмин в чате как «Служба поддержки», UNIQUE участника (thread_id, user_id), повтор лички после 0/1 живого не плодит тред',
        'set-prices-package-assignments' => 'Право setPrices.packageAssignments.view: вкладка назначений в админке, не в базовых ролях',
        'set-prices-cabinet-packages' => 'Права setPrices.cabinetPackages.*: отображение типов абонементов на консоли, не в базовых ролях',
        'student-team-membership'     => 'Ученик ↔ группы (M:N team_user): pivot, отчёты, ЛК read-only + attach из сайдбара',
        'admin-users'                 => 'Клиенты (админка): /admin/users вкладка «Клиенты», только role=user (label «Клиент»), create/edit без success-модалки (toast #kidsMainToast + DataTables ajax.reload), «Показать N» (page_length), телефон родителя в таблице, адрес проживания (users.address → {{child_address}}), ФИО ученика в родительном (users.full_name_genitive → {{child_full_name_genitive}}), импорт Excel, родители, договор (создать / черновик / иконка signed + плюс ещё один, встроенная модалка, lockUser), welcome-письмо (mailable_class в журнале; пульт Welcome — только лид→клиент), пол, комментарий, персональная скидка % (users.discount.manage), team_ids, при delete users.email=null',
        'contracts'                   => 'Договоры (клиентские): PDF и режим «форма клиенту», создание со списка клиентов/заявок (lockUser), блок без юрлица группы, ЭП Подпислона с ключа юрлица (не PODPISLON_API_KEY), карточка, revoke/refund, вкладка «Шаблоны», колокольчик админам при signed',
        'contract-templates'          => 'Шаблоны DOCX: fields_schema, {{contract_date}} / системные поля, разрывы Word w:t, «Юр. лицо», email, версии',
        'account-contract-fill'       => 'Заполнение договора родителем: fill/generate/sign, каунтер активных договоров в сайдбаре и на вкладке «Мои документы», авто {{contract_date}}, юрлицо группы, ключ Подпислона юрлица, паспорт (серия и номер), fallback parent_email, ошибки Laravel под полями, колокольчик админам при signed',
        'account-partner-organization' => 'Организация партнёра: ЛК и админка, метрики списка /admin/partners (короткие заголовки, оборот и комиссия платформы по снимку выплаты), PartnerLegacyLegalFields, оплаты без fallback на partners',
        'school-leads-widget'         => 'Заявки с сайта: виджет iframe, CRM (порядок колонок ребёнок→родитель→статус→объект→секция→телефон, «Показать N» / page_length), лид→клиент (welcome-письмо), статус после клиента, договор, статусы, email-уведомления (кнопка «Уведомления», Select2, галочка «не получать»), Telegram',
        'school-leads-landing'        => 'Страница заявки партнёра: /lead/{landingSlug}, инструкция для родителей /instruction и PDF /instruction.pdf, каскад район→объект→услуга, district_id',
        'partner-self-registration'   => 'Саморегистрация школы с лендинга: /partner/register, кнопка «Регистрация» (не ученик), PARTNER_SELF_REGISTRATION_ENABLED, native POST → /cabinet',
        'blog'                        => 'Блог: /blog, админка (blog.view), ИИ, VK (kidscrm): анонс ИИ, очередь default',
        'admin-legal-entities'        => 'Юр. лица: /admin/legal-entities, bank_corr_account, API-ключ Подпислона (только superadmin, не .env), sm-register, displayTitle, LegalEntityResolver, плейсхолдеры договоров',
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
