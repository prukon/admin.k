<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitSchoolLeadLandingRequest;
use App\Services\RecaptchaVerificationService;
use App\Services\SchoolLeadLandingService;
use App\Services\SchoolLeadNotificationService;
use App\Support\RuPhone;
use App\Support\UrlQrCode;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        $districts = $this->landing->districtsForWidget($widget);

        return view('landing.partner-lead', [
            'partner'          => $partner,
            'districts'        => $districts,
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
            'submitUrl'        => route('lead.submit', ['landingSlug' => $landingSlug]),
            'locationsUrl'     => route('lead.locations', ['landingSlug' => $landingSlug]),
            'teamsUrl'         => route('lead.teams', ['landingSlug' => $landingSlug]),
            'teamInfoUrl'      => route('lead.team-info', ['landingSlug' => $landingSlug]),
        ]);
    }

    public function instruction(Request $request, string $landingSlug): View
    {
        return view('landing.partner-lead-instruction', $this->instructionViewData($request, $landingSlug));
    }

    public function instructionPdf(Request $request, string $landingSlug): Response
    {
        $data = $this->instructionViewData($request, $landingSlug);
        $data['qrPngDataUri'] = UrlQrCode::pngDataUri($data['landingUrl']);
        $data['logoPngDataUri'] = $this->kidsCrmLogoPngDataUri();

        $html = view('landing.partner-lead-instruction-pdf', $data)->render();

        $options = new Options();
        $options->set('defaultFont', (string) config('contracts.dompdf_font', 'DejaVu Sans'));
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $filename = 'instrukciya-'.$landingSlug.'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function locations(Request $request, string $landingSlug): JsonResponse
    {
        $widget = $this->landing->resolveActiveWidget($landingSlug);

        $districtId = (int) $request->query('district_id', 0);
        if ($districtId <= 0) {
            return response()->json([
                'message' => 'Укажите район.',
                'errors'  => ['district_id' => ['Укажите район.']],
            ], 422);
        }

        $locations = $this->landing->locationsForDistrict($widget, $districtId);

        return response()->json([
            'data' => $locations->values(),
        ]);
    }

    public function teamInfo(Request $request, string $landingSlug): JsonResponse
    {
        $widget = $this->landing->resolveActiveWidget($landingSlug);

        $locationId = (int) $request->query('location_id', 0);
        $teamId = (int) $request->query('team_id', 0);

        if ($locationId <= 0) {
            return response()->json([
                'message' => 'Укажите объект.',
                'errors'  => ['location_id' => ['Укажите объект.']],
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
                'message' => 'Укажите объект.',
                'errors'  => ['location_id' => ['Укажите объект.']],
            ], 422);
        }

        $teams = $this->landing->teamsForLocation($widget, $locationId);

        return response()->json([
            'data' => $teams->values(),
        ]);
    }

    public function submit(SubmitSchoolLeadLandingRequest $request, string $landingSlug): JsonResponse|RedirectResponse
    {
        $wantsJson = $request->ajax() || $request->expectsJson();

        $recaptchaResult = $this->recaptcha->verifyRequest($request);
        if (!$recaptchaResult['ok']) {
            if ($wantsJson) {
                return response()->json([
                    'message' => $recaptchaResult['message'],
                ], $recaptchaResult['status']);
            }

            return redirect()
                ->route('lead.show', ['landingSlug' => $landingSlug])
                ->withInput()
                ->withErrors(['form' => $recaptchaResult['message']]);
        }

        $widget = $this->landing->resolveActiveWidget($landingSlug);

        try {
            $schoolLead = $this->landing->createFromRequest($request, $widget);
            $schoolLead->loadMissing('district', 'location', 'team');
            $this->notifications->notify($schoolLead);

            $message = 'Заявка отправлена! Мы свяжемся с вами в ближайшее время.';

            if ($wantsJson) {
                return response()->json([
                    'message' => $message,
                    'id'      => $schoolLead->id,
                ]);
            }

            return redirect()
                ->route('lead.show', ['landingSlug' => $landingSlug])
                ->with('landing_submitted', true);
        } catch (\Throwable $e) {
            report($e);

            if ($wantsJson) {
                return response()->json([
                    'message' => 'На сервере произошла ошибка. Попробуйте позже.',
                ], 500);
            }

            return redirect()
                ->route('lead.show', ['landingSlug' => $landingSlug])
                ->withInput()
                ->withErrors(['form' => 'На сервере произошла ошибка. Попробуйте позже.']);
        }
    }

    /**
     * @return array{
     *     partner: \App\Models\Partner,
     *     landingUrl: string,
     *     pdfUrl: string,
     *     contactPhone: ?string,
     *     contactPhoneHref: ?string
     * }
     */
    private function instructionViewData(Request $request, string $landingSlug): array
    {
        $widget = $this->landing->resolveActiveWidget($landingSlug);
        $partner = $widget->partner;

        if ($partner === null) {
            abort(404);
        }

        $digits = $this->instructionPhoneDigits($request);
        $contactPhone = $digits !== null ? RuPhone::formatForInput($digits) : null;
        $contactPhoneHref = $digits !== null ? 'tel:+'.$digits : null;

        $pdfParams = ['landingSlug' => $landingSlug];
        if ($digits !== null) {
            $pdfParams['phone'] = $digits;
        }

        return [
            'partner'          => $partner,
            'landingUrl'       => route('lead.show', ['landingSlug' => $landingSlug]),
            'pdfUrl'           => route('lead.instruction.pdf', $pdfParams),
            'contactPhone'     => $contactPhone,
            'contactPhoneHref' => $contactPhoneHref,
        ];
    }

    private function instructionPhoneDigits(Request $request): ?string
    {
        $raw = $request->query('phone');
        if (! is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $digits = RuPhone::normalizeDigits($trimmed);
        if ($digits === null || strlen($digits) !== 11 || ! str_starts_with($digits, '7')) {
            return null;
        }

        return $digits;
    }

    private function kidsCrmLogoPngDataUri(): ?string
    {
        $path = public_path('img/logo.png');
        if (! is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($raw);
    }
}
