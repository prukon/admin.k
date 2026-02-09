<?php

namespace Tests\Feature\Crm;

use Tests\TestCase;
use App\Models\User;
use App\Models\Partner;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

class LtvReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID пользователя, которому разрешён доступ к reports-view.
     * Управляется из тестов.
     */
    protected ?int $reportsViewUserId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Подменяем проверку ability reports-view через Gate::before.
        // Это изолировано в рамках этого тестового класса и не требует знания
        // твоей реальной системы ролей/прав.
        Gate::before(function ($user, string $ability) {
            if ($ability === 'reports-view' && $this->reportsViewUserId !== null) {
                return $user->id === $this->reportsViewUserId;
            }

            return null;
        });
    }

    /**
     * Имитация выбора текущего партнёра так же, как это делает суперюзер через селект:
     * в сессию пишется current_partner, а AppServiceProvider уже биндит app('current_partner').
     */
    protected function setCurrentPartner(Partner $partner): void
    {
        // как в реальном приложении — сохраняем ID партнёра в сессии
        $this->session(['current_partner' => $partner->id]);

        // дополнительно кладём сам объект в контейнер, если он где-то используется напрямую
        app()->instance('current_partner', $partner);
    }

    protected function giveReportsViewPermission(User $user): void
    {
        $this->reportsViewUserId = $user->id;
    }

    /**
     * [P1] Доступ к маршрутам LTV только при праве reports-view.
     */
    public function test_ltv_routes_require_reports_view_permission(): void
    {
        $partner = Partner::factory()->create();
        $this->setCurrentPartner($partner);

        // Пользователь без разрешения
        $userWithout = User::factory()->create([
            'partner_id' => $partner->id,
        ]);

        $this->reportsViewUserId = null; // никому не даём доступ
        $this->actingAs($userWithout);

        $this->get(route('ltv'))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv'))
            ->assertForbidden();

        // Пользователь с разрешением
        $userWith = User::factory()->create([
            'partner_id' => $partner->id,
        ]);
        $this->giveReportsViewPermission($userWith);
        $this->actingAs($userWith);

        $this->get(route('ltv'))->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv'))
            ->assertOk();
    }

    /**
     * [P1] LTV отчёт отфильтрован по текущему партнёру.
     */
    public function test_ltv_report_filtered_by_current_partner(): void
    {
        $partnerA = Partner::factory()->create();
        $partnerB = Partner::factory()->create();

        // Пользователь с правом, привяжем к партнёру A (это не влияет на фильтрацию отчёта)
        $userWith = User::factory()->create([
            'partner_id' => $partnerA->id,
        ]);
        $this->giveReportsViewPermission($userWith);
        $this->actingAs($userWith);

        // Юзеры разных партнёров
        $userA = User::factory()->create([
            'partner_id' => $partnerA->id,
        ]);

        $userB = User::factory()->create([
            'partner_id' => $partnerB->id,
        ]);

        // Платежи для обоих
        Payment::factory()->create([
            'user_id'        => $userA->id,
            'summ'           => 100,
            'operation_date' => Carbon::now()->subDays(3),
        ]);

        Payment::factory()->create([
            'user_id'        => $userB->id,
            'summ'           => 200,
            'operation_date' => Carbon::now()->subDays(2),
        ]);

        // current_partner = A → в отчёте только userA
        $this->setCurrentPartner($partnerA);

        $responseA = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv', ['draw' => 1]))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($responseA['data']);
        $userIdsA = collect($responseA['data'])->pluck('user_id')->all();
        $this->assertContains($userA->id, $userIdsA);
        $this->assertNotContains($userB->id, $userIdsA);

        // current_partner = B → в отчёте только userB
        $this->setCurrentPartner($partnerB);

        $responseB = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv', ['draw' => 2]))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($responseB['data']);
        $userIdsB = collect($responseB['data'])->pluck('user_id')->all();
        $this->assertContains($userB->id, $userIdsB);
        $this->assertNotContains($userA->id, $userIdsB);
    }

    /**
     * [P1] Учитываются только платежи с положительной суммой (summ > 0).
     */
    public function test_ltv_includes_only_positive_payments(): void
    {
        $partner = Partner::factory()->create();
        $this->setCurrentPartner($partner);

        $userWith = User::factory()->create(['partner_id' => $partner->id]);
        $this->giveReportsViewPermission($userWith);
        $this->actingAs($userWith);

        $user = User::factory()->create([
            'partner_id' => $partner->id,
        ]);

        // Положительный, нулевой и отрицательный платеж
        Payment::factory()->create([
            'user_id'        => $user->id,
            'summ'           => 100,
            'operation_date' => Carbon::now()->subDays(3),
        ]);

        Payment::factory()->create([
            'user_id'        => $user->id,
            'summ'           => 0,
            'operation_date' => Carbon::now()->subDays(2),
        ]);

        Payment::factory()->create([
            'user_id'        => $user->id,
            'summ'           => -50,
            'operation_date' => Carbon::now()->subDay(),
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv', ['draw' => 1]))
            ->assertOk()
            ->json();

        $row = collect($response['data'])->firstWhere('user_id', $user->id);
        $this->assertNotNull($row);
        $this->assertEquals(100.0, (float) $row['total_price']);
        $this->assertEquals(1, (int) $row['payment_count']);
    }

    /**
     * [P1] Агрегация LTV по пользователю: корректная сумма и количество платежей.
     */
    public function test_ltv_aggregates_sum_and_payment_count_per_user(): void
    {
        $partner = Partner::factory()->create();
        $this->setCurrentPartner($partner);

        $userWith = User::factory()->create(['partner_id' => $partner->id]);
        $this->giveReportsViewPermission($userWith);
        $this->actingAs($userWith);

        $user = User::factory()->create([
            'partner_id' => $partner->id,
        ]);

        // Три положительных платежа
        Payment::factory()->create([
            'user_id'        => $user->id,
            'summ'           => 100,
            'operation_date' => Carbon::parse('2025-01-01'),
        ]);
        Payment::factory()->create([
            'user_id'        => $user->id,
            'summ'           => 150,
            'operation_date' => Carbon::parse('2025-01-10'),
        ]);
        Payment::factory()->create([
            'user_id'        => $user->id,
            'summ'           => 50,
            'operation_date' => Carbon::parse('2025-01-20'),
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv', ['draw' => 1]))
            ->assertOk()
            ->json();

        $row = collect($response['data'])->firstWhere('user_id', $user->id);
        $this->assertNotNull($row);
        $this->assertEquals(300.0, (float) $row['total_price']);
        $this->assertEquals(3, (int) $row['payment_count']);
    }

    /**
     * [P1] Корректные first_payment_date и last_payment_date.
     */
    public function test_ltv_first_and_last_payment_dates_are_correct(): void
    {
        $partner = Partner::factory()->create();
        $this->setCurrentPartner($partner);

        $userWith = User::factory()->create(['partner_id' => $partner->id]);
        $this->giveReportsViewPermission($userWith);
        $this->actingAs($userWith);

        $user = User::factory()->create([
            'partner_id' => $partner->id,
        ]);

        $dates = [
            Carbon::parse('2025-01-05 10:00:00'),
            Carbon::parse('2025-01-01 09:00:00'),
            Carbon::parse('2025-02-01 12:00:00'),
        ];

        foreach ($dates as $date) {
            Payment::factory()->create([
                'user_id'        => $user->id,
                'summ'           => 100,
                'operation_date' => $date,
            ]);
        }

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv', ['draw' => 1]))
            ->assertOk()
            ->json();

        $row = collect($response['data'])->firstWhere('user_id', $user->id);
        $this->assertNotNull($row);

        $this->assertEquals(
            $dates[1]->toDateTimeString(),
            Carbon::parse($row['first_payment_date'])->toDateTimeString()
        );

        $this->assertEquals(
            $dates[2]->toDateTimeString(),
            Carbon::parse($row['last_payment_date'])->toDateTimeString()
        );
    }

    /**
     * [P1] Пустой отчёт: нет платежей у текущего партнёра.
     */
    public function test_ltv_empty_report_returns_correct_datatables_structure(): void
    {
        $partner = Partner::factory()->create();
        $this->setCurrentPartner($partner);

        $userWith = User::factory()->create(['partner_id' => $partner->id]);
        $this->giveReportsViewPermission($userWith);
        $this->actingAs($userWith);

        // Платежей для партнёра нет

        $draw = 3;

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv', ['draw' => $draw]))
            ->assertOk()
            ->json();

        $this->assertEquals($draw, $response['draw']);
        $this->assertEquals(0, $response['recordsTotal']);
        $this->assertEquals(0, $response['recordsFiltered']);
        $this->assertIsArray($response['data']);
        $this->assertCount(0, $response['data']);
    }

    /**
     * [P2] Формирование имени пользователя и fallback «Без имени».
     */
    public function test_ltv_user_name_format_and_fallback(): void
    {
        $partner = Partner::factory()->create();
        $this->setCurrentPartner($partner);

        $userWith = User::factory()->create(['partner_id' => $partner->id]);
        $this->giveReportsViewPermission($userWith);
        $this->actingAs($userWith);

        $userFull = User::factory()->create([
            'partner_id' => $partner->id,
            'lastname'   => 'Иванов',
            'name'       => 'Иван',
        ]);

        $userOnlyName = User::factory()->create([
            'partner_id' => $partner->id,
            'lastname'   => '',
            'name'       => 'Петя',
        ]);

        $userNoName = User::factory()->create([
            'partner_id' => $partner->id,
            'lastname'   => '',
            'name'       => '',
        ]);

        foreach ([$userFull, $userOnlyName, $userNoName] as $u) {
            Payment::factory()->create([
                'user_id'        => $u->id,
                'summ'           => 100,
                'operation_date' => Carbon::now()->subDay(),
            ]);
        }

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv', ['draw' => 1]))
            ->assertOk()
            ->json();

        $data = collect($response['data'])->keyBy('user_id');

        $this->assertEquals('Иванов Иван', $data[$userFull->id]['user_name']);
        $this->assertEquals('Петя', $data[$userOnlyName->id]['user_name']);
        $this->assertEquals('Без имени', $data[$userNoName->id]['user_name']);
    }

    /**
     * [P2] Формат данных для DataTables в getLtv.
     */
    public function test_ltv_datatables_json_structure(): void
    {
        $partner = Partner::factory()->create();
        $this->setCurrentPartner($partner);

        $userWith = User::factory()->create(['partner_id' => $partner->id]);
        $this->giveReportsViewPermission($userWith);
        $this->actingAs($userWith);

        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'lastname'   => 'Петров',
            'name'       => 'Пётр',
        ]);

        Payment::factory()->create([
            'user_id'        => $user->id,
            'summ'           => 123.45,
            'operation_date' => Carbon::now()->subDay(),
        ]);

        $draw = 5;

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv', ['draw' => $draw]))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('draw', $response);
        $this->assertArrayHasKey('recordsTotal', $response);
        $this->assertArrayHasKey('recordsFiltered', $response);
        $this->assertArrayHasKey('data', $response);

        $this->assertEquals($draw, $response['draw']);
        $this->assertGreaterThanOrEqual(1, $response['recordsTotal']);
        $this->assertGreaterThanOrEqual(1, $response['recordsFiltered']);

        $this->assertIsArray($response['data']);
        $row = collect($response['data'])->firstWhere('user_id', $user->id);
        $this->assertNotNull($row);

        $this->assertArrayHasKey('user_name', $row);
        $this->assertArrayHasKey('total_price', $row);
        $this->assertArrayHasKey('payment_count', $row);
        $this->assertArrayHasKey('first_payment_date', $row);
        $this->assertArrayHasKey('last_payment_date', $row);
        $this->assertArrayHasKey('DT_RowIndex', $row);

        $this->assertEquals('Петров Пётр', $row['user_name']);
        $this->assertEquals(123.45, (float) $row['total_price']);
    }

    /**
     * [P2] Только AJAX-доступ к данным LTV.
     */
    public function test_ltv_getltv_available_only_via_ajax(): void
    {
        $partner = Partner::factory()->create();
        $this->setCurrentPartner($partner);

        $userWith = User::factory()->create(['partner_id' => $partner->id]);
        $this->giveReportsViewPermission($userWith);
        $this->actingAs($userWith);

        // Без AJAX-заголовка — 404
        $this->get(route('ltv.getLtv'))
            ->assertStatus(404);

        // С AJAX-заголовком — 200
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('ltv.getLtv', ['draw' => 1]))
            ->assertOk();
    }
}