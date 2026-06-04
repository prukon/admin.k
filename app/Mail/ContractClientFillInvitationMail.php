<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\User;
use App\Services\Contracts\ContractInvitationEmailRenderer;
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
        $renderer = app(ContractInvitationEmailRenderer::class);

        return new Envelope(
            subject: $renderer->renderSubject($this->contract, $this->student),
        );
    }

    public function content(): Content
    {
        $renderer = app(ContractInvitationEmailRenderer::class);

        return new Content(
            htmlString: $renderer->renderBodyHtml($this->contract, $this->student),
        );
    }
}
