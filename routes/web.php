<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\PartnerSettingController;
use App\Http\Controllers\Admin\Report\DeptReportController;
use App\Http\Controllers\Admin\Report\LtvReportController;
use App\Http\Controllers\Admin\Report\PaymentReportController;
use App\Http\Controllers\Admin\Report\PaymentRefundController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\Setting\PaymentSystemController;
use App\Http\Controllers\Admin\Setting\RuleController;
use App\Http\Controllers\Admin\Setting\SettingController;
use App\Http\Controllers\Admin\Setting\TbankCommissionsController;
use App\Http\Controllers\Admin\SettingPricesController;
use App\Http\Controllers\Admin\StatusController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Chat\ChatApiController;
use App\Http\Controllers\Chat\ChatPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyGroupController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PayoutsController;
use App\Http\Controllers\SmRegisterController;
use App\Http\Controllers\TinkoffAdminPartnerController;
use App\Http\Controllers\TinkoffAdminPaymentController;
use App\Http\Controllers\TinkoffDealController;
use App\Http\Controllers\TinkoffDebugController;
use App\Http\Controllers\TinkoffPartnerAdminController;
use App\Http\Controllers\TinkoffPaymentController;
use App\Http\Controllers\TinkoffPayoutController;
use App\Http\Controllers\TinkoffQrController;
use App\Http\Controllers\TinkoffWebhookController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\User\Report\ReportController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\LandingPageController;



use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PartnerPaymentController;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Security\PhoneChangeController;
use App\Http\Controllers\ContractsController;
use App\Http\Controllers\Webhooks\PodpislonWebhookController;
use App\Http\Controllers\YooKassaWebhookController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Debug\RequestDebugController;
use App\Http\Middleware\DebugRequestAccess;
use App\Http\Controllers\Admin\Report\PaymentIntentReportController;




Auth::routes();

Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

// Debug endpoint for proxy/IP/header diagnostics.
// Access: either X-Debug-Token header (env DEBUG_REQUEST_TOKEN) OR authenticated + 2FA passed + can:viewing-all-logs.
Route::get('/_debug/request', [RequestDebugController::class, 'show'])
    ->name('debug.request')
    ->middleware([DebugRequestAccess::class, 'throttle:30,1'])
    // не создаём session / XSRF cookies и не тянем "web" middleware, чтобы тест был чище
    ->withoutMiddleware([
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        // кастомные web middleware тоже не нужны для диагностики прокси
        \App\Http\Middleware\SetPartner::class,
        \App\Http\Middleware\SetMenuItems::class,
        \App\Http\Middleware\SetSocialItems::class,
        \App\Http\Middleware\ShareGlobalStats::class,
    ]);


//landing Page
Route::view('/', 'landing.index')->name('landing.home');
Route::view('/crm-dlya-futbolnoy-sekcii', 'landing.seo.football')->name('landing.seo.football');
Route::view('/crm-dlya-tancevalnoy-studii', 'landing.seo.dance')->name('landing.dance');
Route::view('/crm-dlya-shkoly-edinoborstv', 'landing.seo.martial-arts')->name('landing.martial-arts');
Route::view('/crm-dlya-detskogo-razvivayushchego-centra', 'landing.seo.development-centers')->name('landing.seo.development.centers');
Route::view('/crm-dlya-shkol-gimnastiki-i-akrobatiki', 'landing.seo.gymnastics-acrobatics')->name('landing.seo.gymnastics.acrobatics');
Route::view('/crm-dlya-detskih-yazykovyh-shkol', 'landing.seo.language-schools')->name('landing.seo.language.schools');

// Отправка заявки с ленда (feature test +)
Route::post('/contact/send', [LandingPageController::class, 'contactSend'])->name('contact.send');

//Страница Публичная оферта
Route::view('/oferta', 'landing.oferta')->name('oferta');

//Страница Партнёрская оферта
Route::view('/partner/oferta', 'admin.partnerOferta')->name('partnerOferta');

//Страница Политика конфиденциальности
Route::view('/privacy-policy', 'landing.policy')->name('privacy.policy');

