<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendPasswordResetLinkRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Параллельный повтор POST /password/email (даблтап) ловит 1062 на
     * password_reset_tokens.email PK. Победитель уже отправил письмо —
     * проигравшему показываем тот же успех, не HTTP 500.
     */
    public function sendResetLinkEmail(SendPasswordResetLinkRequest $request)
    {
        try {
            $response = $this->broker()->sendResetLink(
                $this->credentials($request)
            );
        } catch (UniqueConstraintViolationException) {
            $response = Password::RESET_LINK_SENT;
        }

        return $response == Password::RESET_LINK_SENT
                    ? $this->sendResetLinkResponse($request, $response)
                    : $this->sendResetLinkFailedResponse($request, $response);
    }
}
