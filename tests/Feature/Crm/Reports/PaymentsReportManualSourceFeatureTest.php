<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Models\UserTableSetting;
use Tests\Feature\Crm\CrmTestCase;

final class PaymentsReportManualSourceFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private Payment $gatewayPayment;

    private UserPrice $manualMonth;

    private UserLessonPackage $manualPackage;

    private UserCustomPayment $manualCustom;

    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа ручной оплаты',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Ручнов',
            'name' => 'Илья',
            'is_enabled' => 1,
        ]);

        $this->gatewayPayment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'summ_cents' => 50000,
            'payment_month' => '2026-04-01',
            'operation_date' => '2026-04-02 10:00:00',
            'deal_id' => null,
            'payment_id' => null,
            'payment_status' => null,
            'team_id' => $this->team->id,
            'team_title' => $this->team->title,
        ]);

        $this->manualMonth = UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-03-01',
            'price_cents' => 10000,
            'is_paid' => 0,
            'is_manual_paid' => true,
            'manual_paid_at' => '2026-03-15 12:00:00',
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Абонемент ручной',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 25000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $this->manualPackage = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $this->team->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 25000,
            'is_paid' => false,
            'is_manual_paid' => true,
            'manual_paid_at' => '2026-03-16 12:00:00',
            'created_by' => $this->user->id,
        ]);

        $this->manualCustom = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount_cents' => 7500,
            'is_paid' => false,
            'is_manual_paid' => true,
            'manual_paid_at' => '2026-03-17 12:00:00',
            'note' => 'Форма',
        ]);

        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 99900,
            'is_paid' => 0,
            'is_manual_paid' => null,
        ]);
        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-01-01',
            'price_cents' => 88800,
            'is_paid' => 1,
            'is_manual_paid' => false,
            'manual_paid_at' => '2026-01-10 12:00:00',
        ]);

        $disabled = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Отключеннов',
            'name' => 'Пётр',
            'is_enabled' => 0,
        ]);
        UserPrice::query()->create([
            'user_id' => $disabled->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-03-01',
            'price_cents' => 11100,
            'is_paid' => 0,
            'is_manual_paid' => true,
            'manual_paid_at' => '2026-03-18 12:00:00',
        ]);

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
        ]);
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);
        UserPrice::query()->create([
            'user_id' => $foreignStudent->id,
            'team_id' => $foreignTeam->id,
            'new_month' => '2026-03-01',
            'price_cents' => 22200,
            'is_paid' => 0,
            'is_manual_paid' => true,
            'manual_paid_at' => '2026-03-19 12:00:00',
        ]);
    }

    public function test_payments_page_shows_source_filter_default_all(): void
    {
        $html = $this->get(route('payments'))->assertOk()->getContent();

        $this->assertStringContainsString('id="pay-filter-source"', $html);
        $this->assertStringContainsString('Источник оплаты', $html);
        $this->assertStringContainsString('value="" selected>Все</option>', $html);
        $this->assertStringContainsString('value="gateway"', $html);
        $this->assertStringContainsString('>Платёжная система</option>', $html);
        $this->assertStringContainsString('value="manual"', $html);
        $this->assertStringContainsString('>Ручная оплата</option>', $html);
    }

    public function test_default_list_includes_gateway_and_three_manual_sources(): void
    {
        $rows = $this->rows();

        $this->assertCount(4, $rows);
        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['id'] === (int) $this->gatewayPayment->id));
        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['id'] === -$this->manualMonth->id
            && $row['payment_method_label'] === 'Ручная оплата'
            && $row['payment_provider'] === ''
            && (float) $row['summ'] === 100.0
            && $row['payment_month'] === '2026-03-01'
            && $row['team_title'] === 'Группа ручной оплаты'
            && $row['refund_actions_available'] === false));
        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['id'] === -(1000000000 + $this->manualPackage->id)
            && $row['payment_month'] === 'Абонемент'
            && (float) $row['summ'] === 250.0));
        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['id'] === -(2000000000 + $this->manualCustom->id)
            && $row['payment_month'] === 'Дополнительный платеж'
            && (float) $row['summ'] === 75.0));
    }

    public function test_source_filter_splits_gateway_and_manual_rows(): void
    {
        $manual = $this->rows(['payment_source' => 'manual']);
        $this->assertCount(3, $manual);
        $this->assertFalse($manual->contains(fn (array $row) => (int) $row['id'] === (int) $this->gatewayPayment->id));

        $gateway = $this->rows(['payment_source' => 'gateway']);
        $this->assertCount(1, $gateway);
        $this->assertSame((int) $this->gatewayPayment->id, (int) $gateway->first()['id']);
        $this->assertSame('robokassa', $gateway->first()['payment_provider']);
    }

    public function test_provider_and_method_filters_drop_manual_rows(): void
    {
        $robokassa = $this->rows(['payment_provider' => 'robokassa']);
        $this->assertCount(1, $robokassa);
        $this->assertSame((int) $this->gatewayPayment->id, (int) $robokassa->first()['id']);

        $this->assertCount(0, $this->rows(['payment_provider' => 'tbank']));
        $this->assertCount(0, $this->rows([
            'payment_source' => 'manual',
            'payment_method' => 'card',
        ]));
    }

    public function test_month_and_operation_date_filters_apply_to_manual_rows(): void
    {
        $month = $this->rows(['payment_month' => '2026-03']);
        $this->assertCount(1, $month);
        $this->assertSame(-$this->manualMonth->id, (int) $month->first()['id']);

        $dates = $this->rows([
            'operation_date_from' => '2026-03-01',
            'operation_date_to' => '2026-03-31',
        ]);
        $this->assertCount(3, $dates);
    }

    public function test_inactive_manual_row_is_hidden_until_status_is_all(): void
    {
        $this->assertFalse($this->rows()->contains(fn (array $row) => ($row['user_name'] ?? '') === 'Отключеннов Пётр'));

        $all = $this->rows(['status' => '']);
        $this->assertTrue($all->contains(fn (array $row) => ($row['user_name'] ?? '') === 'Отключеннов Пётр'));
    }

    public function test_toolbar_sum_includes_manual_rows_until_source_is_gateway(): void
    {
        $all = $this->getJson(route('reports.payments.total', ['status' => 'active']))->assertOk();
        $this->assertEquals(925.0, (float) $all->json('sum_payments_raw'));

        $gateway = $this->getJson(route('reports.payments.total', [
            'status' => 'active',
            'payment_source' => 'gateway',
        ]))->assertOk();
        $this->assertEquals(500.0, (float) $gateway->json('sum_payments_raw'));

        $manual = $this->getJson(route('reports.payments.total', [
            'status' => 'active',
            'payment_source' => 'manual',
        ]))->assertOk();
        $this->assertEquals(425.0, (float) $manual->json('sum_payments_raw'));
    }

    public function test_invalid_source_is_rejected_under_the_field_and_valid_source_is_stored(): void
    {
        $this->postJson(route('reports.payments.filters.save'), [
            'payment_source' => 'cash',
        ])->assertStatus(422)
            ->assertJsonPath('errors.payment_source.0', 'Выберите источник оплаты: все, платёжная система или ручная оплата.');

        $this->postJson(route('reports.payments.filters.save'), [
            'payment_source' => 'manual',
        ])->assertOk();

        $row = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_payments')
            ->first();
        $this->assertSame('manual', $row->filters['payment_source']);

        $html = $this->get(route('payments'))->assertOk()->getContent();
        $this->assertStringContainsString('value="manual" selected', $html);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function rows(array $query = []): \Illuminate\Support\Collection
    {
        $query += [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
        ];

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments', $query));

        $response->assertOk();

        return collect($response->json('data') ?? []);
    }
}
