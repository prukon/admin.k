<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\UpdateSchoolLeadNotificationSettingsRequest;
use App\Services\PartnerContext;
use App\Services\SchoolLeadNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class SchoolLeadNotificationSettingsController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly SchoolLeadNotificationService $notifications,
    ) {
        parent::__construct($partnerContext);
    }

    public function show(): JsonResponse
    {
        $partner = $this->requirePartner();
        $configuredEmails = $partner->school_leads_notification_emails;
        $emailsConfigured = is_array($configuredEmails);

        $emails = $emailsConfigured
            ? $this->notifications->normalizeEmailList($configuredEmails)->all()
            : $this->notifications->resolveAdminEmails((int) $partner->id)->all();

        return response()->json([
            'emails'                         => $emails,
            'emails_configured'              => $emailsConfigured,
            'email_notifications_disabled'   => (bool) $partner->school_leads_email_notifications_disabled,
            'suggested_emails'               => $this->notifications->suggestedEmailOptions($partner),
        ]);
    }

    public function update(UpdateSchoolLeadNotificationSettingsRequest $request): JsonResponse|RedirectResponse
    {
        $partner = $this->requirePartner();
        $data = $request->validated();
        $emails = $this->notifications->normalizeEmailList($data['emails'] ?? [])->all();

        $partner->school_leads_notification_emails = $emails;
        $partner->school_leads_email_notifications_disabled = (bool) $data['email_notifications_disabled'];
        $partner->save();

        $payload = [
            'success'                      => true,
            'message'                      => 'Настройки уведомлений сохранены.',
            'emails'                       => $emails,
            'email_notifications_disabled' => (bool) $partner->school_leads_email_notifications_disabled,
        ];

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('admin.school-leads')
            ->with('success', (string) $payload['message']);
    }
}
