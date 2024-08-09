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
    Route::delete('', '\App\Http\Controllers\Admin\User\DestroyController')->name('admin.user.delete');

    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/payments', [\App\Http\Controllers\PaymentsController::class, 'index'])->name('payments.index');
    Route::get('/prices', [\App\Http\Controllers\PricesController::class, 'index'])->name('prices.index');


//AJAX
    Route::get('/get-user-details', [\App\Http\Controllers\DashboardController::class, 'getUserDetails'])->name('getUserDetails');
    Route::get('/get-team-details', [\App\Http\Controllers\DashboardController::class, 'getTeamDetails'])->name('getTeamDetails');

});

//ajax
//Route::get('/11', [\App\Http\Controllers\Dashboard\IndexController::class, 'getUserDetails'])->name('getUserDetails');
//Route::get('/', '\App\Http\Controllers\HomeController@getUserDetails')->name('getUserDetails');
Auth::routes();
