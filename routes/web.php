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


//Route::get('/dasboard', function () {
//    return  "dasboard";
//});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index' ])->name('dashboard.index');;
Route::get('/payments', [\App\Http\Controllers\PaymentsController::class, 'index' ])->name('payments.index');;
Route::get('/prices', [\App\Http\Controllers\PricesController::class, 'index' ])->name('prices.index');;
Route::get('/users', [\App\Http\Controllers\UsersController::class, 'index' ])->name('users.index');;








Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
