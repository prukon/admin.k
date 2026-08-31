<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Mail\ClientSiblingAddedMail;
use App\Mail\ClientWelcomeCredentialsMail;
use App\Mail\PaymentNotificationMail;
use App\Models\OutgoingEmailLog;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Журнал исходящих: тип письма пишется для Mailable, для Mail::raw — нет.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LogOutgoingEmailMailableClassFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array', 'queue.default' => 'sync']);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_welcome_letter_stores_its_type_in_outgoing_log(): void
    {
        $student = $this->createUserWithRole('user', $this->partner, [
            'email' => 'log-welcome-type@example.test',
        ]);

        Mail::to($student->email)->send(new ClientWelcomeCredentialsMail(
            student: $student,
            plainPassword: 'TempPass1234',
            partnerTitle: (string) $this->partner->title,
            partnerId: (int) $this->partner->id,
            loginUrl: url('/login'),
        ));

        $log = OutgoingEmailLog::query()
            ->where('to_summary', 'like', '%log-welcome-type@example.test%')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(OutgoingEmailLog::STATUS_SENT, $log->status);
        $this->assertSame(ClientWelcomeCredentialsMail::class, $log->mailable_class);
        $this->assertStringStartsWith(ClientWelcomeCredentialsMail::SUBJECT_PREFIX, (string) $log->subject);
        $this->assertSame((int) $this->partner->id, (int) $log->partner_id);
    }

    public function test_plain_mail_without_template_does_not_invent_a_type(): void
    {
        Mail::raw('Hello body', function ($message): void {
            $message->to('raw-no-type@example.test')
                ->from('from@example.test')
                ->subject('Raw without type');
        });

        $log = OutgoingEmailLog::query()
            ->where('subject', 'Raw without type')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(OutgoingEmailLog::STATUS_SENT, $log->status);
        $this->assertNull($log->mailable_class);
    }

    public function test_header_can_fill_type_when_there_is_no_mailable(): void
    {
        Mail::raw('Header body', function ($message): void {
            $message->to('raw-header-type@example.test')
                ->from('from@example.test')
                ->subject('Raw with header type');
            $message->getHeaders()->addTextHeader(
                'X-Mailable-Class',
                ClientWelcomeCredentialsMail::class
            );
        });

        $log = OutgoingEmailLog::query()
            ->where('subject', 'Raw with header type')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(ClientWelcomeCredentialsMail::class, $log->mailable_class);
    }

    public function test_sibling_letter_stores_sibling_type_not_welcome(): void
    {
        $first = $this->createUserWithRole('user', $this->partner, [
            'email' => 'family-login@example.test',
        ]);
        $sibling = $this->createUserWithRole('user', $this->partner, [
            'email' => null,
        ]);

        Mail::to('family-login@example.test')->send(new ClientSiblingAddedMail(
            student: $sibling,
            familyLoginUser: $first,
            partnerTitle: (string) $this->partner->title,
            partnerId: (int) $this->partner->id,
            loginUrl: url('/login'),
        ));

        $log = OutgoingEmailLog::query()
            ->where('to_summary', 'like', '%family-login@example.test%')
            ->where('subject', 'like', 'Новый ученик в семейном кабинете%')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(ClientSiblingAddedMail::class, $log->mailable_class);
        $this->assertNotSame(ClientWelcomeCredentialsMail::class, $log->mailable_class);
    }

    public function test_payment_notification_stores_its_own_type(): void
    {
        Mail::to('pay-notice@example.test')->send(new PaymentNotificationMail(
            emailSubject: 'Напоминание об оплате',
            bodyHtml: '<p>Оплата</p>',
            partnerId: (int) $this->partner->id,
        ));

        $log = OutgoingEmailLog::query()
            ->where('subject', 'Напоминание об оплате')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(PaymentNotificationMail::class, $log->mailable_class);
        $this->assertSame((int) $this->partner->id, (int) $log->partner_id);
    }
}
