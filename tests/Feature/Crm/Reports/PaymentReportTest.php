<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Partner;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentSystem;
use App\Models\FiscalReceipt;
use App\Models\Team;
use App\Models\TinkoffCommissionRule;
use App\Models\User;
use App\Models\UserTableSetting;
use App\Models\PaymentIntent;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use App\Jobs\TinkoffProcessRefundJob;
use Tests\Feature\Crm\CrmTestCase;

class PaymentReportTest extends CrmTestCase
{
    private function dataTablesBaseParams(int $length = 50): array
    {
        // Индексы/имена колонок должны соответствовать DataTables-конфигу на странице отчёта.
        // Нам важно, чтобы name совпадал с тем, что мы переопределяем через orderColumn() в контроллере.
        return [
            'draw' => 1,
            'start' => 0,
            'length' => $length,
            'columns' => [
                ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'user_name', 'name' => 'user_name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'team_title', 'name' => 'team_title', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'summ', 'name' => 'summ', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'payment_month', 'name' => 'payment_month', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'operation_date', 'name' => 'operation_date', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'payment_provider', 'name' => 'payment_provider', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'payment_method_label', 'name' => 'payment_method_label', 'searchable' => 'false', 'orderable' => 'false'],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin(); // реальные права reports.view
    }

    /**
     * (P0) Доступ к странице отчёта только при праве reports.view.
     *
     * Если права нет — 403.
     */
    public function test_payments_page_requires_reports_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);

        $response = $this->get(route('payments'));

        $response->assertForbidden();
    }

    /**
     * (P0) Сумма totalPaidPrice учитывает только текущего партнёра.
     * (P1) Форматирование totalPaidPrice (разделители тысяч).
     */
    public function test_payments_page_totalPaidPrice_uses_only_current_partner_payments_and_is_formatted(): void
    {
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
        // Сценарий 1: есть payments.user_name, но приоритет у ФИО пользователя (lastname+name)
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

        // 1) user_name из ФИО пользователя (если заполнено), иначе из payments.user_name
        $this->assertNotNull($rowWithCustomName);
        $expectedUserFio = trim(($this->user->lastname ?? '') . ' ' . ($this->user->name ?? ''));
        $this->assertNotSame('', $expectedUserFio);
        // DataTables по умолчанию HTML-экранирует все колонки (XSS-защита).
        $actualUserName = html_entity_decode(
            (string) ($rowWithCustomName['user_name'] ?? ''),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $this->assertEquals($expectedUserFio, $actualUserName);
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
        $this->assertSame('', (string) ($robokassaRow['payment_method_label'] ?? ''));
        $this->assertSame('', (string) ($tbankRow['payment_method_label'] ?? ''));
    }

    /**
     * (P1) Колонка payment_method_label — из payment_intents.payment_method по payment_number / provider_inv_id.
     */
    public function test_getPayments_payment_method_label_joins_tbank_intent(): void
    {
        $bankPaymentIdQr = 556_677_881;
        $paymentQr = Payment::factory()->create([
            'user_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'summ' => 500.00,
            'operation_date' => now()->toDateTimeString(),
            'deal_id' => 'deal-qr',
            'payment_id' => (string) $bankPaymentIdQr,
            'payment_number' => (string) $bankPaymentIdQr,
            'payment_status' => 'CONFIRMED',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => null,
            'provider' => 'tbank',
            'provider_inv_id' => $bankPaymentIdQr,
            'payment_method' => 'sbp_qr',
            'status' => 'paid',
            'out_sum' => '500.00',
        ]);

        $bankPaymentIdCard = 556_677_882;
        $paymentCard = Payment::factory()->create([
            'user_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'summ' => 300.00,
            'operation_date' => now()->subHour()->toDateTimeString(),
            'deal_id' => 'deal-card',
            'payment_id' => (string) $bankPaymentIdCard,
            'payment_number' => (string) $bankPaymentIdCard,
            'payment_status' => 'CONFIRMED',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => null,
            'provider' => 'tbank',
            'provider_inv_id' => $bankPaymentIdCard,
            'payment_method' => 'card',
            'status' => 'paid',
            'out_sum' => '300.00',
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'));

        $response->assertOk();
        $data = collect($response->json('data') ?? []);

        $rowQr = $data->firstWhere('id', $paymentQr->id);
        $rowCard = $data->firstWhere('id', $paymentCard->id);

        $this->assertNotNull($rowQr);
        $this->assertNotNull($rowCard);
        $this->assertSame('QR (СБП)', $rowQr['payment_method_label']);
        $this->assertSame('Карта', $rowCard['payment_method_label']);
    }

    /**
     * (P0) Колонки receipt_url/has_receipt и return_receipt_url/has_return_receipt работают по правилам:
     * - только URL вида https://receipts.ru/... считается валидным;
     * - robokassa не получает ссылку чека;
     * - при нескольких чеках берётся последний (по id) отдельно по каждому типу (income vs income_return).
     */
    public function test_getPayments_receipt_columns_follow_tbank_and_receipts_ru_rules(): void
    {
        $tbankWithValidReceipt = Payment::factory()->create([
            'user_id' => $this->user->id,
            'deal_id' => 'deal-valid',
            'payment_id' => null,
            'payment_status' => null,
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $tbankWithValidReceipt->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => (float) $tbankWithValidReceipt->summ,
            'receipt_url' => 'https://receipts.ru/first-old-receipt',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $tbankWithValidReceipt->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => (float) $tbankWithValidReceipt->summ,
            'receipt_url' => 'https://receipts.ru/latest-valid-receipt',
        ]);

        // Чек возврата (income_return)
        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $tbankWithValidReceipt->id,
            'type' => FiscalReceipt::TYPE_INCOME_RETURN,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => (float) $tbankWithValidReceipt->summ,
            'receipt_url' => 'https://receipts.ru/latest-return-receipt',
        ]);

        $tbankWithInvalidReceipt = Payment::factory()->create([
            'user_id' => $this->user->id,
            'deal_id' => 'deal-invalid',
            'payment_id' => null,
            'payment_status' => null,
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $tbankWithInvalidReceipt->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => (float) $tbankWithInvalidReceipt->summ,
            'receipt_url' => 'https://example.com/not-allowed',
        ]);

        // Некорректный return чек (не receipts.ru => должен игнорироваться в UI-колонке)
        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $tbankWithInvalidReceipt->id,
            'type' => FiscalReceipt::TYPE_INCOME_RETURN,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => (float) $tbankWithInvalidReceipt->summ,
            'receipt_url' => 'https://example.com/not-allowed-return',
        ]);

