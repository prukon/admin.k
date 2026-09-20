<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Support\Payments\EmailNewsletterPaymentSource;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Колонка и фильтр «Email рассылка» в «Платежи» и «Платежные запросы».
 *
 * @see docs/documentation/reports-payments.html
 * @see docs/documentation/reports-admin.html
 */
class ReportsEmailNewsletterSourceFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
    }

    public function test_payments_page_has_email_newsletter_column_and_filter(): void
    {
        $this->asAdmin();

        $this->get(route('payments'))
            ->assertOk()
            ->assertSee('id="pay-filter-email-newsletter"', false)
            ->assertSee('name="email_newsletter"', false)
            ->assertSee('data-column-key="email_newsletter"', false)
            ->assertSee('>Email рассылка<', false);
    }

    public function test_get_payments_marks_only_up_public_pay_as_email_newsletter(): void
    {
        $this->asAdmin();

        $fromEmail = $this->makeTbankPaymentWithIntentMeta(10001, [
            'method' => 'sbp',
            'up_public_pay' => true,
        ], 11100);
        $fromCabinet = $this->makeTbankPaymentWithIntentMeta(10002, [
            'method' => 'sbp',
        ], 22200);
        $fromPackageLink = $this->makeTbankPaymentWithIntentMeta(10003, [
            'ulp_public_pay' => true,
        ], 33300);

        $data = collect($this->getPaymentsJson()['data'] ?? [])->keyBy('id');

        $this->assertSame(EmailNewsletterPaymentSource::LABEL, $data[$fromEmail->id]['email_newsletter'] ?? null);
        $this->assertSame('', (string) ($data[$fromCabinet->id]['email_newsletter'] ?? 'missing'));
        $this->assertSame('', (string) ($data[$fromPackageLink->id]['email_newsletter'] ?? 'missing'));
    }

    public function test_get_payments_email_newsletter_filter_yes_and_no(): void
    {
        $this->asAdmin();

        $fromEmail = $this->makeTbankPaymentWithIntentMeta(10011, ['up_public_pay' => true], 15000);
        $fromCabinet = $this->makeTbankPaymentWithIntentMeta(10012, ['method' => 'sbp'], 25000);

        $yes = collect($this->getPaymentsJson(['email_newsletter' => '1'])['data'] ?? [])->pluck('id')->all();
        $this->assertContains($fromEmail->id, $yes);
        $this->assertNotContains($fromCabinet->id, $yes);

        $no = collect($this->getPaymentsJson(['email_newsletter' => '0'])['data'] ?? [])->pluck('id')->all();
        $this->assertContains($fromCabinet->id, $no);
        $this->assertNotContains($fromEmail->id, $no);
    }

    public function test_payments_total_respects_email_newsletter_filter(): void
    {
        $this->asAdmin();

        $this->makeTbankPaymentWithIntentMeta(10021, ['up_public_pay' => true], 40000);
        $this->makeTbankPaymentWithIntentMeta(10022, ['method' => 'card'], 60000);

        $yesTotal = $this->get(route('reports.payments.total', ['email_newsletter' => '1', 'status' => '']))
            ->assertOk();
        $this->assertEquals(400.0, (float) $yesTotal->json('sum_payments_raw'));

        $noTotal = $this->get(route('reports.payments.total', ['email_newsletter' => '0', 'status' => '']))
            ->assertOk();
        $this->assertEquals(600.0, (float) $noTotal->json('sum_payments_raw'));
    }

    public function test_payments_invalid_email_newsletter_filter_is_ignored(): void
    {
        $this->asAdmin();

        $fromEmail = $this->makeTbankPaymentWithIntentMeta(10031, ['up_public_pay' => true], 10000);
        $fromCabinet = $this->makeTbankPaymentWithIntentMeta(10032, [], 20000);

        $ids = collect($this->getPaymentsJson(['email_newsletter' => 'maybe'])['data'] ?? [])
            ->pluck('id')
            ->all();

        $this->assertContains($fromEmail->id, $ids);
        $this->assertContains($fromCabinet->id, $ids);
    }

    public function test_payment_intents_page_has_email_newsletter_column_and_filter(): void
    {
        $this->asSuperadmin();

        $this->get(route('reports.payment-intents.index'))
            ->assertOk()
            ->assertSee('id="pi-filter-email-newsletter"', false)
            ->assertSee('data-column-key="email_newsletter"', false)
            ->assertSee('>Email рассылка<', false);
    }

    public function test_get_payment_intents_marks_only_up_public_pay_as_email_newsletter(): void
    {
        $this->asSuperadmin();

        $fromEmail = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'provider' => 'tbank',
            'out_sum_cents' => 11100,
            'meta' => json_encode(['up_public_pay' => true], JSON_UNESCAPED_UNICODE),
        ]);
        $fromCabinet = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'provider' => 'tbank',
            'out_sum_cents' => 22200,
            'meta' => json_encode(['method' => 'sbp'], JSON_UNESCAPED_UNICODE),
        ]);
        $fromPackage = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'provider' => 'tbank',
            'out_sum_cents' => 33300,
            'meta' => json_encode(['ulp_public_pay' => true], JSON_UNESCAPED_UNICODE),
        ]);

        $rows = collect($this->getPaymentIntentsJson()['data'] ?? [])->keyBy('id');

        $this->assertSame(EmailNewsletterPaymentSource::LABEL, $rows[$fromEmail->id]['email_newsletter'] ?? null);
        $this->assertSame('', (string) ($rows[$fromCabinet->id]['email_newsletter'] ?? 'missing'));
        $this->assertSame('', (string) ($rows[$fromPackage->id]['email_newsletter'] ?? 'missing'));
    }

    public function test_get_payment_intents_email_newsletter_filter_and_total(): void
    {
        $this->asSuperadmin();

        $fromEmail = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'provider' => 'tbank',
            'out_sum_cents' => 70000,
            'meta' => json_encode(['up_public_pay' => true], JSON_UNESCAPED_UNICODE),
        ]);
        $fromCabinet = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'provider' => 'tbank',
            'out_sum_cents' => 30000,
            'meta' => json_encode(['method' => 'card'], JSON_UNESCAPED_UNICODE),
        ]);

        $yesIds = collect($this->getPaymentIntentsJson(['email_newsletter' => '1'])['data'] ?? [])
            ->pluck('id')
            ->all();
        $this->assertContains($fromEmail->id, $yesIds);
        $this->assertNotContains($fromCabinet->id, $yesIds);

        $noIds = collect($this->getPaymentIntentsJson(['email_newsletter' => '0'])['data'] ?? [])
            ->pluck('id')
            ->all();
        $this->assertContains($fromCabinet->id, $noIds);
        $this->assertNotContains($fromEmail->id, $noIds);

        $yesTotal = $this->get(route('reports.payment-intents.total', [
            'partner_id' => $this->partner->id,
            'email_newsletter' => '1',
        ]))->assertOk();
        $this->assertEquals(700.0, (float) $yesTotal->json('total_raw'));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function makeTbankPaymentWithIntentMeta(int $bankPaymentId, array $meta, int $summCents): Payment
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'summ_cents' => $summCents,
            'operation_date' => now()->toDateTimeString(),
            'deal_id' => 'deal-'.$bankPaymentId,
            'payment_id' => (string) $bankPaymentId,
            'payment_number' => (string) $bankPaymentId,
            'payment_status' => 'CONFIRMED',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => null,
            'provider' => 'tbank',
            'provider_inv_id' => $bankPaymentId,
            'payment_method' => 'sbp_qr',
            'status' => 'paid',
            'out_sum_cents' => $summCents,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function getPaymentsJson(array $extra = []): array
    {
        $params = array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
        ], $extra);

        return $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments', $params))
            ->assertOk()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function getPaymentIntentsJson(array $extra = []): array
    {
        $params = array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'partner_id' => $this->partner->id,
        ], $extra);

        return $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', $params))
            ->assertOk()
            ->json();
    }
}
