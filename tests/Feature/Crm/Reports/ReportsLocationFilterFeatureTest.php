<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фильтр по локации и smoke-доступ для отчётов:
 * payments/monthly, ltv, debts.
 */
final class ReportsLocationFilterFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantReportsViewOnly(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('reports.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    // -------------------------------------------------------------------------
    // Платежи по месяцам (/admin/reports/payments/monthly)
    // -------------------------------------------------------------------------

    public function test_payment_monthly_smoke_all_endpoints_return_ok_with_reports_and_locations_view(): void
    {
        $this->grantPermission('locations.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ' => 500,
            'payment_month' => '2025-01-01',
            'operation_date' => '2025-01-15 10:00:00',
        ]);

        $this->get(route('reports.payments.monthly'))->assertOk();

        $this->get(route('reports.payments.monthly.total'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->get(route('reports.payments.monthly.data', ['draw' => 1]))
            ->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->get(route('reports.payments.monthly.payments', [
                'yearMonth' => '2025-01',
                'mode' => 'subscription',
            ]))
            ->assertOk();
    }

    public function test_payment_monthly_routes_forbidden_without_reports_view(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.payments.monthly'))->assertForbidden();

        $this->get(route('reports.payments.monthly.total'))->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->get(route('reports.payments.monthly.data', ['draw' => 1]))
            ->assertForbidden();
    }

    public function test_payment_monthly_hides_location_filter_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantReportsViewOnly($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.payments.monthly'))
            ->assertOk()
            ->assertDontSee('id="pay-monthly-filter-location"', false);
    }

    public function test_payment_monthly_data_filters_by_payments_location_id_not_user_location(): void
    {
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locB->id,
        ]);

        Payment::factory()->forUser($student)->create([
            'location_id' => $locA->id,
            'summ' => 1111,
            'payment_month' => '2025-02-01',
            'operation_date' => '2025-02-10 12:00:00',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.data', [
                'draw' => 1,
                'filter_location_id' => $locA->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1111.0, (float) ($json['data'][0]['total_sum'] ?? 0));

        $jsonEmpty = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.data', [
                'draw' => 1,
                'filter_location_id' => $locB->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(0, $jsonEmpty['data'] ?? []);
    }

    public function test_payment_monthly_data_filter_location_none_returns_only_payments_without_location(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $withLoc = User::factory()->create(['partner_id' => $this->partner->id]);
        $withoutLoc = User::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        Payment::factory()->forUser($withLoc)->create([
            'location_id' => $loc->id,
            'summ' => 900,
            'payment_month' => '2025-03-01',
            'operation_date' => '2025-03-05 12:00:00',
        ]);
        Payment::factory()->forUser($withoutLoc)->create([
            'location_id' => null,
            'summ' => 400,
            'payment_month' => '2025-03-01',
            'operation_date' => '2025-03-06 12:00:00',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.data', [
                'draw' => 1,
                'filter_location_id' => 'none',
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['data'] ?? []);
        $this->assertSame(400.0, (float) ($json['data'][0]['total_sum'] ?? 0));
    }

    public function test_payment_monthly_month_detail_respects_location_filter(): void
    {
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $student = User::factory()->create(['partner_id' => $this->partner->id]);

        Payment::factory()->forUser($student)->create([
            'location_id' => $locA->id,
            'summ' => 777,
            'payment_month' => '2025-04-01',
            'operation_date' => '2025-04-12 12:00:00',
        ]);
        Payment::factory()->forUser($student)->create([
            'location_id' => null,
            'summ' => 111,
            'payment_month' => '2025-04-01',
            'operation_date' => '2025-04-13 12:00:00',
        ]);

        $resp = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', [
                'yearMonth' => '2025-04',
                'mode' => 'subscription',
                'filter_location_id' => $locA->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(1, $resp['payments'] ?? []);
        $this->assertSame(777.0, (float) ($resp['payments'][0]['summ'] ?? 0));
    }

    public function test_payment_monthly_ignores_location_filter_without_locations_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantReportsViewOnly($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        Payment::factory()->forUser($this->user)->create([
            'location_id' => $locA->id,
            'summ' => 100,
            'payment_month' => '2025-05-01',
            'operation_date' => '2025-05-01 12:00:00',
        ]);
        Payment::factory()->forUser($this->user)->create([
            'location_id' => $locB->id,
            'summ' => 200,
            'payment_month' => '2025-05-01',
            'operation_date' => '2025-05-02 12:00:00',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.data', [
                'draw' => 1,
                'filter_location_id' => $locA->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['data'] ?? []);
        $this->assertSame(300.0, (float) ($json['data'][0]['total_sum'] ?? 0));
    }

    // -------------------------------------------------------------------------
    // LTV (/admin/reports/ltv)
    // -------------------------------------------------------------------------

    public function test_ltv_smoke_all_endpoints_return_ok_with_reports_and_locations_view(): void
    {
        $this->grantPermission('locations.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ' => 300,
            'operation_date' => now()->subDay()->format('Y-m-d H:i:s'),
        ]);

        $this->get(route('reports.ltv'))->assertOk();

        $this->get(route('reports.ltv.total'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->get(route('reports.ltv.data', ['draw' => 1]))
            ->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->get(route('reports.ltv.user_payments', [
                'user' => $student->id,
            ]))
            ->assertOk();
    }

    public function test_ltv_routes_forbidden_without_reports_view(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.ltv'))->assertForbidden();
        $this->get(route('reports.ltv.total'))->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->get(route('reports.ltv.data', ['draw' => 1]))
            ->assertForbidden();
    }

    public function test_ltv_hides_location_filter_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantReportsViewOnly($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.ltv'))
            ->assertOk()
            ->assertDontSee('id="pay-ltv-filter-location"', false);
    }

    public function test_ltv_data_filters_by_payments_location_snapshot(): void
    {
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locB->id,
        ]);

        Payment::factory()->forUser($student)->create([
            'location_id' => $locA->id,
            'summ' => 1500,
            'operation_date' => '2025-06-01 10:00:00',
        ]);
        Payment::factory()->forUser($student)->create([
            'location_id' => $locB->id,
            'summ' => 500,
            'operation_date' => '2025-06-02 10:00:00',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.data', [
                'draw' => 1,
                'filter_location_id' => $locA->id,
            ]))
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('user_id', $student->id);
        $this->assertNotNull($row);
        $this->assertSame(1500.0, (float) $row['total_price']);
        $this->assertSame(1, (int) $row['payment_count']);
    }

    public function test_ltv_data_filter_location_none(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $u1 = User::factory()->create(['partner_id' => $this->partner->id]);
        $u2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        Payment::factory()->forUser($u1)->create([
            'location_id' => $loc->id,
            'summ' => 800,
            'operation_date' => '2025-07-01 10:00:00',
        ]);
        Payment::factory()->forUser($u2)->create([
            'location_id' => null,
            'summ' => 200,
            'operation_date' => '2025-07-02 10:00:00',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.data', [
                'draw' => 1,
                'filter_location_id' => 'none',
            ]))
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('user_id', $u2->id);
        $this->assertNotNull($row);
        $this->assertSame(200.0, (float) $row['total_price']);

        $this->assertNull(collect($json['data'] ?? [])->firstWhere('user_id', $u1->id));
    }

    public function test_ltv_user_payments_detail_respects_location_filter(): void
    {
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $student = User::factory()->create(['partner_id' => $this->partner->id]);

        Payment::factory()->forUser($student)->create([
            'location_id' => $locA->id,
            'summ' => 600,
            'operation_date' => '2025-08-01 10:00:00',
        ]);
        Payment::factory()->forUser($student)->create([
            'location_id' => null,
            'summ' => 50,
            'operation_date' => '2025-08-02 10:00:00',
        ]);

        $resp = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', [
                'user' => $student->id,
                'filter_location_id' => $locA->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(1, $resp['payments'] ?? []);
        $this->assertSame(600.0, (float) ($resp['payments'][0]['summ'] ?? 0));
    }

    public function test_ltv_ignores_location_filter_without_locations_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantReportsViewOnly($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        Payment::factory()->forUser($this->user)->create([
            'location_id' => $locA->id,
            'summ' => 100,
            'operation_date' => '2025-09-01 10:00:00',
        ]);
        Payment::factory()->forUser($this->user)->create([
            'location_id' => null,
            'summ' => 50,
            'operation_date' => '2025-09-02 10:00:00',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.data', [
                'draw' => 1,
                'filter_location_id' => $locA->id,
            ]))
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('user_id', $this->user->id);
        $this->assertNotNull($row);
        $this->assertSame(150.0, (float) $row['total_price']);
    }

    // -------------------------------------------------------------------------
    // Задолженности (/admin/reports/debts)
    // -------------------------------------------------------------------------

    public function test_debts_smoke_all_endpoints_return_ok_with_reports_and_locations_view(): void
    {
        Carbon::setTestNow('2026-02-15');
        $this->grantPermission('locations.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        DB::table('users_prices')->insert([
            'user_id' => $student->id,
            'is_paid' => 0,
            'price' => 500,
            'new_month' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('debts'))->assertOk();

        $this->get(route('reports.debts.total'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->get(route('debts.getDebts', ['draw' => 1]))
            ->assertOk();
    }

    public function test_debts_routes_forbidden_without_reports_view(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('debts'))->assertForbidden();
        $this->get(route('reports.debts.total'))->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->get(route('debts.getDebts', ['draw' => 1]))
            ->assertForbidden();
    }

    public function test_debts_hides_location_filter_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantReportsViewOnly($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('debts'))
            ->assertOk()
            ->assertDontSee('id="pay-debt-filter-location"', false);
    }

    public function test_debts_get_debts_filters_by_current_user_location(): void
    {
        Carbon::setTestNow('2026-02-15');
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $userA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'location_id' => $locA->id,
        ]);
        $userB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'location_id' => $locB->id,
        ]);

        DB::table('users_prices')->insert([
            [
                'user_id' => $userA->id,
                'is_paid' => 0,
                'price' => 1000,
                'new_month' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userB->id,
                'is_paid' => 0,
                'price' => 2000,
                'new_month' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('debts.getDebts', [
                'draw' => 1,
                'filter_location_id' => $locA->id,
            ]))
            ->assertOk()
            ->json();

        $userIds = collect($json['data'] ?? [])->pluck('user_id')->unique()->values()->all();
        $this->assertContains($userA->id, $userIds);
        $this->assertNotContains($userB->id, $userIds);
    }

    public function test_debts_get_debts_filter_location_none(): void
    {
        Carbon::setTestNow('2026-02-15');
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $withLoc = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'location_id' => $loc->id,
        ]);
        $withoutLoc = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'location_id' => null,
        ]);

        DB::table('users_prices')->insert([
            [
                'user_id' => $withLoc->id,
                'is_paid' => 0,
                'price' => 900,
                'new_month' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $withoutLoc->id,
                'is_paid' => 0,
                'price' => 100,
                'new_month' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('debts.getDebts', [
                'draw' => 1,
                'filter_location_id' => 'none',
            ]))
            ->assertOk()
            ->json();

        $userIds = collect($json['data'] ?? [])->pluck('user_id')->unique()->values()->all();
        $this->assertContains($withoutLoc->id, $userIds);
        $this->assertNotContains($withLoc->id, $userIds);
    }

    public function test_debts_ignores_location_filter_without_locations_view_permission(): void
    {
        Carbon::setTestNow('2026-02-15');

        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantReportsViewOnly($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $userA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'location_id' => $locA->id,
        ]);
        $userB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'location_id' => null,
        ]);

        DB::table('users_prices')->insert([
            [
                'user_id' => $userA->id,
                'is_paid' => 0,
                'price' => 100,
                'new_month' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userB->id,
                'is_paid' => 0,
                'price' => 200,
                'new_month' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('debts.getDebts', [
                'draw' => 1,
                'filter_location_id' => $locA->id,
            ]))
            ->assertOk()
            ->json();

        $userIds = collect($json['data'] ?? [])->pluck('user_id')->unique()->values()->all();
        $this->assertContains($userA->id, $userIds);
        $this->assertContains($userB->id, $userIds);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
