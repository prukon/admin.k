<?php

namespace App\Mail;

use App\Models\PartnerLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewPartnerLeadSubmission extends Mailable
{
    use Queueable, SerializesModels;

    public PartnerLead $partnerLead;

    public function __construct(PartnerLead $partnerLead)
    {
        $this->partnerLead = $partnerLead;
    }

    public function build()
    {
        return $this
            ->subject('Новая заявка с лендинга')
            ->view('emails.new_submission');
    }
}
