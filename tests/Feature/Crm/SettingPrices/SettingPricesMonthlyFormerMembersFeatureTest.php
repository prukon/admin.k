<?php

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Вкладка «по месяцам»: исторические users_prices бывших участников группы (price > 0).
 */
final class SettingPricesMonthlyFormerMembersFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $currentStudent;

    private User $formerStudent;

    private LessonPackage $package;

    private TeamUserSyncService $teamSync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();

        $this->teamSync = app(TeamUserSyncService::class);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Алмаз',
        ]);

        $this->currentStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'lastname' => 'Активный',
            'name' => 'Ученик',
        ]);
        $this->teamSync->syncTeamsForStudent($this->currentStudent, [(int) $this->team->id]);

        $this->formerStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'lastname' => 'Бывший',
            'name' => 'Ученик',
        ]);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, [(int) $this->team->id]);

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => 500000,
            'is_active' => true,
        ]);
    }

    public function test_get_team_price_includes_former_member_with_price_gt_zero(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
            'is_paid' => 1,
        ]);

        // Снимаем с группы — запись users_prices остаётся.
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);
        $this->assertSame(
            0,
            (int) DB::table('team_user')
                ->where('user_id', $this->formerStudent->id)
                ->where('team_id', $this->team->id)
                ->count()
        );

        $json = $this->postJson(route('getTeamPrice'), [
            'teamId' => $this->team->id,
            'selectedDate' => 'Февраль 2026',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $teamIds = collect($json['usersTeam'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $this->currentStudent->id, $teamIds);
        $this->assertContains((int) $this->formerStudent->id, $teamIds);

        $currentRow = collect($json['usersTeam'])->firstWhere('id', $this->currentStudent->id);
        $formerRow = collect($json['usersTeam'])->firstWhere('id', $this->formerStudent->id);
        $this->assertFalse((bool) ($currentRow['is_former_member'] ?? false));
        $this->assertTrue((bool) ($formerRow['is_former_member'] ?? false));

        $formerPrice = collect($json['usersPrice'])->firstWhere('user_id', $this->formerStudent->id);
        $this->assertNotNull($formerPrice);
        $this->assertTrue((bool) ($formerPrice['is_former_member'] ?? false));
        $this->assertEquals(3267, (float) $formerPrice['price']);
        $this->assertTrue((bool) $formerPrice['is_paid']);

        // Порядок: сначала текущие, затем бывшие.
        $priceUserIds = collect($json['usersPrice'])->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all();
        $currentPos = array_search((int) $this->currentStudent->id, $priceUserIds, true);
        $formerPos = array_search((int) $this->formerStudent->id, $priceUserIds, true);
        $this->assertNotFalse($currentPos);
        $this->assertNotFalse($formerPos);
        $this->assertLessThan($formerPos, $currentPos);
    }

    public function test_get_team_price_excludes_former_member_with_zero_price(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 0,
            'is_paid' => 0,
        ]);

        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $json = $this->postJson(route('getTeamPrice'), [
            'teamId' => $this->team->id,
            'selectedDate' => 'Февраль 2026',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $teamIds = collect($json['usersTeam'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertNotContains((int) $this->formerStudent->id, $teamIds);

        $priceUserIds = collect($json['usersPrice'])->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->assertNotContains((int) $this->formerStudent->id, $priceUserIds);

        // Нулевая запись бывшего не удаляется и не «досоздаётся» повторно при открытии.
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 0,
        ]);
        $this->assertSame(
            1,
            UserPrice::query()
                ->where('user_id', $this->formerStudent->id)
                ->where('team_id', $this->team->id)
                ->where('new_month', '2026-02-01')
                ->count()
        );
    }

    public function test_get_team_price_does_not_mark_disabled_pivot_member_as_former(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 2000,
            'is_paid' => 0,
        ]);

        // Остаётся в pivot, но отключён — как и раньше не в списке; не как «бывший».
        $this->formerStudent->forceFill(['is_enabled' => false])->save();

        $json = $this->postJson(route('getTeamPrice'), [
            'teamId' => $this->team->id,
            'selectedDate' => 'Февраль 2026',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $teamIds = collect($json['usersTeam'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertNotContains((int) $this->formerStudent->id, $teamIds);
        $this->assertContains((int) $this->currentStudent->id, $teamIds);
    }

    public function test_get_team_price_succeeds_with_only_former_members(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 1500,
            'is_paid' => 1,
        ]);

        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);
        $this->teamSync->syncTeamsForStudent($this->currentStudent, []);

        $json = $this->postJson(route('getTeamPrice'), [
            'teamId' => $this->team->id,
            'selectedDate' => 'Февраль 2026',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertCount(1, $json['usersTeam']);
        $this->assertTrue((bool) ($json['usersTeam'][0]['is_former_member'] ?? false));
        $this->assertSame((int) $this->formerStudent->id, (int) $json['usersTeam'][0]['id']);
    }

    public function test_set_team_price_does_not_change_former_member_row(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        UserPrice::forceCreate([
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 100,
            'is_paid' => 0,
        ]);

        $this->postJson(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'selectedDate' => 'Февраль 2026',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 5000,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_set_price_all_users_skips_former_member_even_if_sent_in_payload(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
            'is_paid' => 0,
        ]);

        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        UserPrice::forceCreate([
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 100,
            'is_paid' => 0,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Февраль 2026',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->currentStudent->id,
                    'price' => 4500,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => $this->currentStudent->name],
                ],
                [
                    'user_id' => $this->formerStudent->id,
                    'price' => 9999,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => $this->formerStudent->name],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
        ]);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 4500,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_set_manual_paid_rejects_former_member(): void
    {
        // Право setPrices.manualPaid.manage есть у superadmin, не у базового admin.
        $this->asSuperadmin();

        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Дубль',
        ]);

        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
            'is_paid' => 0,
        ]);

        // Как в реальном кейсе: снят с «Алмаз», но остаётся в другой группе.
        $this->teamSync->syncTeamsForStudent($this->formerStudent, [(int) $otherTeam->id]);

        $this->postJson(route('setting-prices.manual-paid'), [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Февраль 2026',
            'mode' => 'paid',
            'comment' => 'Попытка изменить бывшего',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Группа не найдена или ученик в ней не состоит.');
    }

    public function test_get_team_price_ajax_contract_includes_former_flags_and_lesson_packages(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
            'is_paid' => 1,
        ]);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->postJson(route('getTeamPrice'), [
            'teamId' => $this->team->id,
            'selectedDate' => 'Февраль 2026',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'usersTeam',
                'usersPrice',
                'lessonPackages' => [
                    ['id', 'name', 'price'],
                ],
                'can_manage_manual_paid',
            ]);
    }

    public function test_set_price_all_teams_does_not_change_former_member_row(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        UserPrice::forceCreate([
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 100,
            'is_paid' => 0,
        ]);

        $this->postJson(route('setPriceAllTeams'), [
            'selectedDate' => 'Февраль 2026',
            'teamsData' => [
                [
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->package->id,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
            'lesson_package_id' => null,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price' => 5000,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_get_team_price_does_not_create_zero_row_for_former_on_open(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-03-01',
            'price' => 1500,
            'is_paid' => 1,
        ]);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $before = UserPrice::query()
            ->where('user_id', $this->formerStudent->id)
            ->where('team_id', $this->team->id)
            ->count();

        // Февраль: у бывшего нет строки — не создаём.
        $this->postJson(route('getTeamPrice'), [
            'teamId' => $this->team->id,
            'selectedDate' => 'Февраль 2026',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $after = UserPrice::query()
            ->where('user_id', $this->formerStudent->id)
            ->where('team_id', $this->team->id)
            ->count();

        $this->assertSame($before, $after);
        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
        ]);

        $teamIds = collect(
            $this->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])->json('usersTeam')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotContains((int) $this->formerStudent->id, $teamIds);
    }

    public function test_monthly_page_renders_for_admin(): void
    {
        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertViewIs('admin.SettingPrices.index')
            ->assertViewHas('activeTab', 'monthly');
    }
}
