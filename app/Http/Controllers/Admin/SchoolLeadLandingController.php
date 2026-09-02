<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewSchoolLeadLandingInstructionRequest;
use App\Http\Requests\UpdateSchoolLeadLandingSlugRequest;
use App\Models\Partner;
use App\Services\PartnerContext;
use App\Services\PartnerWidgetService;
use App\Support\PartnerAdminUserOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SchoolLeadLandingController extends Controller
{
    public function __construct(
        private readonly PartnerContext $partnerContext,
        private readonly PartnerWidgetService $widgetService,
    ) {
    }

    public function landingTab(): View
    {
        return $this->renderLandingTab();
    }

    public function updateSlug(UpdateSchoolLeadLandingSlugRequest $request): JsonResponse
    {
        $partner = $this->resolveCurrentPartner();
        $widget = $this->widgetService->ensureForPartner((int) $partner->id);

        $widget->landing_slug = $request->validated('landing_slug');
        $widget->save();

        $landingUrl = route('lead.show', ['landingSlug' => $widget->landing_slug]);

        return response()->json([
            'message'      => 'Адрес страницы сохранён.',
            'landing_slug' => $widget->landing_slug,
            'landing_url'  => $landingUrl,
        ]);
    }

    public function previewInstruction(PreviewSchoolLeadLandingInstructionRequest $request): JsonResponse|RedirectResponse
    {
        $partner = $this->resolveCurrentPartner();
        $widget = $this->widgetService->ensureForPartner((int) $partner->id);
        $slug = (string) ($widget->landing_slug ?? '');
        $wantsJson = $request->ajax() || $request->expectsJson();

        if ($slug === '') {
            if ($wantsJson) {
                return response()->json([
                    'message' => 'Сначала сохраните адрес страницы.',
                    'errors'  => [
                        'landing_slug' => ['Сначала сохраните адрес страницы.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('admin.school-leads.landing')
                ->withErrors([
                    'landing_slug' => ['Сначала сохраните адрес страницы.'],
                ]);
        }

        $params = ['landingSlug' => $slug];
        $digits = $request->normalizedPhoneDigits();
        if ($digits !== null) {
            $params['phone'] = $digits;
        }

        $instructionUrl = route('lead.instruction', $params);

        if ($wantsJson) {
            return response()->json([
                'instruction_url' => $instructionUrl,
            ]);
        }

        return redirect()->to($instructionUrl);
    }

    private function renderLandingTab(): View
    {
        $partner = $this->resolveCurrentPartner();
        $widget = $this->widgetService->ensureForPartner((int) $partner->id);

        $landingUrl = $widget->landing_slug !== null && $widget->landing_slug !== ''
            ? route('lead.show', ['landingSlug' => $widget->landing_slug])
            : null;

        $instructionUrl = $widget->landing_slug !== null && $widget->landing_slug !== ''
            ? route('lead.instruction', ['landingSlug' => $widget->landing_slug])
            : null;

        return view('admin.school-leads.index', [
            'activeTab'              => 'landing',
            'widget'                 => $widget,
            'landingUrl'             => $landingUrl,
            'instructionUrl'         => $instructionUrl,
            'instructionAdminPhones' => PartnerAdminUserOptions::phoneOptionsForPartner((int) $partner->id),
            'partner'                => $partner,
        ]);
    }

    private function resolveCurrentPartner(): Partner
    {
        $partnerId = $this->partnerContext->partnerId();

        if (!$partnerId) {
            abort(403, 'Партнёр не выбран.');
        }

        return Partner::query()->findOrFail($partnerId);
    }
}
