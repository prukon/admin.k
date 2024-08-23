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


    Route::get('admin/teams', '\App\Http\Controllers\Admin\Team\IndexController')->name('admin.team.index');
    Route::get('admin/teams/create', '\App\Http\Controllers\Admin\Team\CreateController')->name('admin.team.create');
    Route::post('admin/teams', '\App\Http\Controllers\Admin\Team\StoreController')->name('admin.team.store');
    Route::get('admin/teams/{team}/edit', '\App\Http\Controllers\Admin\Team\EditController')->name('admin.team.edit');
    Route::patch('admin/teams/{team}', '\App\Http\Controllers\Admin\Team\UpdateController')->name('admin.team.update');
    Route::delete('admin/teams/{team}', '\App\Http\Controllers\Admin\Team\DestroyController')->name('admin.team.delete');

    Route::get('admin/users', '\App\Http\Controllers\Admin\User\IndexController')->name('admin.user.index');
    Route::get('admin/users/create', '\App\Http\Controllers\Admin\User\CreateController')->name('admin.user.create');
    Route::post('admin/users', '\App\Http\Controllers\Admin\User\StoreController')->name('admin.user.store');
    Route::get('admin/users/{user}/edit', '\App\Http\Controllers\Admin\User\EditController')->name('admin.user.edit');
    Route::patch('admin/users/{user}', '\App\Http\Controllers\Admin\User\UpdateController')->name('admin.user.update');
//    Route::delete('', '\App\Http\Controllers\Admin\User\DestroyController')->name('admin.user.delete');
    Route::delete('/admin/user/{user}', '\App\Http\Controllers\Admin\User\DestroyController')->name('admin.user.delete');


    Route::get('admin/setting-prices', [\App\Http\Controllers\Admin\SettingPricesController::class, 'index'])->name('admin.settingPrices.index');

    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');


    Route::get('/admin/payments', [\App\Http\Controllers\Admin\PaymentsController::class, 'index'])->name('payments.index');
    Route::get('/getPayments', [\App\Http\Controllers\Admin\PaymentsController::class, 'getPayments'])->name('payments.getPayments');





//AJAX
    Route::get('/get-user-details', [\App\Http\Controllers\DashboardController::class, 'getUserDetails'])->name('getUserDetails');
    Route::get('/get-team-details', [\App\Http\Controllers\DashboardController::class, 'getTeamDetails'])->name('getTeamDetails');
    Route::get('/setup-btn', [\App\Http\Controllers\DashboardController::class, 'setupBtn'])->name('setupBtn');
    Route::get('/content-menu-calendar', [\App\Http\Controllers\DashboardController::class, 'contentMenuCalendar'])->name('contentMenuCalendar');



    Route::get('/get-team-price', [\App\Http\Controllers\Admin\SettingPricesController::class, 'getTeamPrice'])->name('getTeamPrice');
    Route::get('/set-team-price', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setTeamPrice'])->name('setTeamPrice');
    Route::get('/update-date', [\App\Http\Controllers\Admin\SettingPricesController::class, 'updateDate'])->name('updateDate');
    Route::get('/set-price-all-teams', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setPriceAllTeams'])->name('setPriceAllTeams');
    Route::get('/set-price-all-users', [\App\Http\Controllers\Admin\SettingPricesController::class, 'setPriceAllUsers'])->name('setPriceAllUsers');




//    Route::post('/upload-avatar', [\App\Http\Controllers\DashboardController::class, 'uploadAvatar'])->name('uploadAvatar');
    Route::post('/profile/upload-avatar', [\App\Http\Controllers\DashboardController::class, 'uploadAvatar'])->name('profile.uploadAvatar');


//    Route::get('/calendar', [\App\Http\Controllers\CalendarController::class, 'index']);

    // Маршрут для API, который возвращает события (это в случае, если у вас есть динамическая загрузка событий через AJAX)
//    Route::get('/api/events', [\App\Http\Controllers\EventController::class, 'getEvents']);


});

//ajax
//Route::get('/11', [\App\Http\Controllers\Dashboard\IndexController::class, 'getUserDetails'])->name('getUserDetails');
//Route::get('/', '\App\Http\Controllers\HomeController@getUserDetails')->name('getUserDetails');
Auth::routes();