        $robokassaPayment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'deal_id' => null,
            'payment_id' => null,
            'payment_status' => null,
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $robokassaPayment->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => (float) $robokassaPayment->summ,
            'receipt_url' => 'https://receipts.ru/robokassa-should-not-be-used-in-ui',
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $robokassaPayment->id,
            'type' => FiscalReceipt::TYPE_INCOME_RETURN,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => (float) $robokassaPayment->summ,
            'receipt_url' => 'https://receipts.ru/robokassa-return-should-not-be-used-in-ui',
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'));

        $response->assertOk();
        $data = collect($response->json('data') ?? []);

        $rowValid = $data->firstWhere('id', $tbankWithValidReceipt->id);
        $rowInvalid = $data->firstWhere('id', $tbankWithInvalidReceipt->id);
        $rowRobokassa = $data->firstWhere('id', $robokassaPayment->id);

        $this->assertNotNull($rowValid);
        $this->assertEquals('tbank', $rowValid['payment_provider']);
        $this->assertTrue((bool) ($rowValid['has_receipt'] ?? false));
        $this->assertSame('https://receipts.ru/latest-valid-receipt', $rowValid['receipt_url']);
        $this->assertTrue((bool) ($rowValid['has_return_receipt'] ?? false));
        $this->assertSame('https://receipts.ru/latest-return-receipt', $rowValid['return_receipt_url']);

        $this->assertNotNull($rowInvalid);
        $this->assertEquals('tbank', $rowInvalid['payment_provider']);
        $this->assertFalse((bool) ($rowInvalid['has_receipt'] ?? true));
        $this->assertNull($rowInvalid['receipt_url']);
        $this->assertFalse((bool) ($rowInvalid['has_return_receipt'] ?? true));
        $this->assertNull($rowInvalid['return_receipt_url']);

