<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractClientFillInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contract $contract,
        public User $student,
    ) {
    }

    public function envelope(): Envelope
    {
        $version = $this->contract->templateVersion;
        $subject = $version?->defaultEmailSubject() ?? 'Договор: требуется заполнение и подписание';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $version = $this->contract->templateVersion;
        $bodyHtml = $version?->defaultEmailBodyHtml() ?? '';
        $documentsUrl = url('/account-settings/documents');

        return new Content(
            htmlString: $this->renderBody($bodyHtml, $documentsUrl),
        );
    }

    private function renderBody(string $bodyHtml, string $documentsUrl): string
    {
        $replacements = [
            '{{documents_url}}'   => e($documentsUrl),
            '{{student_name}}'    => e(trim(($this->student->lastname ?? '') . ' ' . ($this->student->name ?? ''))),
            '{{contract_id}}'     => (string) $this->contract->id,
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $bodyHtml);

        return '<div style="font-family:system-ui,sans-serif;line-height:1.5">' . $html . '</div>';
    }
}
