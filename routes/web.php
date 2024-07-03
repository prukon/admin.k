<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {
	return view('layouts.main');
});


//Route::get('/dasboard', function () {
//    return  "dasboard";
//});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index' ])->name('dashboard.index');
Route::get('/payments', [\App\Http\Controllers\PaymentsController::class, 'index' ])->name('payments.index');
Route::get('/prices', [\App\Http\Controllers\PricesController::class, 'index' ])->name('prices.index');


Route::group(['namespace'=> 'User'], function (){
//Пользователи
    Route::get('/users', '\App\Http\Controllers\User\IndexController')->name('user.index');
//Создание пользователя
    Route::get('users/create', '\App\Http\Controllers\User\CreateController')->name('user.create');
//Создание пользователя обработка
    Route::post('users', '\App\Http\Controllers\User\StoreController')->name('user.store');
//Показ 1 пользователя
//    Route::get('users/{user}', '\App\Http\Controllers\User\ShowController')->name('user.show');
//Редактирование 1 пользователя
    Route::get('users/{user}/edit','\App\Http\Controllers\User\EditController')->name('user.edit');
//Редактирование 1 обработка
    Route::patch('users/{user}', '\App\Http\Controllers\User\UpdateController')->name('user.update');
//Удаление 1 юзера
    Route::delete('users/{user}', '\App\Http\Controllers\User\DestroyController')->name('user.delete');
});


//Группы
Route::get('/teams', [\App\Http\Controllers\TeamsController::class, 'index' ])->name('team.index');
Route::get('teams/create', [\App\Http\Controllers\TeamsController::class, 'create' ])->name('team.create');
Route::post('teams', [\App\Http\Controllers\TeamsController::class, 'store' ])->name('team.store');
Route::get('teams/{team}/edit', [\App\Http\Controllers\TeamsController::class, 'edit' ])->name('team.edit');
Route::patch('teams/{team}', [\App\Http\Controllers\TeamsController::class, 'update' ])->name('team.update');
Route::delete('teams/{team}', [\App\Http\Controllers\TeamsController::class, 'destroy' ])->name('team.delete');





Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
