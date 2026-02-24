<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Tests\Feature\Crm\CrmTestCase;

class LtvReportTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * [P1] Доступ к маршрутам LTV только при праве reports.view.
     */
   
    /**
     * [P1] Доступ к маршрутам LTV только при праве reports.view.
     */
    public function test_ltv_routes_require_reports_view_permission(): void
    {
        // Пользователь без разрешения (роль user из CrmTestCase)
        $this->actingAs($this->user);
        $this->withSession(['current_partner' => $this->partner->id]);

        // было: route('reports.ltv.index')
        $this->get(route('reports.ltv'))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => 1]))
            ->assertForbidden();

        // Суперадмин — доступ есть (Gate::before в AuthServiceProvider)
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        // было: route('reports.ltv.index')
        $this->get(route('reports.ltv'))->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => 1]))
            ->assertOk();
    }
 

    /**
     * [P1] LTV отчёт отфильтрован по текущему партнёру.
     */
    public function test_ltv_report_filtered_by_current_partner(): void
    {
        $partnerA = Partner::factory()->create();
        $partnerB = Partner::factory()->create();

        // Переключать current_partner может только superadmin
        $actor = $this->createUserWithRole('superadmin', $partnerA);
        $this->actingAs($actor);

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
        $responseA = $this->withSession(['current_partner' => $partnerA->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => 1]))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($responseA['data']);
        $userIdsA = collect($responseA['data'])->pluck('user_id')->all();
        $this->assertContains($userA->id, $userIdsA);
        $this->assertNotContains($userB->id, $userIdsA);

        // current_partner = B → в отчёте только userB
        $responseB = $this->withSession(['current_partner' => $partnerB->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => 2]))
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
        $actor = $this->createUserWithRole('superadmin', $partner);
        $this->actingAs($actor);

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

        $response = $this->withSession(['current_partner' => $partner->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => 1]))
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
        $actor = $this->createUserWithRole('superadmin', $partner);
        $this->actingAs($actor);

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

        $response = $this->withSession(['current_partner' => $partner->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => 1]))
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
        $actor = $this->createUserWithRole('superadmin', $partner);
        $this->actingAs($actor);

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

        $response = $this->withSession(['current_partner' => $partner->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => 1]))
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
        $actor = $this->createUserWithRole('superadmin', $partner);
        $this->actingAs($actor);

        // Платежей для партнёра нет

        $draw = 3;

        $response = $this->withSession(['current_partner' => $partner->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => $draw]))
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
        $actor = $this->createUserWithRole('superadmin', $partner);
        $this->actingAs($actor);

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

        $response = $this->withSession(['current_partner' => $partner->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => 1]))
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
        $actor = $this->createUserWithRole('superadmin', $partner);
        $this->actingAs($actor);

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

        $response = $this->withSession(['current_partner' => $partner->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => $draw]))
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
        $actor = $this->createUserWithRole('superadmin', $partner);
        $this->actingAs($actor);

        // Без AJAX-заголовка — 404
        $this->withSession(['current_partner' => $partner->id])
            ->get(route('reports.ltv.data'))
            ->assertStatus(404);

        // С AJAX-заголовком — 200
        $this->withSession(['current_partner' => $partner->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.data', ['draw' => 1]))
            ->assertOk();
    }
}