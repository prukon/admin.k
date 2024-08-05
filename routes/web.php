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

//Route::get('/', function () {
//    return view('welcome');
//});

Route::get('/test', function () {
    return view('layouts.main');
});






//Route::get('/dashboard', '\App\Http\Controllers\Dasboard\IndexController')->name('dashboard.index');
Route::get('/payments', [\App\Http\Controllers\PaymentsController::class, 'index'])->name('payments.index');
Route::get('/prices', [\App\Http\Controllers\PricesController::class, 'index'])->name('prices.index');


Route::group(['namespace' => 'User'], function () {
//Пользователи
    Route::get('/users', '\App\Http\Controllers\User\IndexController')->name('user.index');
//Создание пользователя
    Route::get('users/create', '\App\Http\Controllers\User\CreateController')->name('user.create');
//Создание пользователя обработка
    Route::post('users', '\App\Http\Controllers\User\StoreController')->name('user.store');
//Показ 1 пользователя
//    Route::get('users/{user}', '\App\Http\Controllers\User\ShowController')->name('user.show');
//Редактирование 1 пользователя
    Route::get('users/{user}/edit', '\App\Http\Controllers\User\EditController')->name('user.edit');
//Редактирование 1 обработка
    Route::patch('users/{user}', '\App\Http\Controllers\User\UpdateController')->name('user.update');
//Удаление 1 юзера
    Route::delete('users/{user}', '\App\Http\Controllers\User\DestroyController')->name('user.delete');
});

Route::group(['namespace' => 'Team'], function () {
//Группы
    Route::get('/teams', '\App\Http\Controllers\Team\IndexController')->name('team.index');
    Route::get('teams/create', '\App\Http\Controllers\Team\CreateController')->name('team.create');
    Route::post('teams', '\App\Http\Controllers\Team\StoreController')->name('team.store');
    Route::get('teams/{team}/edit', '\App\Http\Controllers\Team\EditController')->name('team.edit');
    Route::patch('teams/{team}', '\App\Http\Controllers\Team\UpdateController')->name('team.update');
    Route::delete('teams/{team}', '\App\Http\Controllers\Team\DestroyController')->name('team.delete');

});


Route::get('/main', '\App\Http\Controllers\MainController')->name('main.index');


Route::group(['namespace' => 'Admin', 'middleware'=> 'admin'], function () {
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
    Route::delete('admin/users/{user}', '\App\Http\Controllers\Admin\User\DestroyController')->name('admin.user.delete');

//    Route::get('admin/dashboard', '\App\Http\Controllers\Admin\Dashboard\IndexController')->name('admin.dashboard.index');

});


Route::get('/', '\App\Http\Controllers\HomeController@index')->name('home');

use App\Http\Controllers\Auth\RegisterController;

Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');



Auth::routes();


