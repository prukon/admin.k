<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller
{
    use ResetsPasswords;

    /**
     * Куда перенаправлять пользователей после сброса пароля.
     *
     * @var string
     */
    protected $redirectTo = '/login';

    protected function sendResetResponse(Request $request, $response)
    {
        return redirect('/login')->with('status', trans($response));
    }
}

