<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PartnerPaymentController;
use Illuminate\Support\Facades\Mail;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Auth::routes();


Route::group(['namespace' => 'Auth', 'middleware' => 'auth'], function () {

//    Группы
    Route::get('admin/teams', '\App\Http\Controllers\Admin\Team\IndexController')->name('admin.team1');
    Route::get('admin/teams/create', '\App\Http\Controllers\Admin\Team\CreateController')->name('admin.team.create');
    Route::post('admin/teams', '\App\Http\Controllers\Admin\Team\StoreController')->name('admin.team.store');
    Route::get('/admin/team/{id}/edit', [\App\Http\Controllers\Admin\Team\EditController::class, 'edit'])->name('admin.team.edit');
    Route::patch('/admin/team/{id}', '\App\Http\Controllers\Admin\Team\UpdateController')->name('admin.team.update');
    Route::delete('admin/team/{team}', '\App\Http\Controllers\Admin\Team\DestroyController')->name('admin.team.delete');
    Route::get('/admin/teams/logs-data', '\App\Http\Controllers\Admin\Team\LogsController')->name('logs.data.team');

//    Пользователи
    Route::get('admin/users', '\App\Http\Controllers\Admin\User\IndexController')->name('admin.user1');
    Route::get('admin/users/create', '\App\Http\Controllers\Admin\User\CreateController')->name('admin.user.create');
    Route::post('admin/users', '\App\Http\Controllers\Admin\User\StoreController')->name('admin.user.store');
    Route::get('admin/users/{user}/edit', '\App\Http\Controllers\Admin\User\EditController')->name('admin.user.edit');
    Route::patch('admin/users/{user}', '\App\Http\Controllers\Admin\User\UpdateController')->name('admin.user.update');
    Route::delete('/admin/user/{user}', '\App\Http\Controllers\Admin\User\DestroyController')->name('admin.user.delete');
    Route::get('/admin/user/logs-data', '\App\Http\Controllers\Admin\User\LogsController')->name('logs.data.user');
    Route::post('/admin/field/store', [\App\Http\Controllers\Admin\User\UpdateController::class, 'storeFields'])->name('admin.field.store');
    Route::delete('/admin/field/delete/{id}', [\App\Http\Controllers\Admin\User\UpdateController::class, 'deleteField'])->name('admin.field.delete');




    Route::get('admin/setting-prices', [\App\Http\Controllers\Admin\SettingPricesController::class, 'index'])->name('admin.settingPrices.indexMenu');
    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');


//    Отчеты
    Route::get('/admin/reports/payments', [\App\Http\Controllers\Admin\Report\ReportController::class, 'payments'])->name('payments');
    Route::get('/admin/reports/debts', [\App\Http\Controllers\Admin\Report\ReportController::class, 'debts'])->name('debts');
    Route::get('/admin/reports/ltv', [\App\Http\Controllers\Admin\Report\ReportController::class, 'ltv'])->name('ltv');

    Route::get('/getPayments', [\App\Http\Controllers\Admin\Report\ReportController::class, 'getPayments'])->name('payments.getPayments');
    Route::get('/getDebts', [\App\Http\Controllers\Admin\Report\ReportController::class, 'getDebts'])->name('debts.getDebts');
    Route::get('/getLtv', [\App\Http\Controllers\Admin\Report\ReportController::class, 'getLtv'])->name('ltv.getLtv');


    //    Отчеты юзера
    Route::get('/reports/payments', [\App\Http\Controllers\User\Report\ReportController::class, 'showUserPayments'])->name('showUserPayments');
    Route::get('/getUserPayments', [\App\Http\Controllers\User\Report\ReportController::class, 'getUserPayments'])->name('payments.getUserPayments');


//AJAX
    Route::get('/update-date', [\App\Http\Controllers\Admin\SettingPricesController::class, 'updateDate'])->name('updateDate');
    Route::get('/get-user-details', [\App\Http\Controllers\DashboardController::class, 'getUserDetails'])->name('getUserDetails');
    Route::get('/get-team-details', [\App\Http\Controllers\DashboardController::class, 'getTeamDetails'])->name('getTeamDetails');
    Route::get('/setup-btn', [\App\Http\Controllers\DashboardController::class, 'setupBtn'])->name('setupBtn');
    Route::get('/content-menu-calendar', [\App\Http\Controllers\DashboardController::class, 'contentMenuCalendar'])->name('contentMenuCalendar');

    Route::post('/admin/user/{id}/update-password', [\App\Http\Controllers\Admin\User\UpdateController::class, 'updatePassword']);

//    Установка цен
    Route::post('/get-team-price', [\App\Http\Controllers\Admin\SettingPricesController::class, 'getTeamPrice'])->name('getTeamPrice');
    Route::post('/set-team-price', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setTeamPrice'])->name('setTeamPrice');
    Route::post('/set-price-all-teams', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setPriceAllTeams'])->name('setPriceAllTeams');
    Route::post('/set-price-all-users', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setPriceAllUsers'])->name('setPriceAllUsers');

    Route::get('/setting-prices/logs-data', [\App\Http\Controllers\Admin\SettingPricesController::class, 'getLogsData'])->name('logs.data.settingPrice');

    Route::post('/profile/upload-user-avatar', [\App\Http\Controllers\AccountSettingController::class, 'uploadAvatar'])->name('profile.user.uploadAvatar');

    //Обновление аватара админом
    Route::post('admin/user/{user}/update-avatar', [\App\Http\Controllers\AccountSettingController::class, 'updateAvatar'])->name('admin.user.update-avatar');
    Route::post('/admin/user/{user}/delete-avatar', [\App\Http\Controllers\AccountSettingController::class, 'deleteAvatar'])->name('user.delete-avatar');


    //Страница выбора оплаты
    Route::post('/payment', [\App\Http\Controllers\TransactionController::class, 'index'])->name('payment');

    //Страница оплаты робокассы
    Route::post('/payment/pay', [\App\Http\Controllers\TransactionController::class, 'pay'])->name('payment.pay');
    // Маршрут для страницы успешной оплаты
    Route::get('/payment/success', [\App\Http\Controllers\TransactionController::class, 'success'])->name('payment.success');
    // Маршрут для страницы неудачной оплаты
    Route::get('/payment/fail', [\App\Http\Controllers\TransactionController::class, 'fail'])->name('payment.fail');
    Route::get('/payment/club-fee', [\App\Http\Controllers\TransactionController::class, 'clubFee'])->name('clubFee'); //Оплата клубного взноса


//    Route::get('/payment/service', [\App\Http\Controllers\TransactionController::class, 'service'])->name('service'); //Оплата сервиса


//  Страница оплаты сервиса адимон
    Route::get('/partner-payment/recharge', [\App\Http\Controllers\PartnerPaymentController::class, 'showRecharge'])->name('partner.payment.recharge');
    Route::get('/partner-payment/history', [\App\Http\Controllers\PartnerPaymentController::class, 'showHistory'])->name('partner.payment.history');
    Route::get('/partner-payment/data', [\App\Http\Controllers\PartnerPaymentController::class, 'getPaymentsData'])->name('partner.payment.data');


//    Страница Настойки
    Route::get('/admin/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('setting');
    Route::get('/admin/settings/registration-activity', [\App\Http\Controllers\Admin\SettingController::class, 'registrationActivity'])->name('registrationActivity');
    Route::post('/admin/settings/text-for-users', [\App\Http\Controllers\Admin\SettingController::class, 'textForUsers'])->name('textForUsers');
    Route::get('/admin/settings/logs-all-data', [\App\Http\Controllers\Admin\SettingController::class, 'logsAllData'])->name('logs.all.data');
    Route::get('/edit-menu', [\App\Http\Controllers\Admin\SettingController::class, 'editMenu'])->name('editMenu');
    Route::post('/settings/save-menu-items', [\App\Http\Controllers\Admin\SettingController::class, 'saveMenuItems'])->name('settings.saveMenuItems');
    Route::post('/settings/save-social-menu-items', [\App\Http\Controllers\Admin\SettingController::class, 'saveSocialItems'])->name('settings.saveSocialItems');

//Учетная запись
    Route::get('/account-settings/users/{user}/edit', [\App\Http\Controllers\AccountSettingController::class, 'index'])->name('user.edit');
    Route::patch('/account-settings/users/{user}', [\App\Http\Controllers\AccountSettingController::class, 'update'])->name('user.update');
    Route::post('/user/{id}/update-password', [\App\Http\Controllers\AccountSettingController::class, 'updatePassword']);


    //Учетная запись админа
    Route::get('/admin/account-settings/users/{user}/edit', [\App\Http\Controllers\Admin\AccountSettingController::class, 'user'])->name('admin.cur.user.edit');
    Route::get('/admin/account-settings/partner/{user}/edit', [\App\Http\Controllers\Admin\AccountSettingController::class, 'partner'])->name('admin.cur.company.edit');
    Route::patch('/admin/account-settings/users/{user}', [\App\Http\Controllers\Admin\AccountSettingController::class, 'update'])->name('admin.cur.user.update');
    Route::patch('/admin/account-settings/partner/{partner}', [\App\Http\Controllers\Admin\AccountSettingController::class, 'updatePartner'])->name('admin.cur.partner.update');


//    Route::patch('/admin/account-settings/users/{user}', function () {
//        dd('test');
//    })->name('admin.cur.user.update');

    Route::post('/admin/user/{id}/update-password', [\App\Http\Controllers\Admin\AccountSettingController::class, 'updatePassword']);

//Организация
    Route::get('/admin/company', [\App\Http\Controllers\Admin\CompanyController::class, 'index'])->name('company');
    Route::get('/about', [\App\Http\Controllers\AboutController::class, 'index'])->name('about');
    Route::get('/terms', [\App\Http\Controllers\AboutController::class, 'terms'])->name('terms');


    //Емани
    Route::post('//payment/service/yookassa', [\App\Http\Controllers\PartnerPaymentController::class, 'createPaymentYookassa'])->name('createPaymentYookassa');





});
// Маршрут для обработки результатов оплаты робокассы (callback от Robokassa)
Route::get('/payment/result', [\App\Http\Controllers\RobokassaController::class, 'result'])->name('payment.result');

//вебхук емани
Route::post('/webhook/yookassa', [\App\Http\Controllers\WebhookController::class, 'handleWebhook'])->name('webhook.yookassa');


Route::post('password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('password.update');



//Route::get('/test-mail', function () {
//    $details = [
//        'title' => 'Тестовое письмо',
//        'body' => 'Это тестовое письмо для проверки отправки почты через SMTP.'
//    ];
//
//    Mail::raw($details['body'], function ($message) {
//        $message->to('prukon@gmail.com') // Замените на реальный адрес получателя
//        ->subject('Тестовое письмо из Laravel');
//    });
//
//    return 'Письмо отправлено!';
//});

//Auth::routes(['verify' => true]);
