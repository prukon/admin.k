<?php

namespace App\Mail;

use App\Models\SchoolLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewSchoolLeadSubmission extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly SchoolLead $schoolLead,
        public readonly string $partnerTitle,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Новая заявка с сайта — ' . $this->partnerTitle)
            ->view('emails.new_school_lead');
    }
}
