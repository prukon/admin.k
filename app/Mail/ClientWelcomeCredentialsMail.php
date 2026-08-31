<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ClientWelcomeCredentialsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public const SUBJECT_PREFIX = 'Доступ в личный кабинет';

    public function __construct(
        public User $student,
        public string $plainPassword,
        public string $partnerTitle,
        public int $partnerId,
        public string $loginUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: self::SUBJECT_PREFIX . ' — ' . $this->partnerTitle,
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Partner-Id' => (string) $this->partnerId,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-welcome-credentials',
        );
    }
}
