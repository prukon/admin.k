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

Route::get('/dashboard', '\App\Http\Controllers\Dasboard\IndexController')->name('dashboard.index');
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
Route::group(['namespace'=> 'User'], function (){
//Группы
    Route::get('/teams', '\App\Http\Controllers\Team\IndexController')->name('team.index');
    Route::get('teams/create', '\App\Http\Controllers\Team\CreateController')->name('team.create');
    Route::post('teams', '\App\Http\Controllers\Team\StoreController')->name('team.store');
    Route::get('teams/{team}/edit', '\App\Http\Controllers\Team\EditController')->name('team.edit');
    Route::patch('teams/{team}', '\App\Http\Controllers\Team\UpdateController')->name('team.update');
    Route::delete('teams/{team}', '\App\Http\Controllers\Team\DestroyController')->name('team.delete');

});






Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
