<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;

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


Route::group(['namespace' => 'Auth', 'middleware' => 'auth'], function () {

//    Группы
    Route::get('admin/teams', '\App\Http\Controllers\Admin\Team\IndexController')->name('admin.team.index');
    Route::get('admin/teams/create', '\App\Http\Controllers\Admin\Team\CreateController')->name('admin.team.create');
    Route::post('admin/teams', '\App\Http\Controllers\Admin\Team\StoreController')->name('admin.team.store');
    Route::get('admin/teams/{team}/edit', '\App\Http\Controllers\Admin\Team\EditController')->name('admin.team.edit');
    Route::patch('admin/teams/{team}', '\App\Http\Controllers\Admin\Team\UpdateController')->name('admin.team.update');
    Route::delete('admin/teams/{team}', '\App\Http\Controllers\Admin\Team\DestroyController')->name('admin.team.delete');
    Route::get('/admin/teams/logs-data', '\App\Http\Controllers\Admin\Team\LogsController')->name('logs.data.team');


//    Пользователи
    Route::get('admin/users', '\App\Http\Controllers\Admin\User\IndexController')->name('admin.user.index');
    Route::get('admin/users/create', '\App\Http\Controllers\Admin\User\CreateController')->name('admin.user.create');
    Route::post('admin/users', '\App\Http\Controllers\Admin\User\StoreController')->name('admin.user.store');
    Route::get('admin/users/{user}/edit', '\App\Http\Controllers\Admin\User\EditController')->name('admin.user.edit');
    Route::patch('admin/users/{user}', '\App\Http\Controllers\Admin\User\UpdateController')->name('admin.user.update');
//    Route::delete('', '\App\Http\Controllers\Admin\User\DestroyController')->name('admin.user.delete');
    Route::delete('/admin/user/{user}', '\App\Http\Controllers\Admin\User\DestroyController')->name('admin.user.delete');
    Route::get('/admin/user/logs-data', '\App\Http\Controllers\Admin\User\LogsController')->name('logs.data.user');



//    Route::get('admin/reports', [\App\Http\Controllers\Admin\Report\ReportController::class, 'index'])->name('admin.report');
    Route::get('admin/setting-prices', [\App\Http\Controllers\Admin\SettingPricesController::class, 'index'])->name('admin.settingPrices.indexMenu');
//    Route::get('admin/setting-prices', [\App\Http\Controllers\Admin\SettingPricesController::class, 'index'])->name('admin.settingPrices.index');
    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');



//    Отчеты
//        Route::get('/admin/payments', [\App\Http\Controllers\Admin\PaymentsController::class, 'index'])->name('payments.index');

    Route::get('/admin/reports/payments', [\App\Http\Controllers\Admin\Report\ReportController::class, 'payments'])->name('payments');
    Route::get('/admin/reports/debts', [\App\Http\Controllers\Admin\Report\ReportController::class, 'debts'])->name('debts');
    Route::get('/getPayments', [\App\Http\Controllers\Admin\Report\ReportController::class, 'getPayments'])->name('payments.getPayments');
    Route::get('/getDebts', [\App\Http\Controllers\Admin\Report\ReportController::class, 'getDebts'])->name('debts.getDebts');


//AJAX
    Route::get('/update-date', [\App\Http\Controllers\Admin\SettingPricesController::class, 'updateDate'])->name('updateDate');

    Route::get('/get-user-details', [\App\Http\Controllers\DashboardController::class, 'getUserDetails'])->name('getUserDetails');
    Route::get('/get-team-details', [\App\Http\Controllers\DashboardController::class, 'getTeamDetails'])->name('getTeamDetails');
    Route::get('/setup-btn', [\App\Http\Controllers\DashboardController::class, 'setupBtn'])->name('setupBtn');
    Route::get('/content-menu-calendar', [\App\Http\Controllers\DashboardController::class, 'contentMenuCalendar'])->name('contentMenuCalendar');

    Route::post('/admin/user/{id}/update-password', [\App\Http\Controllers\Admin\User\UpdateController::class, 'updatePassword']);

//    Установка цен
    Route::get('/get-team-price', [\App\Http\Controllers\Admin\SettingPricesController::class, 'getTeamPrice'])->name('getTeamPrice');
    Route::get('/set-team-price', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setTeamPrice'])->name('setTeamPrice');
    Route::get('/set-price-all-teams', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setPriceAllTeams'])->name('setPriceAllTeams');
    Route::get('/set-price-all-users', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setPriceAllUsers'])->name('setPriceAllUsers');
    Route::get('/setting-prices/logs-data', [\App\Http\Controllers\Admin\SettingPricesController::class, 'getLogsData'])->name('logs.data.settingPrice');




    Route::post('/profile/upload-avatar', [\App\Http\Controllers\DashboardController::class, 'uploadAvatar'])->name('profile.uploadAvatar');

    //Страница выбора оплаты
    Route::post('/payment', [\App\Http\Controllers\TransactionController::class, 'index'])->name('payment');
    //Страница оплаты робокассы
    Route::post('/payment/pay', [\App\Http\Controllers\TransactionController::class, 'pay'])->name('payment.pay');
    // Маршрут для страницы успешной оплаты
    Route::get('/payment/success', [\App\Http\Controllers\TransactionController::class, 'success'])->name('payment.success');
    // Маршрут для страницы неудачной оплаты
    Route::get('/payment/fail', [\App\Http\Controllers\TransactionController::class, 'fail'])->name('payment.fail');
    Route::get('/payment/club-fee', [\App\Http\Controllers\TransactionController::class, 'clubFee'])->name('.clubFee');

    Route::get('/admin/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('setting');
    Route::get('/admin/settings/registration-activity', [\App\Http\Controllers\Admin\SettingController::class, 'registrationActivity'])->name('registrationActivity');
    Route::get('/admin/settings/text-for-users', [\App\Http\Controllers\Admin\SettingController::class, 'textForUsers'])->name('textForUsers');


//  Route::get('/account-settings', [\App\Http\Controllers\AccountSettingController::class, 'index'])->name('accountSettings');

//Учетная запись
    Route::get('/account-settings/users/{user}/edit', [\App\Http\Controllers\AccountSettingController::class, 'index'])->name('user.edit');
    Route::patch('/account-settings/users/{user}', [\App\Http\Controllers\AccountSettingController::class, 'update'])->name('user.update');
    Route::post('/user/{id}/update-password', [\App\Http\Controllers\AccountSettingController::class, 'updatePassword']);
//    Route::get('/account-settings/logs-data', [\App\Http\Controllers\AccountSettingController::class, 'getLogsData'])->name('logs.data.accountSettings');


});
// Маршрут для обработки результатов оплаты робокассы (callback от Robokassa)
Route::get('/payment/result', [\App\Http\Controllers\RobokassaController::class, 'result'])->name('payment.result');


Auth::routes();
