<?php

namespace Tests\Feature\Crm;

use App\Models\Partner;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentSystem;
use App\Models\TinkoffCommissionRule;
use App\Models\User;
use App\Models\UserTableSetting;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;

class PaymentReportTest extends CrmTestCase
{
    /**
     * Флаг, определяющий, есть ли у текущего пользователя право reports-view.
     * Управляем им из тестов.
     */
    protected static bool $canReportsView = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Определяем способность reports-view один раз на класс:
        // она просто читает статический флаг.
        Gate::define('reports-view', function (?User $user = null) {
            return self::$canReportsView;
        });
    }

    /**
     * (P0) Доступ к странице отчёта только при праве reports-view.
     *
     * Если права нет — 403.
     */
    public function test_payments_page_requires_reports_view_permission(): void
    {
        // Права нет
        self::$canReportsView = false;

        $response = $this->get(route('payments'));

        $response->assertForbidden();
    }

    /**
     * (P0) Сумма totalPaidPrice учитывает только текущего партнёра.
     * (P1) Форматирование totalPaidPrice (разделители тысяч).
     */
    public function test_payments_page_totalPaidPrice_uses_only_current_partner_payments_and_is_formatted(): void
    {
        self::$canReportsView = true;

        // Платежи текущего партнёра
        $payment1 = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 1000,
        ]);

        $userSamePartner = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $payment2 = Payment::factory()->create([
            'user_id' => $userSamePartner->id,
            'summ' => 2000,
        ]);

        // Платёж другого партнёра (не должен попасть в сумму)
        $otherPartner = Partner::factory()->create();
        $otherUser = User::factory()->create([
            'partner_id' => $otherPartner->id,
        ]);

        Payment::factory()->create([
            'user_id' => $otherUser->id,
            'summ' => 5000,
        ]);

        $expectedTotal = $payment1->summ + $payment2->summ;
        $expectedFormatted = number_format($expectedTotal, 0, '', ' ');

        $response = $this->get(route('payments'));

        $response
            ->assertOk()
            ->assertViewHas('totalPaidPrice', $expectedFormatted);
    }

    /**
     * (P1) Флаг tbankEnabled зависит от настроек платёжной системы.
     */
    public function test_payments_page_tbankEnabled_depends_on_payment_system_settings(): void
    {
        self::$canReportsView = true;

        // 1) Когда настройки tbank отсутствуют
        $response = $this->get(route('payments'));
        $response
            ->assertOk()
            ->assertViewHas('tbankEnabled', false);

        // 2) Когда есть PaymentSystem с name = 'tbank' для текущего партнёра
        $ps = new PaymentSystem();
        $ps->partner_id = $this->partner->id;
        $ps->name = 'tbank';
        $ps->save();

        $response = $this->get(route('payments'));
        $response
            ->assertOk()
            ->assertViewHas('tbankEnabled', true);
    }

    /**
     * (P0) Только AJAX-доступ к getPayments.
     */
    public function test_getPayments_requires_ajax_request(): void
    {
        self::$canReportsView = true;

        // Не-AJAX запрос -> 404
        $response = $this->get(route('payments.getPayments'));
        $response->assertNotFound();

        // AJAX-запрос -> 200
        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'));

        $response->assertOk();
    }

    /**
     * (P0) Фильтрация платежей по партнёру в getPayments.
     *
     * Здесь используем PayableFactory::paidMonthlyWithAllRelations(),
     * чтобы создать «правильную» цепочку оплаты.
     */
    public function test_getPayments_returns_only_current_partner_payments(): void
    {
        self::$canReportsView = true;

        // Платёж текущего партнёра
        $payableCurrent = Payable::factory()
            ->state([
                'partner_id' => $this->partner->id,
                'user_id' => $this->user->id,
            ])
            ->paidMonthlyWithAllRelations()
            ->create();

        /** @var Payment $paymentForCurrentPartner */
        $paymentForCurrentPartner = Payment::where('user_id', $this->user->id)->firstOrFail();

        // Платёж другого партнёра
        $otherPartner = Partner::factory()->create();
        $otherUser = User::factory()->create([
            'partner_id' => $otherPartner->id,
        ]);

        $payableOther = Payable::factory()
            ->state([
                'partner_id' => $otherPartner->id,
                'user_id' => $otherUser->id,
            ])
            ->paidMonthlyWithAllRelations()
            ->create();

        $paymentForOtherPartner = Payment::where('user_id', $otherUser->id)->firstOrFail();

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'));

        $response->assertOk();

        $data = collect($response->json('data') ?? []);

        // В выборке должен быть платёж текущего партнёра
        $this->assertTrue(
            $data->contains(fn($row) => (int)($row['id'] ?? 0) === $paymentForCurrentPartner->id),
            'Ожидался платёж текущего партнёра в выдаче getPayments.'
        );

        // И не должно быть платежа другого партнёра
        $this->assertFalse(
            $data->contains(fn($row) => (int)($row['id'] ?? 0) === $paymentForOtherPartner->id),
            'Платёж другого партнёра не должен попадать в выдачу getPayments.'
        );
    }

    /**
     * (P1) Колонка user_name — порядок приоритетов.
     * (P1) Колонка user_id.
     * (P1) Колонка team_title.
     */
    public function test_getPayments_user_related_columns_are_filled_in_priority_order(): void
    {
        self::$canReportsView = true;

        // Сценарий 1: есть payments.user_name
        $paymentWithCustomName = Payment::factory()->create([
            'user_id' => $this->user->id,
            'user_name' => 'Custom Payment Name',
            'summ' => 1000,
        ]);

        // Сценарий 2: user_name пустой, есть user с ФИО и командой
        $teamUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванов',
            'name' => 'Пётр',
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Команда А',
        ]);
        $teamUser->team_id = $team->id;
        $teamUser->save();

        $paymentWithUser = Payment::factory()->create([
            'user_id' => $teamUser->id,
            'user_name' => null,
            'summ' => 2000,
        ]);

        // Сценарий 3: пользователь есть, но без ФИО и без команды
        $userNoData = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => '',
            'name' => '',
            'team_id' => null,
        ]);

        $paymentNoData = Payment::factory()->create([
            'user_id' => $userNoData->id,
            'user_name' => null,
            'summ' => 500,
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'));

        $response->assertOk();
        $data = collect($response->json('data') ?? []);

        $rowWithCustomName = $data->firstWhere('id', $paymentWithCustomName->id);
        $rowWithUser = $data->firstWhere('id', $paymentWithUser->id);
        $rowNoData = $data->firstWhere('id', $paymentNoData->id);

        // 1) user_name из payments.user_name
        $this->assertNotNull($rowWithCustomName);
        $this->assertEquals('Custom Payment Name', $rowWithCustomName['user_name']);
        $this->assertEquals($this->user->id, $rowWithCustomName['user_id']);

        // 2) user_name из ФИО пользователя, team_title из команды
        $this->assertNotNull($rowWithUser);
        $this->assertEquals('Иванов Пётр', $rowWithUser['user_name']);
        $this->assertEquals($teamUser->id, $rowWithUser['user_id']);
        $this->assertEquals('Команда А', $rowWithUser['team_title']);

        // 3) Без ФИО и команды -> "Без пользователя" / "Без команды"
        $this->assertNotNull($rowNoData);
        $this->assertEquals('Без пользователя', $rowNoData['user_name']);
        $this->assertEquals($userNoData->id, $rowNoData['user_id']);
        $this->assertEquals('Без команды', $rowNoData['team_title']);
    }

    /**
     * (P1) Колонки summ и operation_date.
     * (P0) Колонка payment_provider — определение провайдера.
     */
    public function test_getPayments_basic_payment_columns_and_provider_are_returned_correctly(): void
    {
        self::$canReportsView = true;

        // Robokassa-платёж (все T-Bank поля пустые)
        $robokassaPayment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 1500.50,
            'operation_date' => now()->toDateTimeString(),
            'deal_id' => null,
            'payment_id' => null,
            'payment_status' => null,
        ]);

        // T-Bank платёж (хотя бы одно из полей заполнено)
        $tbankPayment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 2500.00,
            'operation_date' => now()->subDay()->toDateTimeString(),
            'deal_id' => '12345',
            'payment_id' => null,
            'payment_status' => null,
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'));

        $response->assertOk();
        $data = collect($response->json('data') ?? []);

        $robokassaRow = $data->firstWhere('id', $robokassaPayment->id);
        $tbankRow = $data->firstWhere('id', $tbankPayment->id);

        $this->assertNotNull($robokassaRow);
        $this->assertEquals((float)$robokassaPayment->summ, (float)$robokassaRow['summ']);
        $this->assertEquals($robokassaPayment->operation_date, $robokassaRow['operation_date']);
        $this->assertEquals('robokassa', $robokassaRow['payment_provider']);

        $this->assertNotNull($tbankRow);
        $this->assertEquals((float)$tbankPayment->summ, (float)$tbankRow['summ']);
        $this->assertEquals($tbankPayment->operation_date, $tbankRow['operation_date']);
        $this->assertEquals('tbank', $tbankRow['payment_provider']);
    }

    /**
     * (P1) bank_commission_total — базовый сценарий расчёта.
     * (P1) Колонка commission_total (bank + platform).
     * (P1) Колонка net_to_partner.
     */
    public function test_getPayments_commissions_and_net_to_partner_are_calculated_for_tbank_payments(): void
    {
        self::$canReportsView = true;

        // Создаём правило комиссий для текущего партнёра
        $rule = new TinkoffCommissionRule();
        $rule->partner_id = $this->partner->id;
        $rule->method = null;
        $rule->is_enabled = true;
        $rule->acquiring_percent = 2.5;
        $rule->acquiring_min_fixed = 3.5;
        $rule->payout_percent = 1.0;
        $rule->payout_min_fixed = 0.0;
        $rule->platform_percent = 5.0;
        $rule->platform_min_fixed = 1.0;

        // min_fixed пока ещё существует в БД — заполняем, чтобы не падать, если поле NOT NULL
        $rule->min_fixed = 0.0;

        $rule->save();

        // T-Bank платёж
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 1000.00, // руб
            'deal_id' => '999',
            'payment_id' => null,
            'payment_status' => null,
        ]);

        // Ожидаемые комиссии по формуле контроллера
        $grossCents = (int)round($payment->summ * 100);

        $bankAcceptFee = max(
            (int)round($grossCents * ($rule->acquiring_percent / 100)),
            (int)round($rule->acquiring_min_fixed * 100)
        );
        $bankPayoutFee = max(
            (int)round($grossCents * ($rule->payout_percent / 100)),
            (int)round($rule->payout_min_fixed * 100)
        );
        $platformFee = max(
            (int)round($grossCents * ($rule->platform_percent / 100)),
            (int)round($rule->platform_min_fixed * 100)
        );

        $expectedBankTotal = round(($bankAcceptFee + $bankPayoutFee) / 100, 2);
        $expectedCommissionAll = round(($bankAcceptFee + $bankPayoutFee + $platformFee) / 100, 2);
        $expectedNet = round(max(0, $grossCents - $bankAcceptFee - $bankPayoutFee - $platformFee) / 100, 2);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'));

        $response->assertOk();
        $data = collect($response->json('data') ?? []);

        $row = $data->firstWhere('id', $payment->id);
        $this->assertNotNull($row, 'Не найден платёж в выдаче getPayments.');

        $this->assertEquals($expectedBankTotal, (float)$row['bank_commission_total']);
        $this->assertEquals($expectedCommissionAll, (float)$row['commission_total']);
        $this->assertEquals($expectedNet, (float)$row['net_to_partner']);

        // Для Robokassa комиссий быть не должно
        $robokassaPayment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 500.0,
            'deal_id' => null,
            'payment_id' => null,
            'payment_status' => null,
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'));

        $data = collect($response->json('data') ?? []);

        $robokassaRow = $data->firstWhere('id', $robokassaPayment->id);
        $this->assertNotNull($robokassaRow);

        $this->assertNull($robokassaRow['bank_commission_total']);
        $this->assertNull($robokassaRow['commission_total']);
        $this->assertNull($robokassaRow['net_to_partner']);
    }

    /**
     * (P0) Доступ к настройкам колонок только при reports-view.
     *
     * Без права — 403 для GET и POST.
     */
    public function test_columns_settings_endpoints_require_reports_view_permission(): void
    {
        // Права нет
        self::$canReportsView = false;

        $getResponse = $this->get('/admin/reports/payments/columns-settings');
        $getResponse->assertForbidden();

        $postResponse = $this->postJson('/admin/reports/payments/columns-settings', [
            'columns' => ['user_name' => true],
        ]);
        $postResponse->assertForbidden();
    }

    /**
     * (P1) getColumnsSettings возвращает пустой массив по умолчанию.
     */
    public function test_getColumnsSettings_returns_empty_array_by_default(): void
    {
        self::$canReportsView = true;

        $response = $this->get('/admin/reports/payments/columns-settings');

        $response->assertOk();
        $this->assertSame([], $response->json());
    }

    /**
     * (P1) getColumnsSettings возвращает сохранённые настройки именно для текущего пользователя.
     */
    public function test_getColumnsSettings_returns_saved_settings_for_current_user_only(): void
    {
        self::$canReportsView = true;

        // Настройки другого пользователя
        $anotherUser = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $otherSetting = new UserTableSetting();
        $otherSetting->user_id = $anotherUser->id;
        $otherSetting->table_key = 'reports_payments';
        $otherSetting->columns = ['user_name' => false];
        $otherSetting->save();

        // Настройки текущего пользователя
        $mySetting = new UserTableSetting();
        $mySetting->user_id = $this->user->id;
        $mySetting->table_key = 'reports_payments';
        $mySetting->columns = ['user_name' => true, 'team_title' => false];
        $mySetting->save();

        $response = $this->get('/admin/reports/payments/columns-settings');

        $response->assertOk();
        $this->assertSame($mySetting->columns, $response->json());
    }

    /**
     * (P1) saveColumnsSettings валидирует и сохраняет columns как массив.
     */
    public function test_saveColumnsSettings_validates_and_stores_columns_array(): void
    {
        self::$canReportsView = true;

        // Невалидный запрос (нет columns) -> 422
        $invalid = $this->postJson('/admin/reports/payments/columns-settings', []);
        $invalid->assertStatus(422);

        // Валидный запрос
        $payload = [
            'columns' => [
                'user_name' => true,
                'team_title' => false,
            ],
        ];

        $response = $this->postJson('/admin/reports/payments/columns-settings', $payload);

        $response
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'reports_payments')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($payload['columns'], $setting->columns);
    }

    /**
     * (P1) Нормализация значений columns в boolean.
     */
    public function test_saveColumnsSettings_normalizes_column_values_to_boolean(): void
    {
        self::$canReportsView = true;

        $payload = [
            'columns' => [
                'col_true_string' => 'true',
                'col_false_string' => 'false',
                'col_one' => '1',
                'col_zero' => '0',
                'col_int_one' => 1,
                'col_int_zero' => 0,
                'col_null' => null,
                'col_garbage' => 'foobar',
            ],
        ];

        $response = $this->postJson('/admin/reports/payments/columns-settings', $payload);
        $response
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'reports_payments')
            ->firstOrFail();

        $this->assertSame(
            [
                'col_true_string' => true,
                'col_false_string' => false,
                'col_one' => true,
                'col_zero' => false,
                'col_int_one' => true,
                'col_int_zero' => false,
                'col_null' => false,
                'col_garbage' => false,
            ],
            $setting->columns
        );
    }
}