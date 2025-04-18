<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

    class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
//    protected $redirectTo = RouteServiceProvider::HOME;
    protected function redirectTo()
    {
        return '/'; // Перенаправление на главную страницу
    }


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view("auth.login");
    }



    protected function sendFailedLoginResponse(\Illuminate\Http\Request $request)
    {
        $user = \App\Models\User::where('email', $request->email)->first();

        // Если пользователь с таким email не найден
        if (!$user) {
            return back()->withInput()->withErrors([
                'email' => 'Такой email не найден.',
            ]);
        }

        // Если пользователь найден, но пароль неверный
        return back()->withInput()->withErrors([
            'password' => 'Неправильный пароль.',
        ]);
    }



}
