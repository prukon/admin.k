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

    public function show(string $landingSlug): View
    {
        $widget = $this->landing->resolveActiveWidget($landingSlug);
        $partner = $widget->partner;

        if ($partner === null) {
            abort(404);
        }

        $locations = $this->landing->locationsForWidget($widget);

        return view('landing.partner-lead', [
            'partner'          => $partner,
            'locations'        => $locations,
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
            'submitUrl'        => route('lead.submit', ['landingSlug' => $landingSlug]),
            'teamsUrl'         => route('lead.teams', ['landingSlug' => $landingSlug]),
            'teamInfoUrl'      => route('lead.team-info', ['landingSlug' => $landingSlug]),
        ]);
    }

    public function teamInfo(Request $request, string $landingSlug): JsonResponse
    {
        $widget = $this->landing->resolveActiveWidget($landingSlug);

        $locationId = (int) $request->query('location_id', 0);
        $teamId = (int) $request->query('team_id', 0);

        if ($locationId <= 0) {
            return response()->json([
                'message' => 'Укажите район.',
                'errors'  => ['location_id' => ['Укажите район.']],
            ], 422);
        }

        if ($teamId <= 0) {
            return response()->json([
                'message' => 'Укажите услугу.',
                'errors'  => ['team_id' => ['Укажите услугу.']],
            ], 422);
        }

        $info = $this->landing->teamInfoForLanding($widget, $locationId, $teamId);
        if ($info === null) {
            abort(404);
        }

        return response()->json([
            'data' => $info,
        ]);
    }

    public function teams(Request $request, string $landingSlug): JsonResponse
    {
        $widget = $this->landing->resolveActiveWidget($landingSlug);

        $locationId = (int) $request->query('location_id', 0);
        if ($locationId <= 0) {
            return response()->json([
                'message' => 'Укажите район.',
                'errors'  => ['location_id' => ['Укажите район.']],
            ], 422);
        }

        $teams = $this->landing->teamsForLocation($widget, $locationId);

        return response()->json([
            'data' => $teams->values(),
        ]);
    }

    public function submit(SubmitSchoolLeadLandingRequest $request, string $landingSlug): JsonResponse
    {
        $recaptchaResult = $this->recaptcha->verifyRequest($request);
        if (!$recaptchaResult['ok']) {
            return response()->json([
                'message' => $recaptchaResult['message'],
            ], $recaptchaResult['status']);
        }

        $widget = $this->landing->resolveActiveWidget($landingSlug);

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
