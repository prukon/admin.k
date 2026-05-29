<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\PartnerContext;
use App\Services\PartnerWidgetService;
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

    private function renderLandingTab(): View
    {
        $partner = $this->resolveCurrentPartner();
        $widget = $this->widgetService->ensureForPartner((int) $partner->id);

        $landingUrl = route('lead.show', ['landingKey' => $widget->landing_key]);

        return view('admin.school-leads.index', [
            'activeTab'  => 'landing',
            'widget'     => $widget,
            'landingUrl' => $landingUrl,
            'partner'    => $partner,
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
