<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitSchoolLeadLandingRequest;
use App\Services\RecaptchaVerificationService;
use App\Services\SchoolLeadLandingService;
use App\Services\SchoolLeadNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolLeadLandingController extends Controller
{
    public function __construct(
        private readonly SchoolLeadLandingService $landing,
        private readonly RecaptchaVerificationService $recaptcha,
        private readonly SchoolLeadNotificationService $notifications,
    ) {
    }

    public function show(string $landingKey): View
    {
        $widget = $this->landing->resolveActiveWidget($landingKey);
        $partner = $widget->partner;

        if ($partner === null) {
            abort(404);
        }

        $locations = $this->landing->locationsForWidget($widget);
        $sportTypes = $this->landing->sportTypesForWidget($widget);

        return view('landing.partner-lead', [
            'partner'          => $partner,
            'locations'        => $locations,
            'sportTypes'       => $sportTypes,
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
            'submitUrl'        => route('lead.submit', ['landingKey' => $landingKey]),
            'teamsUrl'         => route('lead.teams', ['landingKey' => $landingKey]),
        ]);
    }

    public function teams(Request $request, string $landingKey): JsonResponse
    {
        $widget = $this->landing->resolveActiveWidget($landingKey);

        $locationId = (int) $request->query('location_id', 0);
        if ($locationId <= 0) {
            return response()->json([
                'message' => 'Укажите район.',
                'errors'  => ['location_id' => ['Укажите район.']],
            ], 422);
        }

        $sportTypeId = $request->query('sport_type_id');
        $sportTypeId = ($sportTypeId !== null && $sportTypeId !== '' && ctype_digit((string) $sportTypeId))
            ? (int) $sportTypeId
            : null;

        $teams = $this->landing->teamsForLocation($widget, $locationId, $sportTypeId);

        return response()->json([
            'data' => $teams->values(),
        ]);
    }

    public function submit(SubmitSchoolLeadLandingRequest $request, string $landingKey): JsonResponse
    {
        $recaptchaResult = $this->recaptcha->verifyRequest($request);
        if (!$recaptchaResult['ok']) {
            return response()->json([
                'message' => $recaptchaResult['message'],
            ], $recaptchaResult['status']);
        }

        $widget = $this->landing->resolveActiveWidget($landingKey);

        try {
            $schoolLead = $this->landing->createFromRequest($request, $widget);
            $schoolLead->loadMissing('location', 'team');
            $this->notifications->notify($schoolLead);

            return response()->json([
                'message' => 'Заявка отправлена! Мы свяжемся с вами в ближайшее время.',
                'id'      => $schoolLead->id,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'На сервере произошла ошибка. Попробуйте позже.',
            ], 500);
        }
    }
}
