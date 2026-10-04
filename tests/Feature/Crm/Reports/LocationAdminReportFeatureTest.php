<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\Location;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class LocationAdminReportFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
        $this->grantPermission('locations.view');
    }

    public function test_payments_column_and_filter_use_location_admins(): void
    {
        [$withAdmin, $withoutAdmin, $admin] = $this->seedTwoLocations();

        $this->get(route('payments'))
            ->assertOk()
            ->assertSee('id="pay-filter-admin"', false)
            ->assertSee('id="payColAdmin"', false)
            ->assertSee('Северная Анна', false);

        $all = $this->rows('payments.getPayments');
        $bySum = collect($all)->keyBy(fn ($row) => (string) ($row['summ'] ?? ''));
        $this->assertSame('Северная Анна', $bySum->get('100')['location_admin'] ?? null);
        $this->assertSame(['Северная Анна'], $bySum->get('100')['location_admin_names'] ?? null);
        $this->assertSame('', $bySum->get('50')['location_admin'] ?? null);

        $filtered = $this->rows('payments.getPayments', [
            'filter_admin_user_id' => [$admin->id],
        ]);
        $this->assertSame([100.0], $this->sums($filtered));

        $none = $this->rows('payments.getPayments', [
            'filter_admin_user_id' => ['none'],
        ]);
        $this->assertSame([50.0], $this->sums($none));
        $this->assertNotSame($withAdmin->id, $withoutAdmin->id);
    }

    public function test_payments_hide_admin_filter_without_locations_view(): void
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
            ->assertDontSee('id="pay-filter-admin"', false)
            ->assertDontSee('id="payColAdmin"', false);
    }

    public function test_save_filters_rejects_unknown_admin(): void
    {
        $response = $this->postJson(route('reports.payments.filters.save'), [
            'filter_admin_user_id' => [999999],
        ])->assertStatus(422);

        $this->assertSame(
            'Выберите администратора из списка.',
            $response->json('errors')['filter_admin_user_id.0'][0] ?? null
        );
    }

    public function test_monthly_ltv_and_debts_show_location_admin(): void
    {
        [$location, , $admin] = $this->seedTwoLocations();
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа админа',
            'location_id' => $location->id,
            'is_enabled' => 1,
        ]);
        Payment::factory()->forUser($student)->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
            'summ_cents' => 8000,
            'payment_month' => now()->startOfMonth()->format('Y-m-d'),
            'operation_date' => now(),
        ]);

        $months = $this->rows('reports.payments.monthly.data', ['mode' => 'subscription']);
        $this->assertNotEmpty($months);
        $this->assertArrayNotHasKey('location_admin', $months[0]);

        $monthPayments = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->getJson(route('reports.payments.monthly.payments', [
            'yearMonth' => now()->format('Y-m'),
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'mode' => 'subscription',
        ]))->assertOk()->json('data');
        $detail = collect($monthPayments)->first(fn ($row) => (int) ($row['summ'] ?? 0) === 80);
        $this->assertNotNull($detail);
        $this->assertSame('Северная Анна', $detail['location_admin']);

        $ltv = $this->rows('reports.ltv.data', [
            'order' => [
                ['column' => 3, 'dir' => 'desc'],
            ],
            'columns' => [
                ['data' => null, 'name' => '', 'orderable' => 'false'],
                ['data' => 'user_name', 'name' => 'user_name', 'orderable' => 'true'],
                ['data' => 'team_title', 'name' => 'team_title', 'orderable' => 'true'],
                ['data' => 'location_admin', 'name' => 'location_admin', 'orderable' => 'true'],
                ['data' => 'total_price', 'name' => 'total_price', 'orderable' => 'true'],
            ],
        ]);
        $userRow = collect($ltv)->firstWhere('user_id', $student->id);
        $this->assertNotNull($userRow);
        $this->assertSame('Северная Анна', $userRow['location_admin']);

        $this->grantPermission('reports.ltv.teams.view');
        $teams = $this->rows('reports.ltv.teams.data', ['period' => 'all']);
        $teamRow = collect($teams)->firstWhere('team_id', $team->id);
        $this->assertNotNull($teamRow);
        $this->assertSame('Северная Анна', $teamRow['location_admin']);

        $this->grantPermission('reports.ltv.locations.view');
        $locations = $this->rows('reports.ltv.locations.data', ['period' => 'all']);
        $locationRow = collect($locations)->firstWhere('location_id', $location->id);
        $this->assertNotNull($locationRow);
        $this->assertSame('Северная Анна', $locationRow['location_admin']);

        $this->insertUserPrice($student, [
            'is_paid' => 0,
            'price' => 70,
            'new_month' => now()->subMonth()->startOfMonth()->format('Y-m-d'),
        ], $team);

        $debts = $this->rows('debts.getDebts', [
            'filter_admin_user_id' => [$admin->id],
            'status' => '',
        ]);
        $this->assertTrue(collect($debts)->contains(fn ($row) => (int) ($row['user_id'] ?? 0) === (int) $student->id));

        $otherDebts = $this->rows('debts.getDebts', [
            'filter_admin_user_id' => ['none'],
            'status' => '',
        ]);
        $this->assertFalse(collect($otherDebts)->contains(fn ($row) => (int) ($row['user_id'] ?? 0) === (int) $student->id));
    }

    /**
     * @return array{0: Location, 1: Location, 2: User}
     */
    private function seedTwoLocations(): array
    {
        $withAdmin = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Зал с админом',
            'is_enabled' => true,
        ]);
        $withoutAdmin = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Зал без админа',
            'is_enabled' => true,
        ]);
        $admin = $this->createPartnerAdmin();
        DB::table('location_admin_user')->insert([
            'partner_id' => $this->partner->id,
            'location_id' => $withAdmin->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        Payment::factory()->forUser($student)->create([
            'location_id' => $withAdmin->id,
            'summ_cents' => 10000,
        ]);
        Payment::factory()->forUser($student)->create([
            'location_id' => $withoutAdmin->id,
            'summ_cents' => 5000,
        ]);

        return [$withAdmin, $withoutAdmin, $admin];
    }

    private function createPartnerAdmin(): User
    {
        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');

        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $adminRoleId,
            'is_enabled' => 1,
            'lastname' => 'Северная',
            'name' => 'Анна',
        ]);
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
        $this->user->unsetRelation('role');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function rows(string $routeName, array $query = []): array
    {
        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->getJson(route($routeName, array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ], $query)));

        $response->assertOk();

        return $response->json('data') ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<float>
     */
    private function sums(array $rows): array
    {
        $sums = array_map(static fn ($row) => (float) ($row['summ'] ?? 0), $rows);
        sort($sums);

        return $sums;
    }
}
