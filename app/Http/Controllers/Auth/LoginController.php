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
        return '/cabinet'; // Перенаправление на главную страницу
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
        // Ваши данные, которые нужно передать в представление

        $setting = Setting::where('name', 'registrationActivity')->first();
        $isRegistrationActivity = $setting ? $setting->status : null;


        // Возвращаем view с данными
//        return view('auth.login',
//            ['customData' => $customData]);

        return view("auth.login", compact(
            "isRegistrationActivity",
        ));
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


//    public function logout(Request $request)
//    {
//        Auth::logout();
//
//        $request->session()->invalidate();
//        $request->session()->regenerateToken();
//
//        return redirect('/');
//    }

}