//Страница Пользовательское соглашение
Route::get('/terms', [\App\Http\Controllers\AboutController::class, 'terms'])->name('terms');


Route::middleware('auth')->group(function () {
    // 2FA challenge
    Route::get('/two-factor', [TwoFactorController::class, 'showChallenge'])->name('two-factor.challenge');
    Route::post('/two-factor/verify', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::post('/two-factor/resend', [TwoFactorController::class, 'resend'])->name('two-factor.resend');
    Route::get('/two-factor/phone', [TwoFactorController::class, 'phoneForm'])->name('two-factor.phone');
    Route::post('/two-factor/phone', [TwoFactorController::class, 'phoneSave'])->name('two-factor.phone.save');

    // Безопасная смена телефона (двухэтапная)
    Route::get('/security/phone', [PhoneChangeController::class, 'showForm'])->name('security.phone.form');
    Route::post('/security/phone/start', [PhoneChangeController::class, 'start'])->name('security.phone.start');
    Route::post('/security/phone/verify-old', [PhoneChangeController::class, 'verifyOld'])->name('security.phone.verify_old');
    Route::post('/security/phone/verify-new', [PhoneChangeController::class, 'verifyNew'])->name('security.phone.verify_new');
    Route::post('/security/phone/resend-old', [PhoneChangeController::class, 'resendOld'])->name('security.phone.resend_old');
    Route::post('/security/phone/resend-new', [PhoneChangeController::class, 'resendNew'])->name('security.phone.resend_new');
});


// -----------auth', '2fa-----------
Route::middleware(['auth', '2fa'])->group(function () {

    //Консоль (feature test +)
    Route::middleware(['can:dashboard-view'])->group(function () {
        Route::match(['get', 'post'], '/cabinet', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/get-user-details', [DashboardController::class, 'getUserDetails'])->name('getUserDetails');
        Route::get('/get-team-details', [DashboardController::class, 'getTeamDetails'])->name('getTeamDetails');
    });

        //Отчеты -> вкладка Платежи, задолженности, LTV
        Route::middleware(['can:reports-view'])->group(function () {
            //Отчеты -> Платежи
            Route::get('/admin/reports/payments', [PaymentReportController::class, 'payments'])->name('payments');
            Route::get('/admin/reports/getPayments', [PaymentReportController::class, 'getPayments'])->name('payments.getPayments');
            Route::post('/admin/reports/payments/{payment}/refund', [PaymentRefundController::class, 'store'])->name('payments.refund')->whereNumber('payment');
            // Настройки отображения колонок в отчёте "Платежи"
            Route::get('/admin/reports/payments/columns-settings', [PaymentReportController::class, 'getColumnsSettings']);
            Route::post('/admin/reports/payments/columns-settings', [PaymentReportController::class, 'saveColumnsSettings']);
            //Отчеты -> Задолженности
            Route::get('/admin/reports/debts', [DeptReportController::class, 'debts'])->name('debts');
            Route::get('/admin/reports/getDebts', [DeptReportController::class, 'getDebts'])->name('debts.getDebts');
            //Отчеты -> LTV
            Route::get('/admin/reports/ltv', [LtvReportController::class, 'ltv'])->name('ltv');
            Route::get('/admin/reports/getLtv', [LtvReportController::class, 'getLtv'])->name('ltv.getLtv');
        });

    // Отчёты -> "Платежные запросы"
    Route::middleware(['can:reports-payment-intents-view'])->group(function () {
        Route::get('/admin/reports/payment-intents', [PaymentIntentReportController::class, 'paymentIntents'])->name('reports.payment-intents.index');
        Route::get('/admin/reports/getPaymentIntents', [PaymentIntentReportController::class, 'getPaymentIntents'])->name('reports.payment-intents.data');
    });

    //Мои платежи
    Route::middleware(['can:myPayments-view'])->group(function () {
        Route::get('/reports/payments', [ReportController::class, 'showUserPayments'])->name('showUserPayments');
        Route::get('/getUserPayments', [ReportController::class, 'getUserPayments'])->name('payments.getUserPayments');
    });

    //    Моя группа
    Route::middleware(['can:myGroup-view'])->group(function () {
        Route::get('/my-group', [MyGroupController::class, 'index'])->name('my-group.index');
        Route::get('/my-group/data', [MyGroupController::class, 'data'])->name('my-group.data');
    });

    //Установка цен
    Route::middleware('can:setPrices-view')->group(function () {
        Route::get('admin/setting-prices', [SettingPricesController::class, 'index'])->name('admin.settingPrices.indexMenu');
        Route::post('admin/setting-prices/get-team-price', [SettingPricesController::class, 'getTeamPrice'])->name('getTeamPrice');
        Route::post('admin/setting-prices/set-team-price', [SettingPricesController::class, 'setTeamPrice'])->name('setTeamPrice');
        Route::post('admin/setting-prices/set-price-all-teams', [SettingPricesController::class, 'setPriceAllTeams'])->name('setPriceAllTeams');
        Route::post('admin/setting-prices/set-price-all-users', [SettingPricesController::class, 'setPriceAllUsers'])->name('setPriceAllUsers');
        Route::get('admin/setting-prices/logs-data', [SettingPricesController::class, 'getLogsData'])->name('logs.data.settingPrice');
        Route::post('admin/setting-prices/update-date', [SettingPricesController::class, 'updateDate'])->name('updateDate');
    });

    //Журнал расписания
    Route::middleware('can:schedule-view')->group(function () {
        Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
        Route::post('/schedule/update', [ScheduleController::class, 'update'])->name('schedule.update');
        Route::get('/schedule/logs-data', [ScheduleController::class, 'getLogsData'])->name('logs.data.schedule');
        Route::get('/schedule/user-schedule/{user}', [ScheduleController::class, 'getUserScheduleInfo'])->name('user.schedule.info');
        Route::post('/schedule/user/{user}/set-group', [ScheduleController::class, 'setUserGroup'])->name('user.set.group');
        Route::post('/schedule/user/{user}/update-schedule-range', [ScheduleController::class, 'updateUserScheduleRange'])->name('user.update.schedule');
    });

    // Статусы
    Route::middleware('can:schedule-view')->group(function () {
        Route::get('/schedule/statuses', [StatusController::class, 'index'])->name('statuses.index');
        Route::post('/schedule/statuses', [StatusController::class, 'store'])->name('statuses.store');
        Route::patch('/schedule/statuses/{id}', [StatusController::class, 'update'])->name('statuses.update');
        Route::delete('/schedule/statuses/{id}', [StatusController::class, 'destroy'])->name('statuses.destroy');
    });

    //Пользователи
    Route::middleware('can:users-view')->group(function () {
        Route::get('admin/users', [UserController::class, 'index'])->name('admin.user1');
        Route::post('admin/users', [UserController::class, 'store'])->name('admin.user.store');
        Route::get('admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.user.edit');
        Route::patch('admin/users/{user}', [UserController::class, 'update'])->name('admin.user.update');
        Route::delete('admin/user/{user}', [UserController::class, 'delete'])->name('admin.user.delete');
        Route::post('admin/field/store', [UserController::class, 'storeFields'])->name('admin.field.store');
        Route::get('admin/user/logs-data', [UserController::class, 'log'])->name('logs.data.user');
        Route::post('admin/user/{user}/update-password', [UserController::class, 'updatePassword'])->name('admin.user.password.update')->middleware(['can:users-password-update', 'throttle:5,1'])->whereNumber('user');
        //Удаление аватарки админом
        Route::delete('/admin/users/{id}/avatar', [UserController::class, 'destroyUserAvatar'])->name('admin.users.avatar.destroy');
        //Обновление аватарки админом
        Route::post('/admin/users/{id}/avatar', [UserController::class, 'uploadUserAvatar']);
        //Данные для datatables
        Route::get('/admin/users/data', [UserController::class, 'data'])->name('admin.users.data');
        Route::get('admin/users/columns-settings', [UserController::class, 'getColumnsSettings']);
        Route::post('admin/users/columns-settings', [UserController::class, 'saveColumnsSettings']);
    });

    //Группы
    Route::middleware('can:groups-view')->group(function () {
        Route::get('admin/teams', [TeamController::class, 'index'])->name('admin.team1');
        Route::post('admin/teams', [TeamController::class, 'store'])->name('admin.team.store');
        Route::get('admin/team/{id}/edit', [TeamController::class, 'edit'])->name('admin.team.edit');
        Route::patch('admin/team/{id}', [TeamController::class, 'update'])->name('admin.team.update');
        Route::delete('admin/team/{team}', [TeamController::class, 'delete'])->name('admin.team.delete');
        Route::get('admin/teams/logs-data', [TeamController::class, 'log'])->name('logs.data.team');
        // DataTables endpoint
        Route::get('admin/teams/data', [TeamController::class, 'data'])->name('admin.team.data');
        // Настройки отображения колонок
        Route::get('admin/teams/columns-settings', [TeamController::class, 'getColumnsSettings']);
        Route::post('admin/teams/columns-settings', [TeamController::class, 'saveColumnsSettings']);
    });

    //Партнеры
    Route::middleware(['can:partner-view'])->group(function () {
        Route::get('admin/partners', [PartnerController::class, 'index'])->name('admin.partner.index');
        Route::post('admin/partners', [PartnerController::class, 'store'])->name('admin.partner.store');
        Route::get('admin/partner/{partner}/edit', [PartnerController::class, 'edit'])->name('admin.partner.edit');
        Route::patch('admin/partner/{partner}', [PartnerController::class, 'update'])->name('admin.partner.update');
        Route::delete('admin/partner/{partner}', [PartnerController::class, 'destroy'])->name('admin.partner.delete');
        Route::get('/admin/partner/logs-data', [PartnerController::class, 'log'])->name('logs.data.partner');
    });

    //Страница Настойки - Общие
    Route::middleware('can:settings-view')->group(function () {
        Route::get('admin/settings', [SettingController::class, 'showSettings'])->name('admin.setting.setting');
        Route::get('admin/settings/registration-activity', [SettingController::class, 'registrationActivity'])->name('registrationActivity');
        Route::post('admin/settings/text-for-users', [SettingController::class, 'textForUsers'])->name('textForUsers');
        Route::post('settings/save-menu-items', [SettingController::class, 'saveMenuItems'])->name('settings.saveMenuItems');
        Route::post('settings/save-social-menu-items', [SettingController::class, 'saveSocialItems'])->name('settings.saveSocialItems');
        Route::post('admin/settings/force-2fa-admins', [SettingController::class, 'toggleForce2faAdmins'])->name('settings.force2fa.admins');
    });

    // Просмотр всех логов
    Route::middleware('can:viewing-all-logs')->group(function () {
        Route::get('admin/settings/logs-all-data', [SettingController::class, 'logsAllData'])->name('logs.all.data');
    });

    //Страница Настойки- Права
    Route::middleware('can:settings-roles-view')->group(function () {
        Route::get('admin/settings/rules', [RuleController::class, 'showRules'])->name('admin.setting.rule');
        Route::post('admin/setting/rule/toggle', [RuleController::class, 'togglePermission'])->name('admin.setting.rule.toggle');
        Route::get('admin/setting/rules/logs-data', [RuleController::class, 'logRules'])->name('logs.data.rule');
        Route::post('admin/setting/role/create', [RuleController::class, 'createRole'])->name('admin.setting.role.create');
        Route::delete('admin/setting/role/delete', [RuleController::class, 'deleteRole'])->name('admin.setting.role.delete');
    });

    //Страница Настойки - Платежные системы
    Route::middleware('can:settings-paymentSystems-view')->group(function () {
        Route::get('admin/settings/paymentSystem', [PaymentSystemController::class, 'index'])->name('admin.setting.paymentSystem');
        Route::post('payment-systems/store', [PaymentSystemController::class, 'store'])->name('payment-systems.store');
        Route::get('payment-systems/{name}', [PaymentSystemController::class, 'show'])->name('payment-systems.show');
        Route::delete('payment-systems/{payment_system}', [PaymentSystemController::class, 'destroy'])->name('payment-systems.destroy');
    });

    // Внутренняя документация проекта (HTML из /docs/documentation)
    Route::middleware('can:documentations-view')->group(function () {
        Route::get('/docs/documentation', [DocumentationController::class, 'index'])->name('docs.documentation.index');
        Route::get('/docs/documentation/{page}', [DocumentationController::class, 'show'])->whereIn('page', ['payments', 'reports-payments', 'tbank'])->name('docs.documentation.show');
    });

    //Страница Настойки - Комиссии Т-Банк
    Route::middleware('can:settings.commission')->group(function () {
        Route::get('admin/settings/tbank-commissions', [TbankCommissionsController::class, 'index'])->name('admin.setting.tbankCommissions');
        Route::get('admin/settings/tbank-commissions/create', [TbankCommissionsController::class, 'create'])->name('admin.setting.tbankCommissions.create');
        Route::post('admin/settings/tbank-commissions', [TbankCommissionsController::class, 'store'])->name('admin.setting.tbankCommissions.store');
        Route::get('admin/settings/tbank-commissions/{id}/edit', [TbankCommissionsController::class, 'edit'])->name('admin.setting.tbankCommissions.edit');
        Route::put('admin/settings/tbank-commissions/{id}', [TbankCommissionsController::class, 'update'])->name('admin.setting.tbankCommissions.update');
        Route::delete('admin/settings/tbank-commissions/{id}', [TbankCommissionsController::class, 'destroy'])->name('admin.setting.tbankCommissions.destroy');
    });

    //Учетная запись - вкладка юзер
    Route::middleware('can:account-user-view')->group(function () {
        Route::get('account-settings/users/{user}/edit', [AccountController::class, 'user'])->name('admin.cur.user.edit');
        Route::patch('account-settings/users/{user}', [AccountController::class, 'update'])->name('account.user.update');
        Route::post('user/update-password', [AccountController::class, 'updatePassword']);
        Route::post('/profile/avatar', [AccountController::class, 'store']);        // добалвение/замена
        Route::delete('/profile/avatar', [AccountController::class, 'destroy']);    // удаление

    });

    //Учетная запись - вкладка "организация"
    Route::middleware('can:account-partner-view')->group(function () {
        Route::get('account-settings/partner/{user}/edit', [PartnerSettingController::class, 'partner'])->name('admin.cur.company.edit');
        Route::patch('account-settings/partner/{partner}', [PartnerSettingController::class, 'updatePartner'])->name('admin.cur.partner.update');
    });

    //Учетная запись - вкладка "Мои договоры"
    Route::middleware('can:account-documents-view')->group(function () {
        Route::get('account-settings/documents', [ContractsController::class, 'myDocuments']);
        Route::get('account-settings/documents/contracts/{contract}/requests', [ContractsController::class, 'myDocumentRequests']);
        Route::get('/contracts/{contract}/download-original', [ContractsController::class, 'downloadOriginal'])->name('contracts.downloadOriginal');
        Route::get('/contracts/{contract}/download-signed', [ContractsController::class, 'downloadSigned'])->name('contracts.downloadSigned');
    });

    //Лиды
    // Route::middleware('can:leads-view')->group(function () {
    //     Route::get('/leads', [\App\Http\Controllers\LandingPageController::class, 'submission'])->name('landing.submissions');
    //     Route::get('/admin/leads/data', [\App\Http\Controllers\LandingPageController::class, 'leadsDataTable'])->name('admin.leads.data');
    // });


    
    Route::middleware('can:leads-view')->group(function () {
        Route::get('/leads', [\App\Http\Controllers\LandingPageController::class, 'submission'])->name('landing.submissions');
        // DataTables endpoint
        Route::get('/admin/leads/data', [LandingPageController::class, 'leadsDataTable'])->name('admin.leads.data');
        // Обновление статуса/комментария (AJAX)
        Route::put('/admin/leads/{submission}', [LandingPageController::class, 'updateLead'])->name('admin.leads.update');
        // Soft delete (AJAX)
        Route::delete('/admin/leads/{submission}', [LandingPageController::class, 'destroyLead'])->name('admin.leads.destroy');
    });


    //Страница оплаты сервиса
    Route::middleware('can:servicePayments-view')->group(function () {
        Route::get('partner-payment/recharge', [PartnerPaymentController::class, 'showRecharge'])->name('partner.payment.recharge');
        Route::get('partner-payment/history', [PartnerPaymentController::class, 'showHistory'])->name('partner.payment.history');
        Route::get('partner-payment/data', [PartnerPaymentController::class, 'getPaymentsData'])->name('partner.payment.data');
        Route::post('payment/service/yookassa', [PartnerPaymentController::class, 'createPaymentYookassa'])->name('createPaymentYookassa');
    });

    //Страница О сервисе
    Route::get('/about', [\App\Http\Controllers\AboutController::class, 'index'])->name('about');

    //Страница оплаты робокассы
    Route::middleware('can:paying-classes')->group(function () {
        Route::post('payment', [TransactionController::class, 'index'])->name('payment');
        Route::post('payment/pay', [TransactionController::class, 'pay'])->name('payment.pay');
    });

    //Страницы результатов оплат
    Route::get('payment/success', [TransactionController::class, 'success'])->name('payment.success');
    Route::get('payment/fail', [TransactionController::class, 'fail'])->name('payment.fail');

    //Оплата клубного взноса (робокасса)
    Route::middleware('can:payment-clubfee')->group(function () {
        Route::get('/payment/club-fee', [\App\Http\Controllers\TransactionController::class, 'clubFee'])->name('clubFee');
        Route::post('/payment/club-fee', [\App\Http\Controllers\TransactionController::class, 'clubFee'])->name('clubFee');
    });

    //Договоры
    Route::middleware('can:contracts-view')->group(function () {

        // AJAX для Select2 (поиск учеников текущего партнёра)
        Route::get('/client-contracts/users-search', [ContractsController::class, 'usersSearch'])->name('contracts.users.search');

        // AJAX для получения групп ученика
        Route::get('/client-contracts/user-group', [ContractsController::class, 'userGroup'])->name('contracts.user.group');

        // >>> СНАЧАЛА: спец-урлы для таблицы (БЕЗ параметров) <<<

        Route::get('/client-contracts/data', [ContractsController::class, 'data'])->name('contracts.data');
        Route::get('/client-contracts/columns-settings', [ContractsController::class, 'getColumnsSettings'])->name('contracts.columns-settings.get');
        Route::post('/client-contracts/columns-settings', [ContractsController::class, 'saveColumnsSettings'])->name('contracts.columns-settings.save');

        // >>> ПОТОМ обычные CRUD-роуты без параметров <<<

        Route::get('/client-contracts', [ContractsController::class, 'index'])->name('contracts.index');
        Route::get('/client-contracts/create', [ContractsController::class, 'create'])->name('contracts.create');
        Route::post('/client-contracts', [ContractsController::class, 'store'])
            ->name('contracts.store');

        Route::get('/client-contracts/{contract}', [ContractsController::class, 'show'])->name('contracts.show');
        Route::post('/client-contracts/{contract}/send', [ContractsController::class, 'send'])->name('contracts.send');
        Route::post('/client-contracts/{contract}/resend', [ContractsController::class, 'resend'])->name('contracts.resend');
        Route::post('/client-contracts/{contract}/revoke', [ContractsController::class, 'revoke'])->name('contracts.revoke');
        Route::get('/client-contracts/{contract}/status', [ContractsController::class, 'status'])->name('contracts.status');
        Route::post('/client-contracts/{contract}/send-email', [ContractsController::class, 'sendEmail'])->name('contracts.sendEmail');
        Route::post('/client-contracts/check-balance', [ContractsController::class, 'checkBalance']);
        Route::get('/client-contracts/{contract}/download-original', [ContractsController::class, 'downloadOriginal'])->name('contracts.downloadOriginal');
        Route::get('/client-contracts/{contract}/download-signed', [ContractsController::class, 'downloadSigned'])->name('contracts.downloadSigned');
    });

    //Сообщения (ЧАТ)
    Route::middleware('can:messages-view')->group(function () {
        // Страница чата
        Route::get('/chat', [ChatPageController::class, 'index'])->name('chat.index');
        // API для фронта (ПРЯМЫЕ URL)
        Route::get('/chat/api/threads', [ChatApiController::class, 'threads']);
        Route::get('/chat/api/threads/{thread}', [ChatApiController::class, 'thread'])->whereNumber('thread');
        Route::get('/chat/api/threads/{thread}/messages', [ChatApiController::class, 'messages'])->whereNumber('thread');
        // ВАЖНО: отправка сообщения → storeMessage (а не storeThread)
        Route::post('/chat/api/threads/{thread}/messages', [ChatApiController::class, 'storeMessage'])->whereNumber('thread');
        // Создание 1-на-1 или группы
        Route::post('/chat/api/threads', [ChatApiController::class, 'storeThread']);
        // Живой поиск пользователей для модалок
        Route::get('/chat/api/users', [ChatApiController::class, 'users']);
        Route::get('/chat/api/threads/{thread}/members', [ChatApiController::class, 'members']);
        Route::post('/chat/api/threads/{thread}/members', [ChatApiController::class, 'addMembers']);
        Route::post('/chat/api/threads/{thread}/typing', [ChatApiController::class, 'typing']);
        Route::patch('/chat/api/threads/{thread}/read', [ChatApiController::class, 'markRead']);
    });

    //Кошелек партнера
    Route::middleware('can:partnerWallet-view')->group(function () {
        Route::get('/partner-wallet', [PartnerPaymentController::class, 'showWallet'])->name('partner.wallet');
        // Создать платёж на пополнение кошелька
        Route::post('/partner-wallet/topup', [PartnerPaymentController::class, 'createWalletTopupYookassa'])->name('partner.wallet.topup');
        // История транзакций кошелька (DataTables)
        Route::get('/partner-wallet/transactions', [PartnerPaymentController::class, 'getWalletTransactionsData'])->name('partner.wallet.transactions');
        // Возврат после оплаты (YooKassa redirect) — просто страница "обрабатывается"
        Route::get('/partner-wallet/success', [PartnerPaymentController::class, 'ykWalletSuccess'])->name('partner.wallet.success');
    });

    //Тинькоф эквайринг мультирасчеты
    Route::middleware('can:payment-method-T-Bank')->group(function () {
        Route::post('/tinkoff/payouts/{deal}/pay-now', [TinkoffPayoutController::class, 'payNow']);
        Route::post('/tinkoff/payouts/{deal}/delay', [TinkoffPayoutController::class, 'delay']);
        Route::post('/tinkoff/deals/{deal}/close', [TinkoffDealController::class, 'close']);
        // Карточки (admin-only)
        Route::get('/admin/tinkoff/payments/{id}', [TinkoffAdminPaymentController::class, 'show']);
        Route::get('/admin/tinkoff/partners/{id}', [TinkoffAdminPartnerController::class, 'show']);
        Route::post('/payments/tinkoff/create', [TinkoffPaymentController::class, 'create'])->name('payment.tinkoff.pay');
        Route::post('/payments/tinkoff/sbp', [TinkoffPaymentController::class, 'createSbp'])->name('payment.tinkoff.sbp');
        Route::get('/tinkoff/debug/state/{paymentId}', [TinkoffDebugController::class, 'state'])->middleware('auth'); // только под админа, если надо
        Route::get('/tinkoff/debug/tpay-status', [TinkoffDebugController::class, 'tpayStatus']);
        Route::post('/payments/tinkoff/qr-init', [TinkoffQrController::class, 'init'])->name('payment.tinkoff.qrInit');
        Route::get('/tinkoff/qr/{paymentId}', [TinkoffQrController::class, 'show'])->name('tinkoff.qr');
        Route::get('/tinkoff/qr/{paymentId}/json', [TinkoffQrController::class, 'getQr']);
        Route::get('/tinkoff/qr/{paymentId}/state', [TinkoffQrController::class, 'state'])->name('tinkoff.qr.state');
        //Список платежей
        Route::get('/admin/tinkoff/payments', [TinkoffAdminPaymentController::class, 'index']);
        Route::get('/admin/tinkoff/payments/{id}', [TinkoffAdminPaymentController::class, 'show']);
        Route::post('/tinkoff/debug/verify-token', [TinkoffDebugController::class, 'verifyToken']);
        // регистрация в sm-register (создание PartnerId)
        Route::post('/admin/tinkoff/partners/{id}/sm-register', [TinkoffAdminPartnerController::class, 'smRegister'])
            ->name('tinkoff.partners.smRegister');
        Route::post('/admin/tinkoff/partners/{id}/sm-patch', [TinkoffAdminPartnerController::class, 'smPatch'])
            ->name('tinkoff.partners.smPatch');
        Route::post('/admin/tinkoff/partners/{id}/sm-refresh', [TinkoffAdminPartnerController::class, 'smRefresh'])
            ->name('tinkoff.partners.smRefresh');
        //routes/web.php
        Route::post('/admin/tinkoff/partners/{id}/sm-pull', [TinkoffAdminPartnerController::class, 'smPull'])
            ->name('tinkoff.partners.smPull');
    });

    Route::post('/account/user/{user}/phone/send-code', [\App\Http\Controllers\Admin\AccountController::class, 'phoneSendCode'])->name('account.user.phoneSendCode');
    Route::post('/account/user/{user}/phone/confirm-code', [\App\Http\Controllers\Admin\AccountController::class, 'phoneConfirmCode'])->name('account.user.phoneConfirmCode');

    //Подтверждение оферты
    Route::post('/partner/accept-offer', [\App\Http\Controllers\PartnerOfferController::class, 'acceptOffer'])->name('partner.accept-offer');

    //переключение между партнерами
    Route::middleware(['can:partner.view'])->prefix('admin')->group(function () {
        Route::post('/switch-partner', [\App\Http\Controllers\PartnerSwitchController::class, 'switch'])->name('partner.switch');
    });

    //2FA
    Route::middleware(['can:admin'])->prefix('admin')->group(function () {
        Route::get('2fa', [TwoFactorController::class, 'show'])->name('admin.2fa.show');
        Route::post('2fa/enable', [TwoFactorController::class, 'enable'])->name('admin.2fa.enable');
        Route::post('2fa/verify', [TwoFactorController::class, 'verify'])->name('admin.2fa.verify');
        Route::post('2fa/disable', [TwoFactorController::class, 'disable'])->name('admin.2fa.disable');
    });
});

Route::post('password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('password.update');


//                              --- ВЕБХУКИ ---
// Robokassa
Route::get('/payment/result', [\App\Http\Controllers\RobokassaController::class, 'result'])->name('payment.result');

// Yookassa абон. плата
//Route::post('/webhook/yookassa', [\App\Http\Controllers\WebhookController::class, 'handleWebhook'])->name('webhook.yookassa');
// YooKassa webhook пополнение кошелька (без CSRF)
Route::post('/partner-wallet/webhook', [PartnerPaymentController::class, 'ykWalletWebhook'])->name('partner.wallet.webhook');
// YooKassa webhook единый (без CSRF)
Route::post('/webhook/yookassa', [YooKassaWebhookController::class, 'handle']);

// Podpislon
Route::post('/webhooks/podpislon', [PodpislonWebhookController::class, 'handle'])->withoutMiddleware([VerifyCsrfToken::class])->name('webhooks.podpislon');

//Тиньков мультирасчеты
Route::get('/payments/tinkoff/{order}/success', [TinkoffPaymentController::class, 'success']);
Route::get('/payments/tinkoff/{order}/fail', [TinkoffPaymentController::class, 'fail']);
Route::post('/webhooks/tinkoff/payments', [TinkoffWebhookController::class, 'payments']);
