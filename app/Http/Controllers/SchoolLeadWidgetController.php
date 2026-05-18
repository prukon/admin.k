<?php

namespace App\Http\Controllers;

use App\Enums\SchoolLeadStatus;
use App\Http\Requests\SubmitSchoolLeadRequest;
use App\Models\PartnerWidget;
use App\Models\SchoolLead;
use App\Services\RecaptchaVerificationService;
use App\Services\SchoolLeadNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SchoolLeadWidgetController extends Controller
{
    public function __construct(
        private readonly RecaptchaVerificationService $recaptcha,
        private readonly SchoolLeadNotificationService $notifications,
    ) {
    }

    public function show(string $widgetKey): View
    {
        $widget = $this->resolveActiveWidget($widgetKey);

        return view('widget.school-lead-form', [
            'widget'           => $widget,
            'policyUrl'        => route('policy'),
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
            'submitUrl'        => route('widget.school-lead.submit', ['widgetKey' => $widget->widget_key]),
        ]);
    }

    public function submit(SubmitSchoolLeadRequest $request, string $widgetKey): JsonResponse
    {
        $recaptchaResult = $this->recaptcha->verifyRequest($request);
        if (!$recaptchaResult['ok']) {
            return response()->json([
                'message' => $recaptchaResult['message'],
            ], $recaptchaResult['status']);
        }

        $widget = $this->resolveActiveWidget($widgetKey);

        try {
            $schoolLead = SchoolLead::create([
                'partner_id'          => $widget->partner_id,
                'partner_widget_id'   => $widget->id,
                'name'                => $request->string('name')->toString(),
                'phone'               => $request->string('phone')->toString(),
                'status'              => SchoolLeadStatus::New->value,
                'utm_source'          => $request->input('utm_source'),
                'utm_medium'          => $request->input('utm_medium'),
                'utm_campaign'        => $request->input('utm_campaign'),
                'utm_content'         => $request->input('utm_content'),
                'utm_term'            => $request->input('utm_term'),
                'page_url'            => $request->input('page_url'),
                'referrer'            => $request->input('referrer'),
                'consent_accepted_at' => now(),
                'policy_url'          => route('policy'),
                'ip'                  => $request->ip(),
                'user_agent'          => $request->userAgent(),
            ]);

            $this->notifications->notify($schoolLead);

            return response()->json([
                'message' => 'Заявка отправлена!',
                'id'      => $schoolLead->id,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'На сервере произошла ошибка. Попробуйте позже.',
            ], 500);
        }
    }

    private function resolveActiveWidget(string $widgetKey): PartnerWidget
    {
        return PartnerWidget::query()
            ->where('widget_key', $widgetKey)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
