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

Route::get('/dasboard', [\App\Http\Controllers\DashboardController::class, 'index' ]);


Route::get('/payments', function () {
    return  "payments";
});

Route::get('/prices', function () {
    return  "prices";
});

Route::get('/users', function () {
    return  "users";
});





Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
