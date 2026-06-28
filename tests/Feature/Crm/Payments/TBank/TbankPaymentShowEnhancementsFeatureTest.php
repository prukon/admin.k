<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\FiscalReceipt;
use App\Models\PartnerLegalEntity;
use App\Models\Payment;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Карточка платежа T‑Bank: юр. лицо, timeline, контроль доступа (manage.payment.method.tbank).
 */
final class TbankPaymentShowEnhancementsFeatureTest extends CrmTestCase
{
    private TinkoffPayment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->payment = TinkoffPayment::create([
            'order_id' => 'order-enhancements-' . uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-enhancements-' . uniqid(),
            'confirmed_at' => Carbon::parse('2026-06-20 14:30:00'),
        ]);
        $this->payment->forceFill(['created_at' => Carbon::parse('2026-06-20 14:00:00')])->save();
    }

    public function test_guest_cannot_access_payment_admin_endpoints(): void
    {
        Auth::logout();

        foreach ($this->paymentAdminRoutes() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'text/html']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_tbank_manage_permission_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('manage.payment.method.tbank', $this->partner);
        $this->actingAs($actor);

        foreach ($this->paymentAdminRoutes() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'text/html']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без manage.payment.method.tbank: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_tbank_manage_permission_gets_200_on_index_and_show(): void
    {
        $actor = $this->createUserWithoutPermission('manage.payment.method.tbank', $this->partner);
        $this->grantTbankManage((int) $actor->role_id);
        $this->actingAs($actor);

        $this->get('/admin/tinkoff/payments')
            ->assertOk();

        $this->get('/admin/tinkoff/payments/' . $this->payment->id)
            ->assertOk()
            ->assertSee('Ход платежа и выплаты', false)
            ->assertSee('tbank-payment-timeline', false);
    }

    public function test_show_displays_legal_entity_organization_from_snapshot(): void
    {
        $this->asSuperadmin();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Краткое название',
            'organization_name' => 'ООО Карточка Платежа',
        ]);

        $this->payment->update(['legal_entity_id' => $entity->id]);

        $this->get('/admin/tinkoff/payments/' . $this->payment->id)
            ->assertOk()
            ->assertSee('Организация:', false)
            ->assertSee('ООО Карточка Платежа', false);
    }

    public function test_show_legal_entity_falls_back_to_title_when_organization_name_empty(): void
    {
        $this->asSuperadmin();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'АНО Спорт',
            'organization_name' => null,
        ]);

        $this->payment->update(['legal_entity_id' => $entity->id]);

        $this->get('/admin/tinkoff/payments/' . $this->payment->id)
            ->assertOk()
            ->assertSee('АНО Спорт', false);
    }

    public function test_show_legal_entity_displays_dash_when_no_snapshot(): void
    {
        $this->asSuperadmin();

        $this->get('/admin/tinkoff/payments/' . $this->payment->id)
            ->assertOk()
            ->assertSee('Организация:', false)
            ->assertSee('Организация: <strong>—</strong>', false);
    }

    public function test_show_timeline_renders_all_steps_and_done_states_without_payout(): void
    {
        $this->asSuperadmin();

        $this->get('/admin/tinkoff/payments/' . $this->payment->id)
            ->assertOk()
            ->assertSee('Платёжный запрос', false)
            ->assertSee('Оплата подтверждена', false)
            ->assertSee('Создана выплата', false)
            ->assertSee('Выплата выполнена', false)
            ->assertSee('20.06.2026 14:00', false)
            ->assertSee('20.06.2026 14:30', false)
            ->assertSee('tbank-payment-timeline__step--done', false)
            ->assertSee('tbank-payment-timeline__step--pending', false)
            ->assertSee('Deal ' . $this->payment->deal_id, false);
    }

    public function test_show_displays_fiscal_receipt_link_when_valid_url_exists(): void
    {
        $this->asSuperadmin();

        $this->payment->update(['tinkoff_payment_id' => 261000001]);

        $ledgerPayment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payment_number' => '261000001',
            'deal_id' => $this->payment->deal_id,
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledgerPayment->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => 100.00,
            'receipt_url' => 'https://receipts.ru/payment-show-income',
        ]);

        $this->get('/admin/tinkoff/payments/' . $this->payment->id)
            ->assertOk()
            ->assertSee('Фискальный чек:', false)
            ->assertSee('https://receipts.ru/payment-show-income', false)
            ->assertSee('Открыть чек', false);
    }

    public function test_show_displays_fiscal_receipt_pending_hint_without_valid_url(): void
    {
        $this->asSuperadmin();

        $this->payment->update(['tinkoff_payment_id' => 261000002]);

        $ledgerPayment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payment_number' => '261000002',
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledgerPayment->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_QUEUED,
            'amount' => 100.00,
        ]);

        $this->get('/admin/tinkoff/payments/' . $this->payment->id)
            ->assertOk()
            ->assertSee('Фискальный чек:', false)
            ->assertSee('Чек формируется (CloudKassir)', false);
    }

    public function test_show_timeline_marks_payout_steps_done_when_completed(): void
    {
        $this->asSuperadmin();

        $payout = TinkoffPayout::create([
            'payment_id' => $this->payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => $this->payment->deal_id,
            'amount' => 9000,
            'is_final' => true,
            'status' => 'COMPLETED',
            'source' => 'auto',
            'completed_at' => Carbon::parse('2026-06-21 10:05:00'),
        ]);
        $payout->forceFill(['created_at' => Carbon::parse('2026-06-21 10:00:00')])->save();

        $html = $this->get('/admin/tinkoff/payments/' . $this->payment->id)
            ->assertOk()
            ->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('21.06.2026 10:00', $html);
        $this->assertStringContainsString('21.06.2026 10:05', $html);
        $this->assertGreaterThanOrEqual(
            4,
            substr_count($html, 'tbank-payment-timeline__step tbank-payment-timeline__step--done'),
            'Все 4 шага timeline должны быть выполнены'
        );
    }

    public function test_show_timeline_marks_failed_payment_step_when_canceled(): void
    {
        $this->asSuperadmin();

        $this->payment->update([
            'status' => 'CANCELED',
            'canceled_at' => Carbon::parse('2026-06-20 15:00:00'),
            'confirmed_at' => null,
        ]);

        $this->get('/admin/tinkoff/payments/' . $this->payment->id)
            ->assertOk()
            ->assertSee('tbank-payment-timeline__step--failed', false)
            ->assertSee('Оплата отменена', false);
    }

    public function test_show_timeline_marks_active_payout_step_when_in_progress(): void
    {
        $this->asSuperadmin();

        TinkoffPayout::create([
            'payment_id' => $this->payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => $this->payment->deal_id,
            'amount' => 9000,
            'is_final' => true,
            'status' => 'CREDIT_CHECKING',
            'source' => 'manual',
            'created_at' => now(),
        ]);

        $this->get('/admin/tinkoff/payments/' . $this->payment->id)
            ->assertOk()
            ->assertSee('tbank-payment-timeline__step--active', false)
            ->assertSee('Статус: CREDIT_CHECKING', false);
    }

    /**
     * @return list<array{method: string, url: string, headers?: array<string, string>}>
     */
    private function paymentAdminRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => '/admin/tinkoff/payments',
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => '/admin/tinkoff/payments/' . $this->payment->id,
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }

    private function grantTbankManage(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'permission_id' => $this->permissionId('manage.payment.method.tbank'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
