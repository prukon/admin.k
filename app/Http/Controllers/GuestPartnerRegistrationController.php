<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\StorePartnerSelfRegistrationRequest;
use App\Notifications\PartnerSelfRegisteredNotification;
use App\Services\GoogleRecaptchaVerifier;
use App\Services\PartnerSelfRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class GuestPartnerRegistrationController extends Controller
{
    public function create(): View
    {
        return view('landing.partner-register');
    }

    public function store(
        StorePartnerSelfRegistrationRequest $request,
        GoogleRecaptchaVerifier $recaptcha,
        PartnerSelfRegistrationService $registration,
    ): RedirectResponse {
        $validated = $request->validated();

        $emailKey = 'partner-registration:email:' . hash('sha256', strtolower($validated['email']));
        if (RateLimiter::tooManyAttempts($emailKey, 5)) {
            return back()
                ->withInput($request->except('password', 'password_confirmation', 'recaptcha_token'))
                ->withErrors([
                    'email' => 'Слишком много попыток регистрации с этого email. Повторите через час.',
                ]);
        }

        $recaptcha->verifyOrFail(
            $validated['recaptcha_token'],
            $request->ip(),
            'partner_register'
        );

        $result = $registration->register([
            'school_title' => $validated['school_title'],
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'password'     => $validated['password'],
        ]);

        RateLimiter::hit($emailKey, 3600);

        $user = $result['user'];
        $partner = $result['partner'];

        $user->notify(new PartnerSelfRegisteredNotification(
            $partner,
            $user->name,
            $user->email,
        ));

        Auth::login($user, false);
        $request->session()->regenerate();

        return redirect('/cabinet');
    }
}
