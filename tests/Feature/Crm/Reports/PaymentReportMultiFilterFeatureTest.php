<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Location;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class PaymentReportMultiFilterFeatureTest extends CrmTestCase
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function paymentRows(array $query = []): array
    {
        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payments.getPayments', $query))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
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

    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_payments_filters_markup_is_multiselect_of_active_options(): void
    {
        $this->grantPermission('trainers.view');
        $this->grantPermission('locations.view');

        $activeTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Активная группа отчёта',
            'is_enabled' => true,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Скрытая группа отчёта',
            'is_enabled' => false,
        ]);

        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');
        $activeUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $trainerRoleId,
            'lastname' => 'Активный',
            'name' => 'ТренерОтчёта',
            'is_enabled' => 1,
        ]);
        $inactiveUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $trainerRoleId,
            'lastname' => 'Скрытый',
            'name' => 'ТренерОтчёта',
            'is_enabled' => 0,
        ]);
        TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $activeUser->id,
            'is_enabled' => true,
        ]);
        TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $inactiveUser->id,
            'is_enabled' => false,
        ]);

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Активный объект отчёта',
            'is_enabled' => true,
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Скрытый объект отчёта',
            'is_enabled' => false,
        ]);

        $html = $this->get(route('payments'))->assertOk()->getContent();

        $this->assertStringContainsString('name="filter_team_id[]"', $html);
        $this->assertStringContainsString('name="filter_trainer_profile_id[]"', $html);
        $this->assertStringContainsString('name="filter_location_id[]"', $html);
        $this->assertStringContainsString('js-generic-multiselect-select', $html);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.init', $html);
        $this->assertStringContainsString('Активная группа отчёта', $html);
        $this->assertStringNotContainsString('Скрытая группа отчёта', $html);
        $this->assertStringContainsString('Активный ТренерОтчёта', $html);
        $this->assertStringNotContainsString('Скрытый ТренерОтчёта', $html);
        $this->assertStringContainsString('Активный объект отчёта', $html);
        $this->assertStringNotContainsString('Скрытый объект отчёта', $html);
        $this->assertStringContainsString('Без объекта', $html);
        $this->assertStringContainsString((string) $activeTeam->id, $html);
    }

    public function test_filter_by_several_teams_is_or(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Мульти A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Мульти B']);
        $teamC = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Мульти C']);

        $studentA = User::factory()->create(['partner_id' => $this->partner->id, 'team_id' => $teamA->id]);
        $studentB = User::factory()->create(['partner_id' => $this->partner->id, 'team_id' => $teamB->id]);
        $studentC = User::factory()->create(['partner_id' => $this->partner->id, 'team_id' => $teamC->id]);

        $payA = Payment::factory()->forUser($studentA)->create([
            'team_id' => $teamA->id,
            'team_title' => 'Мульти A',
            'summ_cents' => 10000,
        ]);
        $payB = Payment::factory()->forUser($studentB)->create([
            'team_id' => $teamB->id,
            'team_title' => 'Мульти B',
            'summ_cents' => 20000,
        ]);
        $payC = Payment::factory()->forUser($studentC)->create([
            'team_id' => $teamC->id,
            'team_title' => 'Мульти C',
            'summ_cents' => 30000,
        ]);

        $ids = collect($this->paymentRows([
            'filter_team_id' => [$teamA->id, $teamB->id],
        ]))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $payA->id, $ids);
        $this->assertContains((int) $payB->id, $ids);
        $this->assertNotContains((int) $payC->id, $ids);
    }

    public function test_filter_by_several_trainers_is_or(): void
    {
        $this->grantPermission('trainers.view');

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа тр A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа тр B']);
        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');

        $make = function (string $lastname) use ($trainerRoleId) {
            $user = User::factory()->create([
                'partner_id' => $this->partner->id,
                'role_id' => $trainerRoleId,
                'lastname' => $lastname,
                'name' => 'Иван',
                'is_enabled' => 1,
            ]);

            return TrainerProfile::factory()->create([
                'partner_id' => $this->partner->id,
                'user_id' => $user->id,
                'is_enabled' => true,
            ]);
        };

        $trainerA = $make('Первый');
        $trainerB = $make('Второй');

        DB::table('team_trainer')->insert([
            [
                'partner_id' => $this->partner->id,
                'team_id' => $teamA->id,
                'trainer_profile_id' => $trainerA->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'partner_id' => $this->partner->id,
                'team_id' => $teamB->id,
                'trainer_profile_id' => $trainerB->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $studentA = User::factory()->create(['partner_id' => $this->partner->id, 'team_id' => $teamA->id]);
        $studentB = User::factory()->create(['partner_id' => $this->partner->id, 'team_id' => $teamB->id]);
        $payA = Payment::factory()->forUser($studentA)->create(['summ_cents' => 10000]);
        $payB = Payment::factory()->forUser($studentB)->create(['summ_cents' => 20000]);

        $ids = collect($this->paymentRows([
            'filter_trainer_profile_id' => [$trainerA->id, $trainerB->id],
        ]))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $payA->id, $ids);
        $this->assertContains((int) $payB->id, $ids);
    }

    public function test_filter_by_several_locations_includes_none(): void
    {
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект A',
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект B',
            'is_enabled' => true,
        ]);

        $student = User::factory()->create(['partner_id' => $this->partner->id]);
        $payA = Payment::factory()->forUser($student)->create([
            'location_id' => $locA->id,
            'summ_cents' => 10000,
        ]);
        $payNone = Payment::factory()->forUser($student)->create([
            'location_id' => null,
            'summ_cents' => 20000,
        ]);
        $payB = Payment::factory()->forUser($student)->create([
            'location_id' => $locB->id,
            'summ_cents' => 30000,
        ]);

        $ids = collect($this->paymentRows([
            'filter_location_id' => [$locA->id, 'none'],
        ]))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $payA->id, $ids);
        $this->assertContains((int) $payNone->id, $ids);
        $this->assertNotContains((int) $payB->id, $ids);
    }
}