        $this->assertNotNull($rowRobokassa);
        $this->assertEquals('robokassa', $rowRobokassa['payment_provider']);
        $this->assertTrue((bool) ($rowRobokassa['has_receipt'] ?? false));
        $this->assertSame('https://receipts.ru/robokassa-should-not-be-used-in-ui', $rowRobokassa['receipt_url']);
        $this->assertTrue((bool) ($rowRobokassa['has_return_receipt'] ?? false));
        $this->assertSame('https://receipts.ru/robokassa-return-should-not-be-used-in-ui', $rowRobokassa['return_receipt_url']);
    }

    /**
     * (P0) По умолчанию выдача отсортирована по operation_date DESC
     * (ближайшие платежи сверху), если order не передан.
     */
    public function test_getPayments_default_sort_is_operation_date_descending_when_no_order_param(): void
    {
        $p1 = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 100,
            'operation_date' => '2026-01-03 10:00:00',
        ]);
        $p2 = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 200,
            'operation_date' => '2026-01-01 10:00:00',
        ]);
        $p3 = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 300,
            'operation_date' => '2026-01-02 10:00:00',
        ]);

        $params = $this->dataTablesBaseParams();
        unset($params['order']);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments', $params));

        $response->assertOk();
        $data = collect($response->json('data') ?? []);

        $ids = $data->pluck('id')->map(fn($v) => (int) $v)->values()->all();

        // Ожидаем: 2026-01-03, 2026-01-02, 2026-01-01
        $this->assertSame([$p1->id, $p3->id, $p2->id], array_slice($ids, 0, 3));
    }

    /**
     * (P0) Явная сортировка работает (пример: summ ASC).
     */
    public function test_getPayments_sorting_works_for_summ_column(): void
    {
        $low = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 100,
            'operation_date' => '2026-01-01 10:00:00',
        ]);
        $high = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 999,
            'operation_date' => '2026-01-01 10:00:01',
        ]);

        $params = $this->dataTablesBaseParams();
        $params['order'] = [
            ['column' => 3, 'dir' => 'asc'], // summ
        ];

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments', $params));

        $response->assertOk();
        $data = collect($response->json('data') ?? []);

        $ids = $data->pluck('id')->map(fn($v) => (int) $v)->values()->all();
        $this->assertSame($low->id, $ids[0] ?? null);
        $this->assertSame($high->id, $ids[1] ?? null);
    }

    /**
     * (P0) Явная сортировка работает для ФИО (user_name ASC).
     *
     * В проекте ФИО формируется из users.lastname + users.name с приоритетом
     * перед payments.user_name.
     */
    public function test_getPayments_sorting_works_for_user_name_column(): void
    {
        $uA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Adams',
            'name' => 'Bob',
        ]);
        $uZ = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Zimmer',
            'name' => 'Anna',
        ]);

        // Специально задаём payments.user_name, чтобы проверить приоритет ФИО из users
        $pA = Payment::factory()->create([
            'user_id' => $uA->id,
            'user_name' => 'ZZZ Payment Name',
            'summ' => 100,
            'operation_date' => '2026-01-01 10:00:00',
        ]);
        $pZ = Payment::factory()->create([
            'user_id' => $uZ->id,
            'user_name' => 'AAA Payment Name',
            'summ' => 200,
            'operation_date' => '2026-01-01 10:00:00',
        ]);

        $params = $this->dataTablesBaseParams();
        $params['order'] = [
            ['column' => 1, 'dir' => 'asc'], // user_name (ФИО)
        ];

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments', $params));

        $response->assertOk();
        $data = collect($response->json('data') ?? []);

        $ids = $data->pluck('id')->map(fn($v) => (int) $v)->values()->all();
        $this->assertSame($pA->id, $ids[0] ?? null);
        $this->assertSame($pZ->id, $ids[1] ?? null);
    }

    /**
     * (P1) bank_commission_total — базовый сценарий расчёта.
     * (P1) Колонка commission_total (bank + platform).
     * (P1) Колонка net_to_partner.
     */
    public function test_getPayments_commissions_and_net_to_partner_are_calculated_for_tbank_payments(): void
    {
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
     * (P0) Доступ к настройкам колонок только при reports.view.
     *
     * Без права — 403 для GET и POST.
     */
    public function test_columns_settings_endpoints_require_reports_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);

        $getResponse = $this->get('/admin/reports/payments/columns-settings');
        $getResponse->assertForbidden();

        $postResponse = $this->postJson('/admin/reports/payments/columns-settings', [
            'columns' => ['user_name' => true],
        ]);
        $postResponse->assertForbidden();
    }

    /**
     * (P0) Для пользователя с правом все основные эндпоинты отчёта доступны (200).
     */
    public function test_payments_report_endpoints_return_200_for_authorized_user(): void
    {
        $this->get(route('payments'))->assertOk();

        $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'))
            ->assertOk();

        $this->get('/admin/reports/payments/columns-settings')->assertOk();

        $this->postJson('/admin/reports/payments/columns-settings', [
            'columns' => ['user_name' => true, 'receipt' => true],
        ])->assertOk();

        // tbank-history только при viewing.all.logs
        $now = now();
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId('viewing.all.logs'),
            ],
            ['created_at' => $now, 'updated_at' => $now]
        );

        $payment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'summ' => 1000.00,
            'deal_id' => 'deal-for-history',
            'payment_id' => '123456',
            'payment_status' => 'CONFIRMED',
        ]);

        $this->get(route('payments.tbankHistory', ['payment' => $payment->id]))
            ->assertOk();

        // refund endpoint возвращает 200 при разрешённом кейсе (п payout уже REJECTED)
        Queue::fake([TinkoffProcessRefundJob::class]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '100.00',
            'currency' => 'RUB',
            'status' => 'paid',
        ]);

        $paymentForRefund = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'summ' => 100.00,
            'deal_id' => 'deal-for-refund',
            'payment_id' => '12346',
            'payment_status' => 'CONFIRMED',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum' => '100.00',
            'payment_date' => 'Клубный взнос',
            'provider_inv_id' => 12346,
            'tbank_payment_id' => 12346,
        ]);

        TinkoffPayout::create([
            'payment_id' => (int) $paymentForRefund->id,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-for-refund',
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'REJECTED',
        ]);

        $resp = $this->postJson(route('payments.refund', ['payment' => $paymentForRefund->id]), []);
        $resp->assertOk();
        $resp->assertJsonFragment(['message' => 'refund_created']);
    }

    /**
     * (P1) getColumnsSettings возвращает пустой массив по умолчанию.
     */
    public function test_getColumnsSettings_returns_empty_array_by_default(): void
    {
        $response = $this->get('/admin/reports/payments/columns-settings');

        $response->assertOk();
        $this->assertSame([], $response->json());
    }

    /**
     * (P1) getColumnsSettings возвращает сохранённые настройки именно для текущего пользователя.
     */
    public function test_getColumnsSettings_returns_saved_settings_for_current_user_only(): void
    {
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

    /**
     * (P0) getPayments под middleware reports.view — без права 403.
     */
    public function test_getPayments_forbidden_without_reports_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);

        $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'))
            ->assertForbidden();
    }

    /**
     * (P0) Возврат платежа — только при reports.view.
     */
    public function test_payments_refund_forbidden_without_reports_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);

        $payment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $actor->id,
            'summ' => 10.00,
        ]);

        $this
            ->postJson(route('payments.refund', ['payment' => $payment->id]), [])
            ->assertForbidden();
    }

    /**
     * (P0) История T‑Bank — 403 без права viewing.all.logs (при наличии reports.view).
     */
    public function test_tbank_history_forbidden_without_viewing_all_logs_even_with_reports_view(): void
    {
        $payment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'summ' => 100.00,
            'deal_id' => 'deal-acl',
            'payment_id' => '111',
            'payment_status' => 'CONFIRMED',
        ]);

        $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.tbankHistory', ['payment' => $payment->id]))
            ->assertForbidden();
    }

    /**
     * (P0) В HTML колонки «Действия» кнопка «История» (T‑Bank) только при viewing.all.logs.
     */
    public function test_getPayments_tbank_history_button_depends_on_viewing_all_logs_permission(): void
    {
        $ps = new PaymentSystem();
        $ps->partner_id = $this->partner->id;
        $ps->name = 'tbank';
        $ps->save();

        $payment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'summ' => 100.00,
            'deal_id' => 'deal-btn',
            'payment_id' => '222',
            'payment_status' => 'CONFIRMED',
        ]);

        $fetchRefundActionHtml = function () use ($payment): string {
            $response = $this
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('payments.getPayments'));

            $response->assertOk();
            $data = collect($response->json('data') ?? []);
            $row = $data->firstWhere('id', $payment->id);
            $this->assertNotNull($row, 'Платёж должен быть в выдаче getPayments.');

            return (string) ($row['refund_action'] ?? '');
        };

        $htmlWithoutLogs = $fetchRefundActionHtml();
        $this->assertStringNotContainsString('js-tbank-history-btn', $htmlWithoutLogs);
        $this->assertStringNotContainsString('>История</button>', $htmlWithoutLogs);

        $this->grantPermissionToCurrentUserRole('viewing.all.logs');

        $htmlWithLogs = $fetchRefundActionHtml();
        $this->assertStringContainsString('js-tbank-history-btn', $htmlWithLogs);
        $this->assertStringContainsString('>История</button>', $htmlWithLogs);
    }

    /**
     * (P1) Модальное окно истории T‑Bank в вёрстке страницы только при viewing.all.logs.
     */
    public function test_payments_page_includes_tbank_history_modal_only_when_can_view_all_logs(): void
    {
        $ps = new PaymentSystem();
        $ps->partner_id = $this->partner->id;
        $ps->name = 'tbank';
        $ps->save();

        $htmlNoLogs = $this->get(route('payments'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="tbankHistoryModal"', $htmlNoLogs);

        $this->grantPermissionToCurrentUserRole('viewing.all.logs');

        $htmlWithLogs = $this->get(route('payments'))->assertOk()->getContent();
        $this->assertStringContainsString('id="tbankHistoryModal"', $htmlWithLogs);
    }

    /**
     * (P1) Чекбоксы колонок комиссий / к выплате / статус возврата: disabled без reports.additional.value.view.
     */
    public function test_payments_page_sensitive_column_toggles_disabled_without_reports_additional_value_permission(): void
    {
        $ps = new PaymentSystem();
        $ps->partner_id = $this->partner->id;
        $ps->name = 'tbank';
        $ps->save();

        $html = $this->get(route('payments'))->assertOk()->getContent();

        foreach (
            [
                'payColBankCommission',
                'payColPlatformCommission',
                'payColCommissionTotal',
                'payColNetToPartner',
                'payColRefundStatus',
            ] as $inputId
        ) {
            $this->assertCheckboxInputHasDisabledAttribute($html, $inputId, true);
        }

        $this->assertCheckboxInputHasDisabledAttribute($html, 'payColPayout', false);
    }

    /**
     * (P1) С правом reports.additional.value.view чекбоксы комиссий и статуса возврата не disabled.
     */
    public function test_payments_page_sensitive_column_toggles_enabled_with_reports_additional_value_permission(): void
    {
        $ps = new PaymentSystem();
        $ps->partner_id = $this->partner->id;
        $ps->name = 'tbank';
        $ps->save();

        $this->grantPermissionToCurrentUserRole('reports.additional.value.view');

        $html = $this->get(route('payments'))->assertOk()->getContent();

        foreach (
            [
                'payColBankCommission',
                'payColPlatformCommission',
                'payColCommissionTotal',
                'payColNetToPartner',
                'payColRefundStatus',
            ] as $inputId
        ) {
            $this->assertCheckboxInputHasDisabledAttribute($html, $inputId, false);
        }
    }

    /**
     * (P0) Полный доступ: страница и все HTTP-эндпоинты отчёта «Платежи» дают 200 при нужных правах.
     *
     * Покрывает: reports.view, viewing.all.logs (история), сценарий refund, настройки колонок, DataTables.
     */
    public function test_payments_report_page_and_all_endpoints_return_200_for_fully_authorized_user(): void
    {
        $this->grantPermissionToCurrentUserRole('viewing.all.logs');
        $this->grantPermissionToCurrentUserRole('reports.additional.value.view');

        $ps = new PaymentSystem();
        $ps->partner_id = $this->partner->id;
        $ps->name = 'tbank';
        $ps->save();

        $this->get(route('payments'))->assertOk();

        $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'))
            ->assertOk();

        $this->get('/admin/reports/payments/columns-settings')->assertOk();

        $this->postJson('/admin/reports/payments/columns-settings', [
            'columns' => [
                'user_name' => true,
                'receipt' => true,
                'bank_commission_total' => true,
                'refund_status' => true,
            ],
        ])->assertOk();

        $paymentHistory = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'summ' => 1000.00,
            'deal_id' => 'deal-full-stack',
            'payment_id' => '333444',
            'payment_status' => 'CONFIRMED',
        ]);

        $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.tbankHistory', ['payment' => $paymentHistory->id]))
            ->assertOk();

        Queue::fake([TinkoffProcessRefundJob::class]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '100.00',
            'currency' => 'RUB',
            'status' => 'paid',
        ]);

        $paymentRefund = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'summ' => 100.00,
            'deal_id' => 'deal-full-refund',
            'payment_id' => '55566',
            'payment_status' => 'CONFIRMED',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum' => '100.00',
            'payment_date' => 'Клубный взнос',
            'provider_inv_id' => 55566,
            'tbank_payment_id' => 55566,
        ]);

        TinkoffPayout::create([
            'payment_id' => (int) $paymentRefund->id,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-full-refund',
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'REJECTED',
        ]);

        $this
            ->postJson(route('payments.refund', ['payment' => $paymentRefund->id]), [])
            ->assertOk()
            ->assertJsonFragment(['message' => 'refund_created']);
    }

    public function test_reports_payments_users_search_scoped_to_partner_and_matches_name_or_lastname(): void
    {
        $otherPartner = Partner::factory()->create();
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Анна',
            'lastname' => 'УникальнаяФамилия',
        ]);
        User::factory()->create([
            'partner_id' => $otherPartner->id,
            'name' => 'Анна',
            'lastname' => 'УникальнаяФамилия',
        ]);

        $response = $this->getJson(route('reports.payments.users.search', ['q' => 'Уникальн']));
        $response->assertOk();
        $response->assertJsonStructure(['results']);
        $ids = collect($response->json('results'))->pluck('id')->all();
        $this->assertContains($student->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_reports_payments_teams_search_scoped_to_partner(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа Уникальная ' . uniqid(),
        ]);
        Team::factory()->create([
            'partner_id' => Partner::factory()->create()->id,
            'title' => $team->title,
        ]);

        $response = $this->getJson(route('reports.payments.teams.search', ['q' => 'Уникальная']));
        $response->assertOk();
        $ids = collect($response->json('results'))->pluck('id')->all();
        $this->assertContains($team->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_get_payments_filters_by_filter_user_id(): void
    {
        $u = User::factory()->create(['partner_id' => $this->partner->id]);
        $other = User::factory()->create(['partner_id' => $this->partner->id]);

        $pMine = Payment::factory()->create(['user_id' => $u->id]);
        Payment::factory()->create(['user_id' => $other->id]);

        $params = array_merge($this->dataTablesBaseParams(), [
            'filter_user_id' => (string) $u->id,
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments', $params));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($pMine->id, $data[0]['id'] ?? null);
    }

    private function grantPermissionToCurrentUserRole(string $permissionName): void
    {
        $now = now();
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permissionName),
            ],
            ['created_at' => $now, 'updated_at' => $now]
        );
    }

    private function assertCheckboxInputHasDisabledAttribute(string $html, string $elementId, bool $expectDisabled): void
    {
        $this->assertSame(
            1,
            preg_match('/<input\b[^>]*\bid="' . preg_quote($elementId, '/') . '"[^>]*>/i', $html, $m),
            'Не найден input #' . $elementId
        );

        $tag = $m[0];
        $hasDisabled = preg_match('/\bdisabled\b/i', $tag) === 1;

        $this->assertSame(
            $expectDisabled,
            $hasDisabled,
            $expectDisabled
                ? 'Ожидался атрибут disabled у #' . $elementId
                : 'Не ожидался disabled у #' . $elementId . ', тег: ' . $tag
        );
    }
}