<?php

namespace Tests\Feature\Crm;

use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class MyPaymentsReportTest extends CrmTestCase
{
    /**
     * Хелпер: разрешить доступ к "моим платежам" только для конкретного пользователя.
     */
    protected function allowMyPaymentsViewForUser(User $allowedUser): void
    {
        Gate::define('myPayments-view', function (User $user) use ($allowedUser) {
            return $user->id === $allowedUser->id;
        });
    }

    /**
     * [P1] Доступ к вкладке только с правом myPayments-view.
     * - другой пользователь без права → 403
     * - текущий пользователь с правом → 200
     */
    public function test_my_payments_routes_require_myPayments_view_permission(): void
    {
        // Разрешаем доступ только для $this->user
        $this->allowMyPaymentsViewForUser($this->user);

        // Пользователь без права (другой)
        $otherUser = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);
        $this->actingAs($otherUser);

        $this->get(route('showUserPayments'))->assertForbidden();
        $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getUserPayments'))
            ->assertForbidden();

        // Пользователь с правом
        $this->actingAs($this->user);

        $this->get(route('showUserPayments'))->assertOk();
        $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getUserPayments'))
            ->assertOk();
    }

    /**
     * [P1] showUserPayments — суммирование только оплат текущего пользователя.
     */
    public function test_show_user_payments_counts_only_current_user_payments(): void
    {
        $this->allowMyPaymentsViewForUser($this->user);

        // Платежи текущего пользователя
        Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ'    => 1_000,
        ]);
        Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ'    => 2_000,
        ]);

        // Платежи другого пользователя (не должны попасть в сумму)
        $otherUser = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        Payment::factory()->create([
            'user_id' => $otherUser->id,
            'summ'    => 5_000,
        ]);

        $response = $this->get(route('showUserPayments'));

        $response->assertOk();
        $response->assertViewIs('user.report.payment');

        // Ожидаемая сумма только по текущему пользователю: 1000 + 2000 = 3000
        $expectedTotal = number_format(3_000, 0, '', ' '); // "3 000"

        $response->assertViewHas('totalPaidPrice', $expectedTotal);
    }

    /**
     * [P1] showUserPayments — корректное отображение, когда нет оплат.
     */
    public function test_show_user_payments_returns_zero_when_user_has_no_payments(): void
    {
        $this->allowMyPaymentsViewForUser($this->user);

        // Платежей для текущего пользователя нет
        $response = $this->get(route('showUserPayments'));

        $response->assertOk();
        $response->assertViewIs('user.report.payment');

        // sum() по пустому набору → 0, number_format(0, ...) → "0"
        $response->assertViewHas('totalPaidPrice', '0');
    }

    /**
     * [P1] getUserPayments — возвращает только платежи текущего пользователя.
     */
    public function test_get_user_payments_returns_only_current_user_payments(): void
    {
        $this->allowMyPaymentsViewForUser($this->user);

        // Платежи текущего пользователя
        Payment::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        // Платежи другого пользователя (не должны попасть в ответ)
        $otherUser = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        Payment::factory()->count(3)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getUserPayments'));

        $response->assertOk();

        $data = $response->json('data');

        // В ответе только платежи текущего пользователя
        $this->assertIsArray($data);
        $this->assertCount(2, $data);

        foreach ($data as $row) {
            // user_id колонка, добавленная через addColumn('user_id', ...)
            $this->assertEquals($this->user->id, $row['user_id']);
        }
    }

    /**
     * [P1] Структура JSON для DataTables.
     */
    public function test_get_user_payments_returns_valid_datatables_json_structure(): void
    {
        $this->allowMyPaymentsViewForUser($this->user);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ'    => 1_500,
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getUserPayments'));

        $response->assertOk();

        $json = $response->json();

        // Основная структура
        $this->assertArrayHasKey('data', $json);
        $this->assertIsArray($json['data']);
        $this->assertNotEmpty($json['data']);

        $row = $json['data'][0];

        // Проверяем наличие колонок, добавленных в DataTables
        $this->assertArrayHasKey('user_name', $row);
        $this->assertArrayHasKey('user_id', $row);
        $this->assertArrayHasKey('team_title', $row);
        $this->assertArrayHasKey('summ', $row);
        $this->assertArrayHasKey('operation_date', $row);
        // Индексная колонка от addIndexColumn()
        $this->assertArrayHasKey('DT_RowIndex', $row);
    }

    /**
     * [P1] user_name берётся из payments, при отсутствии — из связанного пользователя.
     */
    public function test_get_user_payments_user_name_prefers_payments_column_over_user_relation(): void
    {
        $this->allowMyPaymentsViewForUser($this->user);

        // У пользователя есть имя в модели
        $this->user->name = 'Имя из User';
        $this->user->save();

        // Платёж №1: user_name прописан в payments
        $paymentWithCustomName = Payment::factory()->create([
            'user_id'   => $this->user->id,
            'user_name' => 'Имя из payments',
        ]);

        // Платёж №2: user_name = null, берём name из пользователя
        $paymentWithoutName = Payment::factory()->create([
            'user_id'   => $this->user->id,
            'user_name' => null,
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getUserPayments'));

        $response->assertOk();

        $data = $response->json('data');

        // Находим строки по id
        $rowWithCustomName = collect($data)->firstWhere('id', $paymentWithCustomName->id);
        $rowWithoutName    = collect($data)->firstWhere('id', $paymentWithoutName->id);

        $this->assertNotNull($rowWithCustomName);
        $this->assertNotNull($rowWithoutName);

        // 1) Если user_name в payments не пустой — используем его
        $this->assertEquals('Имя из payments', $rowWithCustomName['user_name']);

        // 2) Если user_name отсутствует — используем имя из связанного user
        $this->assertEquals('Имя из User', $rowWithoutName['user_name']);
    }

    /**
     * [P1] user_name = "Без пользователя", если нет связанного пользователя.
     *
     * Здесь создаём платёж с user_id текущего пользователя, затем удаляем пользователя из БД,
     * чтобы связь $row->user была null, а user_name в payments — null.
     */
    public function test_get_user_payments_user_name_is_fallback_when_no_related_user(): void
    {
        $this->allowMyPaymentsViewForUser($this->user);

        // user_name = null => пойдём в ветку с $row->user или "Без пользователя"
        $payment = Payment::factory()->create([
            'user_id'   => $this->user->id,
            'user_name' => null,
        ]);

        // Удаляем запись пользователя из таблицы users.
        // В тестах guard хранит уже загруженную модель, поэтому auth()->user()
        // остаётся доступен, а связь Payment->user станет null.
        $this->user->delete();

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getUserPayments'));

        $response->assertOk();

        $data = $response->json('data');

        $row = collect($data)->firstWhere('id', $payment->id);
        $this->assertNotNull($row);

        $this->assertEquals('Без пользователя', $row['user_name']);
    }

    /**
     * [P1] team_title — команда берётся из user->team или "Без команды".
     *
     * В этом тесте проверяем кейс, когда у пользователя есть команда.
     */
    public function test_get_user_payments_team_title_from_user_team_or_default_when_absent(): void
    {
        $this->allowMyPaymentsViewForUser($this->user);

        // Создаём команду и привязываем к текущему пользователю
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Команда 1',
        ]);

        $this->user->team()->associate($team);
        $this->user->save();

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getUserPayments'));

        $response->assertOk();

        $data = $response->json('data');

        $row = collect($data)->firstWhere('id', $payment->id);
        $this->assertNotNull($row);

        $this->assertEquals('Команда 1', $row['team_title']);
    }

    /**
     * [P2] Пустой список платежей — корректный JSON без ошибок.
     */
    public function test_get_user_payments_returns_empty_data_array_when_no_payments(): void
    {
        $this->allowMyPaymentsViewForUser($this->user);

        // Платежей нет
        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getUserPayments'));

        $response->assertOk();

        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    /**
     * [P3] Нe-AJAX запрос к /getUserPayments — текущее поведение (не JSON, ajax() = false).
     *
     * Сейчас метод просто ничего не возвращает, поэтому ожидаем 200 и пустой контент.
     * Тест фиксирует это поведение на будущее.
     */
    public function test_get_user_payments_non_ajax_request_current_behavior_is_preserved(): void
    {
        $this->allowMyPaymentsViewForUser($this->user);

        $response = $this->get(route('payments.getUserPayments'));

        $response->assertStatus(200);
        $this->assertSame('', $response->getContent());
    }
}