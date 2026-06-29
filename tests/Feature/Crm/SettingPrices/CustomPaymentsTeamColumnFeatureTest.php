<?php

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Models\UserCustomPayment;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class CustomPaymentsTeamColumnFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $permId = $this->permissionId('setPrices.customPayments.view');
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $viewPermId = $this->permissionId('setPrices.view');
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $viewPermId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user);
    }

    public function test_custom_payments_data_includes_team_label(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа Колонка',
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'amount' => '500.00',
            'is_paid' => false,
        ]);

        $response = $this->getJson(route('admin.settingPrices.customPayments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('team_label', 'Группа Колонка');
        $this->assertNotNull($row);
        $this->assertSame('Группа Колонка', $row['team_label']);
    }

    public function test_custom_payments_index_renders_team_column_header(): void
    {
        $this->get(route('admin.settingPrices.customPayments'))
            ->assertOk()
            ->assertSee('Группа', false);
    }
}
