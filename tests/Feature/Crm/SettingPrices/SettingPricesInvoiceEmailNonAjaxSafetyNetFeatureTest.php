<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Mail\PaymentNotificationMail;
use App\Models\ParentProfile;
use Illuminate\Support\Facades\Mail;

/**
 * Non-AJAX safety-net: форма без X-Requested-With → 302 обратно на вкладку, письмо уходит.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesInvoiceEmailNonAjaxSafetyNetFeatureTest extends SettingPricesInvoiceEmailTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
    }

    public function test_html_form_from_monthly_redirects_and_sends_the_invoice(): void
    {
        Mail::fake();
        $this->seedTbank();
        $row = $this->charge();

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.invoice-email.send'), $this->payload($row));

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $response->assertSessionHas('status', 'Счёт отправлен на student-invoice@example.com.');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        Mail::assertSent(PaymentNotificationMail::class, function (PaymentNotificationMail $mail) {
            return $mail->hasTo('student-invoice@example.com')
                && str_contains($mail->bodyHtml, 'Оплатить через СБП');
        });
    }

    public function test_html_form_from_users_tab_redirects_back_to_users(): void
    {
        Mail::fake();
        $this->seedTbank();
        $row = $this->charge();

        $this->from(route('admin.settingPrices.users'))
            ->post(route('setting-prices.invoice-email.send'), $this->payload($row))
            ->assertRedirect(route('admin.settingPrices.users'))
            ->assertSessionHas('status', 'Счёт отправлен на student-invoice@example.com.');

        Mail::assertSent(PaymentNotificationMail::class);
    }

    public function test_html_form_without_month_redirects_with_field_error_and_does_not_send(): void
    {
        Mail::fake();
        $this->charge();

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.invoice-email.send'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['new_month' => 'Укажите месяц начисления.']);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        Mail::assertNothingSent();
    }

    public function test_html_form_for_paid_month_redirects_with_amount_error(): void
    {
        Mail::fake();
        $row = $this->charge(['is_paid' => 1]);

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.invoice-email.send'), $this->payload($row))
            ->assertStatus(302)
            ->assertSessionHasErrors(['amount' => 'Этот период уже оплачен.']);

        $this->assertNotSame(200, $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.invoice-email.send'), $this->payload($row))
            ->getStatusCode());
        Mail::assertNothingSent();
    }

    public function test_html_preview_redirects_and_does_not_send_mail(): void
    {
        Mail::fake();
        $this->seedTbank();
        $row = $this->charge();

        $this->from(route('admin.settingPrices.users'))
            ->post(route('setting-prices.invoice-email.preview'), $this->payload($row))
            ->assertRedirect(route('admin.settingPrices.users'));

        Mail::assertNothingSent();
    }

    public function test_html_preview_without_pay_link_redirects_with_pay_url_error(): void
    {
        $row = $this->charge();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.invoice-email.preview'), $this->payload($row))
            ->assertStatus(302)
            ->assertSessionHasErrors(['pay_url' => 'Оплата через СБП недоступна: у школы не подключён T‑Bank.']);
    }

    public function test_html_form_for_unknown_charge_redirects_with_student_error(): void
    {
        Mail::fake();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.invoice-email.send'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'new_month' => '2026-01-01',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['user_id' => 'Начисление не найдено.']);

        Mail::assertNothingSent();
    }

    public function test_html_form_uses_parent_email_in_the_status_message(): void
    {
        Mail::fake();
        $this->seedTbank();
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Петрова',
            'firstname' => 'Мария',
            'email' => 'parent-invoice@example.com',
        ]);
        $this->student->update(['parent_id' => $parent->id]);
        $row = $this->charge();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.invoice-email.send'), $this->payload($row))
            ->assertRedirect(route('admin.settingPrices.indexMenu'))
            ->assertSessionHas('status', 'Счёт отправлен на parent-invoice@example.com.');
    }
}
