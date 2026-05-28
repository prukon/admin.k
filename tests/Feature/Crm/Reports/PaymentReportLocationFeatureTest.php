<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class PaymentReportLocationFeatureTest extends CrmTestCase
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function ajaxGetPayments(array $query = []): array
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payments.getPayments', $query))
            ->assertOk()
            ->json();
    }

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

    public function test_payments_report_shows_location_column_and_filter_with_locations_view(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Центр',
            'is_enabled' => true,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        Payment::factory()->forUser($student)->create([
            'location_id' => $location->id,
            'summ' => 1500,
        ]);

        $this->get(route('payments'))
            ->assertOk()
            ->assertSee('pay-filter-location', false)
            ->assertSee('payColLocation', false)
            ->assertSee('Центр', false);

        $json = $this->ajaxGetPayments(['draw' => 1, 'start' => 0, 'length' => 50]);

        $row = collect($json['data'] ?? [])->first();
        $this->assertNotNull($row);
        $this->assertSame('Центр', $row['location_title'] ?? null);
    }

    public function test_payments_report_hides_location_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('reports.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('payments'))
            ->assertOk()
            ->assertDontSee('id="pay-filter-location"', false)
            ->assertDontSee('id="payColLocation"', false);

        $json = $this->ajaxGetPayments(['draw' => 1, 'start' => 0, 'length' => 50]);

        $row = collect($json['data'] ?? [])->first();
        if ($row !== null) {
            $this->assertArrayNotHasKey('location_title', $row);
        }
    }

    public function test_payments_report_filters_by_snapshot_location_not_current_user_location(): void
    {
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Локация A',
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Локация B',
            'is_enabled' => true,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        Payment::factory()->forUser($student)->create([
            'location_id' => $locA->id,
            'summ' => 2000,
        ]);

        $json = $this->ajaxGetPayments([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'filter_location_id' => $locA->id,
        ]);

        $this->assertCount(1, $json['data'] ?? []);
        $this->assertSame('Локация A', $json['data'][0]['location_title'] ?? null);

        $jsonEmpty = $this->ajaxGetPayments([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'filter_location_id' => $locB->id,
        ]);

        $this->assertCount(0, $jsonEmpty['data'] ?? []);
    }

    public function test_payments_report_columns_settings_location_key(): void
    {
        $this->grantPermission('locations.view');

        $this->postJson('/admin/reports/payments/columns-settings', [
            'columns' => [
                'user_name' => true,
                'location' => false,
            ],
        ])->assertOk();

        $this->getJson('/admin/reports/payments/columns-settings')
            ->assertOk()
            ->assertJsonPath('location', false);

        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('reports.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        UserTableSetting::query()->updateOrCreate(
            ['user_id' => $actor->id, 'table_key' => 'reports_payments'],
            ['columns' => ['location' => true, 'user_name' => true]]
        );

        $this->getJson('/admin/reports/payments/columns-settings')
            ->assertOk()
            ->assertJsonMissing(['location']);
    }
}
