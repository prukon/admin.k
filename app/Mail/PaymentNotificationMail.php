<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Письмо уведомления об оплате месячного начисления (users_prices).
 * Класс используется как фильтр в отчёте «Исходящие письма».
 */
class PaymentNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public const CATEGORY = 'payment_notification';

    public const CATEGORY_LABEL = 'Уведомления об оплате';

    public function __construct(
        public string $emailSubject,
        public string $bodyHtml,
        public int $partnerId,
        public ?int $dispatchId = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function headers(): Headers
    {
        $text = [
            'X-Partner-Id' => (string) $this->partnerId,
            'X-Email-Category' => self::CATEGORY,
        ];
        if ($this->dispatchId !== null && $this->dispatchId > 0) {
            $text['X-Payment-Notification-Dispatch-Id'] = (string) $this->dispatchId;
        }

        return new Headers(text: $text);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-notification',
            with: [
                'bodyHtml' => $this->bodyHtml,
            ],
        );
    }
}
