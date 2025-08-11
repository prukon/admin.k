<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PartnerPaymentController;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Security\PhoneChangeController;


Auth::routes();

//landing Page
Route::get('/', [\App\Http\Controllers\LandingPageController::class, 'index'])->name('landing.home');
Route::post('/contact', [\App\Http\Controllers\LandingPageController::class, 'contactSend'])->name('contact.send');
Route::view('/oferta', 'landing.oferta')->name('oferta');
Route::view('/partner/oferta', 'admin.partnerOferta')->name('partnerOferta');
Route::view('/privacy-policy', 'landing.policy')->name('privacy.policy');


// routes/web.php


//Route::middleware('auth')->group(function () {
//    Route::get('/two-factor', [TwoFactorController::class, 'showChallenge'])->name('two-factor.challenge');
//    Route::post('/two-factor/verify', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
//    Route::post('/two-factor/resend', [TwoFactorController::class, 'resend'])->name('two-factor.resend');
//
//    // НОВОЕ: страница ввода телефона для 2FA
//    Route::get('/two-factor/phone', [TwoFactorController::class, 'phoneForm'])->name('two-factor.phone');
//    Route::post('/two-factor/phone', [TwoFactorController::class, 'phoneSave'])->name('two-factor.phone.save');
//
//
//    // форма смены телефона
//    Route::get('/security/phone', [PhoneChangeController::class, 'showForm'])->name('security.phone.form');
//    // шаг 1: запрос смены (пароль + новый номер)
//    Route::post('/security/phone/request', [PhoneChangeController::class, 'requestChange'])->name('security.phone.request');
//    // шаг 2: подтверждение кода (на новый номер)
//    Route::post('/security/phone/verify', [PhoneChangeController::class, 'verify'])->name('security.phone.verify');
//    // повторная отправка кода (для смены номера)
//    Route::post('/security/phone/resend', [PhoneChangeController::class, 'resend'])->name('security.phone.resend');
//
//
//    Route::post('/security/phone/start', [PhoneChangeController::class, 'start'])->name('security.phone.start');
//    Route::post('/security/phone/verify-old', [PhoneChangeController::class, 'verifyOld'])->name('security.phone.verify_old');
//    Route::post('/security/phone/verify-new', [PhoneChangeController::class, 'verifyNew'])->name('security.phone.verify_new');
//
//    Route::post('/security/phone/resend-old', [PhoneChangeController::class, 'resendOld'])->name('security.phone.resend_old');
//    Route::post('/security/phone/resend-new', [PhoneChangeController::class, 'resendNew'])->name('security.phone.resend_new');

//});



    Route::middleware('auth')->group(function () {
        // 2FA challenge
        Route::get('/two-factor',        [TwoFactorController::class, 'showChallenge'])->name('two-factor.challenge');
        Route::post('/two-factor/verify',[TwoFactorController::class, 'verify'])->name('two-factor.verify');
        Route::post('/two-factor/resend',[TwoFactorController::class, 'resend'])->name('two-factor.resend');

        // Безопасная смена телефона (двухэтапная)
        Route::get('/security/phone',            [PhoneChangeController::class, 'showForm'])->name('security.phone.form');
        Route::post('/security/phone/start',     [PhoneChangeController::class, 'start'])->name('security.phone.start');
        Route::post('/security/phone/verify-old',[PhoneChangeController::class, 'verifyOld'])->name('security.phone.verify_old');
        Route::post('/security/phone/verify-new',[PhoneChangeController::class, 'verifyNew'])->name('security.phone.verify_new');
        Route::post('/security/phone/resend-old',[PhoneChangeController::class, 'resendOld'])->name('security.phone.resend_old');
        Route::post('/security/phone/resend-new',[PhoneChangeController::class, 'resendNew'])->name('security.phone.resend_new');
    });

