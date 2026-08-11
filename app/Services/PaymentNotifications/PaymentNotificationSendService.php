<?php

declare(strict_types=1);

namespace App\Services\PaymentNotifications;

use App\Mail\PaymentNotificationMail;
use App\Models\OutgoingEmailLog;
use App\Models\PaymentNotificationDispatch;
use App\Models\UserPrice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Отправка одного письма по записи payment_notification_dispatches.
 */
final class PaymentNotificationSendService
{
    public function __construct(
        private readonly PaymentNotificationTemplateRenderer $renderer,
    ) {
    }

    public function sendDispatch(PaymentNotificationDispatch $dispatch): void
    {
        if ($dispatch->status === PaymentNotificationDispatch::STATUS_SENT) {
            return;
        }

        if ($dispatch->status === PaymentNotificationDispatch::STATUS_SKIPPED) {
            return;
        }

        $rule = $dispatch->rule;
        $userPrice = $dispatch->userPrice;
        if ($rule === null || $userPrice === null) {
            $this->markFailed($dispatch, 'Правило или начисление не найдены.');

            return;
        }

        // Повторная проверка перед отправкой: могли оплатить после постановки в очередь.
        $userPrice->refresh();
        if ($userPrice->effective_is_paid || (int) $userPrice->price_cents <= 0) {
            $dispatch->update([
                'status' => PaymentNotificationDispatch::STATUS_SKIPPED,
                'skip_reason' => 'already_paid_or_zero',
                'error_message' => null,
            ]);

            return;
        }

        $userPrice->loadMissing(['user', 'team', 'lessonPackage']);
        $email = trim((string) ($userPrice->user?->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $dispatch->update([
                'status' => PaymentNotificationDispatch::STATUS_SKIPPED,
                'skip_reason' => 'missing_or_invalid_email',
            ]);

            return;
        }

        $rendered = $this->renderer->render(
            (string) $rule->subject_template,
            (string) $rule->body_html_template,
            $userPrice
        );

        $beforeId = (int) (OutgoingEmailLog::query()->max('id') ?? 0);

        try {
            Mail::to($email)->send(new PaymentNotificationMail(
                emailSubject: $rendered['subject'],
                bodyHtml: $rendered['body_html'],
                partnerId: (int) $dispatch->partner_id,
                dispatchId: (int) $dispatch->id,
            ));
        } catch (Throwable $e) {
            $this->markFailed($dispatch, $e->getMessage());

            throw $e;
        }

        $logId = OutgoingEmailLog::query()
            ->where('id', '>', $beforeId)
            ->where('partner_id', (int) $dispatch->partner_id)
            ->where('mailable_class', PaymentNotificationMail::class)
            ->orderByDesc('id')
            ->value('id');

        $dispatch->update([
            'status' => PaymentNotificationDispatch::STATUS_SENT,
            'sent_at' => CarbonImmutable::now(PaymentNotificationTriggerResolver::TIMEZONE),
            'outgoing_email_log_id' => $logId ? (int) $logId : null,
            'error_message' => null,
            'skip_reason' => null,
        ]);
    }

    /**
     * Тестовая отправка (не пишет payment_notification_dispatches).
     *
     * @return array{subject: string, body_html: string}
     */
    public function sendTest(
        int $partnerId,
        string $toEmail,
        string $subjectTemplate,
        string $bodyHtmlTemplate,
        ?UserPrice $userPrice = null,
    ): array {
        if ($userPrice !== null) {
            $userPrice->loadMissing(['user', 'team', 'lessonPackage']);
            $rendered = $this->renderer->render($subjectTemplate, $bodyHtmlTemplate, $userPrice);
        } else {
            $rendered = $this->renderer->renderDemo($subjectTemplate, $bodyHtmlTemplate);
        }

        Mail::to($toEmail)->send(new PaymentNotificationMail(
            emailSubject: '[Тест] '.$rendered['subject'],
            bodyHtml: $rendered['body_html'],
            partnerId: $partnerId,
            dispatchId: null,
        ));

        return [
            'subject' => '[Тест] '.$rendered['subject'],
            'body_html' => $rendered['body_html'],
        ];
    }

    private function markFailed(PaymentNotificationDispatch $dispatch, string $message): void
    {
        $dispatch->update([
            'status' => PaymentNotificationDispatch::STATUS_FAILED,
            'error_message' => mb_substr($message, 0, 2000),
        ]);
    }
}
