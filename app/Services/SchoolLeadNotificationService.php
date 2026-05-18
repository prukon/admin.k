<?php

namespace App\Services;

use App\Mail\NewSchoolLeadSubmission;
use App\Models\Partner;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SchoolLeadNotificationService
{
    public function __construct(
        private readonly TelegramBotClient $telegram,
    ) {
    }

    public function notify(SchoolLead $schoolLead): void
    {
        $schoolLead->loadMissing('partner');

        $partner = $schoolLead->partner;
        if (!$partner) {
            return;
        }

        $this->sendEmails($schoolLead, (string) $partner->title);
        $this->sendTelegram($schoolLead, (string) $partner->title, $partner->school_leads_telegram_chat_id);
    }

    private function sendEmails(SchoolLead $schoolLead, string $partnerTitle): void
    {
        $recipients = $this->resolveAdminEmails((int) $schoolLead->partner_id);

        if ($recipients->isEmpty()) {
            return;
        }

        $mailable = new NewSchoolLeadSubmission($schoolLead, $partnerTitle);

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send($mailable);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @return Collection<int, string>
     */
    public function resolveAdminEmails(int $partnerId): Collection
    {
        $adminRoleId = Role::query()->where('name', 'admin')->value('id');

        if (!$adminRoleId) {
            return collect();
        }

        $emails = User::query()
            ->where('partner_id', $partnerId)
            ->where('role_id', $adminRoleId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email');

        $partnerEmail = Partner::query()
            ->whereKey($partnerId)
            ->value('email');

        if (is_string($partnerEmail) && $partnerEmail !== '') {
            $emails->push($partnerEmail);
        }

        return $emails
            ->map(fn ($email) => trim((string) $email))
            ->filter()
            ->unique()
            ->values();
    }

    private function sendTelegram(SchoolLead $schoolLead, string $partnerTitle, ?string $chatId): void
    {
        $chatId = trim((string) $chatId);
        if ($chatId === '') {
            return;
        }

        $lines = [
            '📩 Новая заявка с сайта',
            '',
            "🏫 {$partnerTitle}",
            "👤 {$schoolLead->name}",
            "📞 {$schoolLead->phone}",
        ];

        $utm = array_filter([
            $schoolLead->utm_source ? 'source: ' . $schoolLead->utm_source : null,
            $schoolLead->utm_medium ? 'medium: ' . $schoolLead->utm_medium : null,
            $schoolLead->utm_campaign ? 'campaign: ' . $schoolLead->utm_campaign : null,
        ]);
        if (!empty($utm)) {
            $lines[] = '';
            $lines[] = '🔗 ' . implode(', ', $utm);
        }

        if ($schoolLead->page_url) {
            $lines[] = '';
            $lines[] = '🌐 ' . mb_substr((string) $schoolLead->page_url, 0, 500);
        }

        $lines[] = '';
        $lines[] = 'CRM: ' . url('/admin/school-leads');

        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }
}
