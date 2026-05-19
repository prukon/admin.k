<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class PaymentMonthlyReportTest extends CrmTestCase
{
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
    /**
     * [P0] Контроль доступа по праву can:reports.view (страница, data, total).
     */
    public function test_payment_monthly_routes_require_reports_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.payments.monthly'))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payments.monthly.data', ['draw' => 1]))
            ->assertForbidden();

        $this->get(route('reports.payments.monthly.total'))->assertForbidden();
    }

    /**
     * [P0] /admin/reports/payments/monthly/total отдаёт сумму payments.summ по фильтрам.
     */
    public function test_payment_monthly_total_endpoint_returns_correct_sum(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 1000,
            'payment_month' => '2025-01-01',
        ]);
        Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 2000,
            'payment_month' => '2025-02-01',
        ]);
        Payment::factory()->create([
            'user_id' => $this->foreignUser->id,
            'summ' => 9999,
            'payment_month' => '2025-01-01',
        ]);

        $expectedRaw = 3000.0;
        $expectedFormatted = number_format($expectedRaw, 0, '', ' ');

        $this->get(route('reports.payments.monthly.total'))
            ->assertOk()
            ->assertJson([
                'total_formatted' => $expectedFormatted,
                'total_raw' => $expectedRaw,
            ]);
    }

    public function test_payment_monthly_page_shows_location_filter_with_locations_view(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id]);
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Филиал М',
            'is_enabled' => true,
        ]);

        $this->get(route('reports.payments.monthly'))
            ->assertOk()
            ->assertSee('pay-monthly-filter-location', false);
    }

    public function test_payment_monthly_total_respects_filter_location_id(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id]);
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
            'payment_month' => '2025-03-01',
        ]);
        Payment::factory()->forUser($student)->create([
            'location_id' => $locB->id,
            'summ' => 2500,
            'payment_month' => '2025-03-01',
        ]);

        $this->get(route('reports.payments.monthly.total', ['filter_location_id' => $locA->id]))
            ->assertOk()
            ->assertJson([
                'total_raw' => 1500.0,
            ]);

        $this->get(route('reports.payments.monthly.total', ['filter_location_id' => $locB->id]))
            ->assertOk()
            ->assertJson([
                'total_raw' => 2500.0,
            ]);
    }
}