Route::middleware(['auth', '2fa'])->group(function () {

//Route::group(['namespace' => 'Auth', 'middleware' => 'auth'], function () {

    // Route: web.php


    Route::post('/partner/accept-offer', [\App\Http\Controllers\PartnerOfferController::class, 'acceptOffer'])->name('partner.accept-offer');

    //    Заявки
    Route::get('/submissions', [\App\Http\Controllers\LandingPageController::class, 'submission'])->name('landing.submissions');

    //Главная
    Route::match(['get', 'post'], '/cabinet', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/get-user-details', [\App\Http\Controllers\DashboardController::class, 'getUserDetails'])->name('getUserDetails');
    Route::get('/get-team-details', [\App\Http\Controllers\DashboardController::class, 'getTeamDetails'])->name('getTeamDetails');
    Route::get('/setup-btn', [\App\Http\Controllers\DashboardController::class, 'setupBtn'])->name('setupBtn');
    Route::get('/content-menu-calendar', [\App\Http\Controllers\DashboardController::class, 'contentMenuCalendar'])->name('contentMenuCalendar');

    //Группы
    Route::get('admin/teams', [\App\Http\Controllers\Admin\TeamController::class, 'index'])->name('admin.team1')->middleware('can:manage-groups');
    Route::post('admin/teams', [\App\Http\Controllers\Admin\TeamController::class, 'store'])->name('admin.team.store')->middleware('can:manage-groups');
    Route::get('/admin/team/{id}/edit', [\App\Http\Controllers\Admin\TeamController::class, 'edit'])->name('admin.team.edit')->middleware('can:manage-groups');
    Route::patch('/admin/team/{id}', [\App\Http\Controllers\Admin\TeamController::class, 'update'])->name('admin.team.update')->middleware('can:manage-groups');
    Route::delete('admin/team/{team}', [\App\Http\Controllers\Admin\TeamController::class, 'delete'])->name('admin.team.delete')->middleware('can:manage-groups');
    Route::get('/admin/teams/logs-data', [\App\Http\Controllers\Admin\TeamController::class, 'log'])->name('logs.data.team')->middleware('can:manage-groups');

    //Партнеры
//    Route::get('admin/partners', [\App\Http\Controllers\Admin\PartnerController::class, 'index'])->name('admin.partner')->middleware('can:manage-partners');
//    Route::post('admin/partners', [\App\Http\Controllers\Admin\PartnerController::class, 'store'])->name('admin.partner.store')->middleware('can:manage-partners');
//    Route::get('/admin/partners/{partner}/edit', [\App\Http\Controllers\Admin\PartnerController::class, 'edit'])->name('admin.partner.edit')->middleware('can:manage-partners');
//    Route::patch('/admin/partners/{partner}', [\App\Http\Controllers\Admin\PartnerController::class, 'update'])->name('admin.partner.update')->middleware('can:manage-partners');
//    Route::delete('admin/partners/{partner}', [\App\Http\Controllers\Admin\PartnerController::class, 'delete'])->name('admin.partner.delete')->middleware('can:manage-partners');
//    Route::get('/admin/partners/logs-data', [\App\Http\Controllers\Admin\PartnerController::class, 'log'])->name('logs.data.partner')->middleware('can:manage-partners');


    // Права: can:manage-partners
    Route::middleware(['can:manage-partners'])->group(function () {
        Route::get('admin/partners', [PartnerController::class, 'index'])->name('admin.partner.index');
        Route::post('admin/partners', [PartnerController::class, 'store'])->name('admin.partner.store');
        Route::get('admin/partner/{partner}/edit', [PartnerController::class, 'edit'])->name('admin.partner.edit');
        Route::patch('admin/partner/{partner}', [PartnerController::class, 'update'])->name('admin.partner.update');
        Route::delete('admin/partner/{partner}', [PartnerController::class, 'destroy'])->name('admin.partner.delete');
        Route::get('/admin/partner/logs-data', [PartnerController::class, 'log'])->name('logs.data.partner');

    });


//    Route::prefix('admin')->middleware(['auth','can:manage-partners'])->group(function () {
//        // Отображение страницы редактирования (возвращает view с модалкой)
//        Route::get('partner/{partner}/edit', [PartnerController::class, 'edit'])
//            ->name('admin.partner.edit');
//
//        // Получение данных партнёра в формате JSON (для AJAX)
//        Route::get('partner/{partner}', [PartnerController::class, 'show'])
//            ->name('admin.partner.show');
//
//        // Обновление партнёра
//        Route::patch('partner/{partner}', [PartnerController::class, 'update'])
//            ->name('admin.partner.update');
//
//        // Создание партнёра (JSON)
//        Route::post('partner', [PartnerController::class, 'store'])
//            ->name('admin.partner.store');
//    });

//    Route::get('admin/partner/{id}/edit', [\App\Http\Controllers\Admin\PartnerController::class, 'edit'])
//        ->name('admin.partner.edit')
//        ->middleware(['can:manage-partners']);


    //Пользователи
    Route::get('admin/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.user1')->middleware('can:manage-users');
    Route::get('admin/users/create', [\App\Http\Controllers\Admin\UserController::class, 'create'])->name('admin.user.create')->middleware('can:manage-users');
    Route::post('admin/users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.user.store')->middleware('can:manage-users');
    Route::get('admin/users/{user}/edit', [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('admin.user.edit')->middleware('can:manage-users');
    Route::patch('admin/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.user.update')->middleware('can:manage-users');
    Route::delete('/admin/user/{user}', [\App\Http\Controllers\Admin\UserController::class, 'delete'])->name('admin.user.delete')->middleware('can:manage-users');
    Route::post('/admin/field/store', [\App\Http\Controllers\Admin\UserController::class, 'storeFields'])->name('admin.field.store')->middleware('can:manage-users');
    Route::get('/admin/user/logs-data', [\App\Http\Controllers\Admin\UserController::class, 'log'])->name('logs.data.user')->middleware('can:manage-users');
    Route::post('/admin/user/{id}/update-password', [\App\Http\Controllers\Admin\UserController::class, 'updatePassword'])->middleware('can:manage-users');

    //Отчеты
    Route::get('/admin/reports/payments', [\App\Http\Controllers\Admin\Report\PaymentReportController::class, 'payments'])->name('payments')->middleware('can:reports');
    Route::get('/admin/reports/getPayments', [\App\Http\Controllers\Admin\Report\PaymentReportController::class, 'getPayments'])->name('payments.getPayments')->middleware('can:reports');
    Route::get('/admin/reports/debts', [\App\Http\Controllers\Admin\Report\DeptReportController::class, 'debts'])->name('debts')->middleware('can:reports');
    Route::get('/admin/reports/getDebts', [\App\Http\Controllers\Admin\Report\DeptReportController::class, 'getDebts'])->name('debts.getDebts')->middleware('can:reports');
    Route::get('/admin/reports/ltv', [\App\Http\Controllers\Admin\Report\LtvReportController::class, 'ltv'])->name('ltv')->middleware('can:reports');
    Route::get('/admin/reports/getLtv', [\App\Http\Controllers\Admin\Report\LtvReportController::class, 'getLtv'])->name('ltv.getLtv')->middleware('can:reports');

    //Отчеты юзера
    Route::get('/reports/payments', [\App\Http\Controllers\User\Report\ReportController::class, 'showUserPayments'])->name('showUserPayments')->middleware('can:my-payments');
    Route::get('/getUserPayments', [\App\Http\Controllers\User\Report\ReportController::class, 'getUserPayments'])->name('payments.getUserPayments')->middleware('can:my-payments');

    //Установка цен
    Route::get('admin/setting-prices', [\App\Http\Controllers\Admin\SettingPricesController::class, 'index'])->name('admin.settingPrices.indexMenu')->middleware('can:set-prices');
    Route::post('admin/setting-prices/get-team-price', [\App\Http\Controllers\Admin\SettingPricesController::class, 'getTeamPrice'])->name('getTeamPrice')->middleware('can:set-prices');
    Route::post('admin/setting-prices/set-team-price', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setTeamPrice'])->name('setTeamPrice')->middleware('can:set-prices');
    Route::post('admin/setting-prices/set-price-all-teams', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setPriceAllTeams'])->name('setPriceAllTeams')->middleware('can:set-prices');
    Route::post('admin/setting-prices/set-price-all-users', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setPriceAllUsers'])->name('setPriceAllUsers')->middleware('can:set-prices');
    Route::get('admin/setting-prices/logs-data', [\App\Http\Controllers\Admin\SettingPricesController::class, 'getLogsData'])->name('logs.data.settingPrice');
    Route::post('admin/setting-prices/update-date', [\App\Http\Controllers\Admin\SettingPricesController::class, 'updateDate'])->name('updateDate');

    //Страница Настойки
    Route::get('/admin/settings', [\App\Http\Controllers\Admin\Setting\SettingController::class, 'showSettings'])->name('admin.setting.setting')->middleware('can:general-settings');
    Route::get('/admin/settings/registration-activity', [\App\Http\Controllers\Admin\Setting\SettingController::class, 'registrationActivity'])->name('registrationActivity')->middleware('can:general-settings');
    Route::post('/admin/settings/text-for-users', [\App\Http\Controllers\Admin\Setting\SettingController::class, 'textForUsers'])->name('textForUsers')->middleware('can:general-settings');
    Route::post('/settings/save-menu-items', [\App\Http\Controllers\Admin\Setting\SettingController::class, 'saveMenuItems'])->name('settings.saveMenuItems')->middleware('can:general-settings');
    Route::post('/settings/save-social-menu-items', [\App\Http\Controllers\Admin\Setting\SettingController::class, 'saveSocialItems'])->name('settings.saveSocialItems')->middleware('can:general-settings');
    Route::get('/edit-menu', [\App\Http\Controllers\Admin\Setting\SettingController::class, 'editMenu'])->name('editMenu');
    Route::get('/admin/settings/logs-all-data', [\App\Http\Controllers\Admin\Setting\SettingController::class, 'logsAllData'])->name('logs.all.data');
    Route::post('/admin/settings/force-2fa-admins', [\App\Http\Controllers\Admin\Setting\SettingController::class, 'toggleForce2faAdmins'])->name('settings.force2fa.admins');





    //Страница Настойки- Права
    Route::get('/admin/settings/rules', [\App\Http\Controllers\Admin\Setting\RuleController::class, 'showRules'])->name('admin.setting.rule')->middleware('can:manage-roles');
    Route::post('/admin/setting/rule/toggle', [\App\Http\Controllers\Admin\Setting\RuleController::class, 'togglePermission'])->name('admin.setting.rule.toggle')->middleware('can:manage-roles');
    Route::get('/admin/setting/rules/logs-data', [\App\Http\Controllers\Admin\Setting\RuleController::class, 'logRules'])->name('logs.data.rule')->middleware('can:manage-roles');
    Route::post('/admin/setting/role/create', [\App\Http\Controllers\Admin\Setting\RuleController::class, 'createRole'])->name('admin.setting.role.create')->middleware('can:manage-roles');
    Route::delete('/admin/setting/role/delete', [\App\Http\Controllers\Admin\Setting\RuleController::class, 'deleteRole'])->name('admin.setting.role.delete')->middleware('can:manage-roles');

    //Страница Настойки-Платежные системы
    Route::get('/admin/settings/paymentSystem', [\App\Http\Controllers\Admin\Setting\PaymentSystemController::class, 'index'])->name('admin.setting.paymentSystem')->middleware('can:setting-payment-systems');
    Route::post('/payment-systems/store', [\App\Http\Controllers\Admin\Setting\PaymentSystemController::class, 'store'])->name('payment-systems.store')->middleware('can:setting-payment-systems');
    Route::get('/payment-systems/{name}', [\App\Http\Controllers\Admin\Setting\PaymentSystemController::class, 'show'])->name('payment-systems.show')->middleware('can:setting-payment-systems');
    Route::delete('/payment-systems/{payment_system}', [\App\Http\Controllers\Admin\Setting\PaymentSystemController::class, 'destroy'])->name('payment-systems.destroy')->middleware('can:setting-payment-systems');

    //Страница оплаты сервиса адмимон
    Route::get('/partner-payment/recharge', [\App\Http\Controllers\PartnerPaymentController::class, 'showRecharge'])->name('partner.payment.recharge')->middleware('can:service-payment');
    Route::get('/partner-payment/history', [\App\Http\Controllers\PartnerPaymentController::class, 'showHistory'])->name('partner.payment.history')->middleware('can:service-payment');
    Route::get('/partner-payment/data', [\App\Http\Controllers\PartnerPaymentController::class, 'getPaymentsData'])->name('partner.payment.data')->middleware('can:service-payment');
    Route::post('//payment/service/yookassa', [\App\Http\Controllers\PartnerPaymentController::class, 'createPaymentYookassa'])->name('createPaymentYookassa')->middleware('can:service-payment');

    //Страница оплаты робокассы
    Route::post('/payment', [\App\Http\Controllers\TransactionController::class, 'index'])->name('payment')->middleware('can:paying-classes');
    Route::post('/payment/pay', [\App\Http\Controllers\TransactionController::class, 'pay'])->name('payment.pay')->middleware('can:paying-classes');
    Route::get('/payment/success', [\App\Http\Controllers\TransactionController::class, 'success'])->name('payment.success')->middleware('can:paying-classes');
    Route::get('/payment/fail', [\App\Http\Controllers\TransactionController::class, 'fail'])->name('payment.fail');

    //Оплата клубного взноса (робокасса)
    Route::get('/payment/club-fee', [\App\Http\Controllers\TransactionController::class, 'clubFee'])->name('clubFee')->middleware('can:payment-clubfee'); //Оплата клубного взноса

    //    Оплата ТБанк
    Route::get('/tinkoff/form', [\App\Http\Controllers\TinkoffPaymentController::class, 'index'])->name('tinkoff.form');
    Route::post('/tinkoff/init', [\App\Http\Controllers\TinkoffPaymentController::class, 'init'])->name('tinkoff.init');
    Route::post('/tinkoff/callback', [\App\Http\Controllers\TinkoffPaymentController::class, 'callback'])->name('tinkoff.callback');
    //    Route::get('/tinkoff/success', [\App\Http\Controllers\TinkoffPaymentController::class, 'success'])->name('tinkoff.success');
    //    Route::get('/tinkoff/fail', [\App\Http\Controllers\TinkoffPaymentController::class, 'fail'])->name('tinkoff.fail');


    //Учетная запись - вкладка юзер
    Route::get('/account-settings/users/{user}/edit', [\App\Http\Controllers\Admin\AccountSettingController::class, 'user'])->name('admin.cur.user.edit');
    Route::patch('/account-settings/users/{user}', [\App\Http\Controllers\Admin\AccountSettingController::class, 'update'])->name('account.user.update');
    Route::post('/user/update-password', [\App\Http\Controllers\Admin\AccountSettingController::class, 'updatePassword']);
    //Обновление аватарки юзером
    Route::post('/profile/upload-user-avatar', [\App\Http\Controllers\Admin\AccountSettingController::class, 'uploadAvatar'])->name('profile.user.uploadAvatar');
    //Обновление аватара админом
    Route::post('admin/user/{user}/update-avatar', [\App\Http\Controllers\Admin\AccountSettingController::class, 'updateAvatar'])->name('admin.user.update-avatar');
    Route::post('/admin/user/{user}/delete-avatar', [\App\Http\Controllers\Admin\AccountSettingController::class, 'deleteAvatar'])->name('user.delete-avatar');

    //Учетная запись - вкладка организация
    Route::get('/account-settings/partner/{user}/edit', [\App\Http\Controllers\Admin\PartnerSettingController::class, 'partner'])->name('admin.cur.company.edit')->middleware('can:partner-company');
    Route::patch('/account-settings/partner/{partner}', [\App\Http\Controllers\Admin\PartnerSettingController::class, 'updatePartner'])->name('admin.cur.partner.update')->middleware('can:partner-company');

    //Организация (сервис)
    Route::get('/about', [\App\Http\Controllers\AboutController::class, 'index'])->name('about');
    Route::get('/terms', [\App\Http\Controllers\AboutController::class, 'terms'])->name('terms');

    //Журнал расписания
    Route::get('/schedule', [\App\Http\Controllers\Admin\ScheduleController::class, 'index'])->name('schedule.index')->middleware('can:schedule-journal');
    Route::post('/schedule/update', [\App\Http\Controllers\Admin\ScheduleController::class, 'update'])->name('schedule.update')->middleware('can:schedule-journal');
    Route::get('/schedule/logs-data', [\App\Http\Controllers\Admin\ScheduleController::class, 'getLogsData'])->name('logs.data.schedule')->middleware('can:schedule-journal');
    //    Route::get('/admin/user-schedule/{user}', [\App\Http\Controllers\Admin\ScheduleController::class, 'getUserScheduleInfo'])->name('user.schedule.info')->middleware('can:schedule-journal');
    Route::get('/schedule/user-schedule/{user}', [\App\Http\Controllers\Admin\ScheduleController::class, 'getUserScheduleInfo'])->name('user.schedule.info')->middleware('can:schedule-journal');
    //    Route::post('/admin/user/{user}/set-group', [\App\Http\Controllers\Admin\ScheduleController::class, 'setUserGroup'])->name('user.set.group')->middleware('can:schedule-journal');
    Route::post('/schedule/user/{user}/set-group', [\App\Http\Controllers\Admin\ScheduleController::class, 'setUserGroup'])->name('user.set.group')->middleware('can:schedule-journal');
    //    Route::post('/admin/user/{user}/update-schedule-range', [\App\Http\Controllers\Admin\ScheduleController::class, 'updateUserScheduleRange'])->name('user.update.schedule')->middleware('can:schedule-journal');
    Route::post('/schedule/user/{user}/update-schedule-range', [\App\Http\Controllers\Admin\ScheduleController::class, 'updateUserScheduleRange'])->name('user.update.schedule')->middleware('can:schedule-journal');

    // Статусы
    Route::get('/schedule/statuses', [\App\Http\Controllers\Admin\StatusController::class, 'index'])->name('statuses.index')->middleware('can:schedule-journal');
    Route::post('/schedule/statuses', [\App\Http\Controllers\Admin\StatusController::class, 'store'])->name('statuses.store')->middleware('can:schedule-journal');
    Route::patch('/schedule/statuses/{id}', [\App\Http\Controllers\Admin\StatusController::class, 'update'])->name('statuses.update')->middleware('can:schedule-journal');
    Route::delete('/schedule/statuses/{id}', [\App\Http\Controllers\Admin\StatusController::class, 'destroy'])->name('statuses.destroy')->middleware('can:schedule-journal');

    //переключение между партнерами
    Route::post('/switch-partner', [\App\Http\Controllers\PartnerSwitchController::class, 'switch'])->name('partner.switch')->middleware('can:changing-partner');


    Route::middleware(['can:admin'])->prefix('admin')->group(function () {
        Route::get('2fa', [TwoFactorController::class, 'show'])->name('admin.2fa.show');
        Route::post('2fa/enable', [TwoFactorController::class, 'enable'])->name('admin.2fa.enable');
        Route::post('2fa/verify', [TwoFactorController::class, 'verify'])->name('admin.2fa.verify');
        Route::post('2fa/disable', [TwoFactorController::class, 'disable'])->name('admin.2fa.disable');
    });


});
// Маршрут для обработки результатов оплаты робокассы (callback от Robokassa)
Route::get('/payment/result', [\App\Http\Controllers\RobokassaController::class, 'result'])->name('payment.result');

//вебхук емани
Route::post('/webhook/yookassa', [\App\Http\Controllers\WebhookController::class, 'handleWebhook'])->name('webhook.yookassa');


Route::post('password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('password.update');


//Маршрут для оплпты тиньков мультирасчеты
//Route::get('/tinkoff/payout/{paymentId}', [\App\Http\Controllers\TinkoffPayoutController::class, 'payout']);
Route::post('/tinkoff/pay', [\App\Http\Controllers\TinkoffPaymentController::class, 'init'])->name('tinkoff.pay');
Route::post('/tinkoff/callback', [\App\Http\Controllers\TinkoffPaymentController::class, 'callback'])->name('tinkoff.callback'); // пока заглушка


Route::get('/log-test', function () {
    \Log::info('Тестовая запись в лог');
    return 'Записано';
});
