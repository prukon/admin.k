<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class PaymentReportTrainerFilterFeatureTest extends CrmTestCase
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

    private function makeTrainerProfile(string $lastname, string $name = 'Иван'): TrainerProfile
    {
        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $trainerRoleId,
            'lastname' => $lastname,
            'name' => $name,
        ]);

        return TrainerProfile::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $user->id,
            'is_enabled' => true,
            'sort_order' => 0,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
        $this->grantPermission('trainers.view');
    }

    public function test_payments_report_filters_by_trainer_teams(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа тренера A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Другая группа']);

        $trainer = $this->makeTrainerProfile('Тренеров', 'Алексей');

        DB::table('team_trainer')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'trainer_profile_id' => $trainer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);
        $studentB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
        ]);

        $paymentA = Payment::factory()->forUser($studentA)->create(['summ' => 1000]);
        Payment::factory()->forUser($studentB)->create(['summ' => 2000]);

        $json = $this->ajaxGetPayments([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'filter_trainer_profile_id' => $trainer->id,
        ]);

        $ids = collect($json['data'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($paymentA->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_payments_page_shows_trainer_filter_with_trainers_view(): void
    {
        $this->get(route('payments'))
            ->assertOk()
            ->assertSee('pay-filter-trainer', false)
            ->assertSee('Тренер', false);
    }

    public function test_payment_monthly_report_filters_by_trainer_teams(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа тренера monthly']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Другая группа monthly']);

        $trainer = $this->makeTrainerProfile('Тренеров', 'Месячный');

        DB::table('team_trainer')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'trainer_profile_id' => $trainer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);
        $studentB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
        ]);

        Payment::factory()->forUser($studentA)->create([
            'summ' => 1500,
            'payment_month' => '2025-03-01',
        ]);
        Payment::factory()->forUser($studentB)->create([
            'summ' => 2500,
            'payment_month' => '2025-03-01',
        ]);

        $this->getJson(route('reports.payments.monthly.total', [
            'filter_trainer_profile_id' => $trainer->id,
        ]))
            ->assertOk()
            ->assertJson([
                'total_raw' => 1500.0,
            ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.payments.monthly.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'filter_trainer_profile_id' => $trainer->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) ($json['recordsTotal'] ?? 0));
        $row = collect($json['data'] ?? [])->first();
        $this->assertNotNull($row);
        $this->assertSame(1500.0, (float) ($row['total_sum'] ?? 0));
    }

    public function test_payment_monthly_page_shows_trainer_filter_with_trainers_view(): void
    {
        $this->get(route('reports.payments.monthly'))
            ->assertOk()
            ->assertSee('pay-monthly-filter-trainer', false)
            ->assertSee('Тренер', false);
    }

    public function test_ltv_report_filters_by_trainer_teams(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа LTV тренера']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа LTV другая']);

        $trainer = $this->makeTrainerProfile('Тренеров', 'LTV');

        DB::table('team_trainer')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'trainer_profile_id' => $trainer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);
        $studentB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
        ]);

        Payment::factory()->forUser($studentA)->create(['summ' => 3000]);
        Payment::factory()->forUser($studentB)->create(['summ' => 5000]);

        $this->getJson(route('reports.ltv.total', [
            'filter_trainer_profile_id' => $trainer->id,
        ]))
            ->assertOk()
            ->assertJson([
                'total_raw' => 3000.0,
            ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'filter_trainer_profile_id' => $trainer->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) ($json['recordsTotal'] ?? 0));
        $row = collect($json['data'] ?? [])->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $studentA->id, (int) ($row['user_id'] ?? 0));
        $this->assertSame(3000.0, (float) ($row['total_price'] ?? 0));
    }

    public function test_ltv_page_shows_trainer_filter_with_trainers_view(): void
    {
        $this->get(route('reports.ltv'))
            ->assertOk()
            ->assertSee('pay-ltv-filter-trainer', false)
            ->assertSee('Тренер', false);
    }

    public function test_debts_report_filters_by_trainer_teams(): void
    {
        \Carbon\Carbon::setTestNow('2026-02-15');

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа долгов тренера']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа долгов другая']);

        $trainer = $this->makeTrainerProfile('Тренеров', 'Долги');

        DB::table('team_trainer')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'trainer_profile_id' => $trainer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'is_enabled' => 1,
        ]);
        $studentB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'is_enabled' => 1,
        ]);

        $month = '2026-01-01';

        $this->insertUserPrice($studentA, [
            'is_paid'   => 0,
            'price'     => 1200,
            'new_month' => $month,
        ], $teamA);
        $this->insertUserPrice($studentB, [
            'is_paid'   => 0,
            'price'     => 3400,
            'new_month' => $month,
        ], $teamB);

        $this->getJson(route('reports.debts.total', [
            'filter_trainer_profile_id' => $trainer->id,
        ]))
            ->assertOk()
            ->assertJson([
                'total_raw' => 1200.0,
            ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('debts.getDebts', [
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'filter_trainer_profile_id' => $trainer->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) ($json['recordsTotal'] ?? 0));
        $row = collect($json['data'] ?? [])->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $studentA->id, (int) ($row['user_id'] ?? 0));
        $this->assertEquals(1200, $row['price']);
    }

    public function test_debts_page_shows_trainer_filter_with_trainers_view(): void
    {
        $this->get(route('debts'))
            ->assertOk()
            ->assertSee('pay-debt-filter-trainer', false)
            ->assertSee('Тренер', false);
    }
}
