<?php

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Вкладка «по ученикам»: история цен бывших участников группы (price &gt; 0).
 */
final class SettingPricesUsersFormerMembersFeatureTest extends CrmTestCase
{
    private Team $almaz;

    private Team $dubl;

    private User $student;

    private TeamUserSyncService $teamSync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->teamSync = app(TeamUserSyncService::class);

        $this->almaz = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Алмаз',
        ]);
        $this->dubl = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Дубль',
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->almaz->id,
            'is_enabled' => true,
            'lastname' => 'Бывший',
            'name' => 'Ученик',
        ]);
        $this->teamSync->syncTeamsForStudent($this->student, [
            (int) $this->almaz->id,
            (int) $this->dubl->id,
        ]);
    }

    public function test_users_tab_marks_former_team_ids_after_detach(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'is_paid' => 1,
        ]);

        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->dubl->id]);

        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-user-id="' . $this->student->id . '"', $html);
        $this->assertMatchesRegularExpression(
            '/data-user-id="' . $this->student->id . '"[^>]*data-former-team-ids="[^"]*' . $this->almaz->id . '/',
            $html
        );
        $this->assertStringContainsString('ранее:', $html);
        $this->assertStringContainsString('Алмаз', $html);
    }

    public function test_user_year_prices_allows_former_member_read_only(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'is_paid' => 1,
        ]);

        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->dubl->id]);

        $json = $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'year' => 2026,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_former_member', true)
            ->assertJsonPath('can_manage_manual_paid', false)
            ->json();

        $february = collect($json['months'])->firstWhere('new_month', '2026-02-01');
        $this->assertNotNull($february);
        $this->assertEquals(3267, (float) $february['price']);
        $this->assertTrue((bool) $february['is_paid']);
    }

    public function test_user_year_prices_rejects_team_without_membership_or_history(): void
    {
        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->dubl->id]);

        $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'year' => 2026,
        ])->assertStatus(422);
    }

    public function test_save_user_year_prices_rejects_former_member(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'is_paid' => 0,
        ]);

        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->dubl->id]);

        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'year' => 2026,
            'prices' => [
                [
                    'new_month' => '2026-02-01',
                    'price' => 9999,
                    'lesson_package_id' => null,
                ],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
        ]);
    }

    public function test_users_tab_includes_enabled_student_with_only_former_history(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-04-01',
            'price_cents' => 50000,
            'is_paid' => 1,
        ]);

        $this->teamSync->syncTeamsForStudent($this->student, []);

        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-user-id="' . $this->student->id . '"', $html);
        $this->assertMatchesRegularExpression(
            '/data-user-id="' . $this->student->id . '"[^>]*data-former-team-ids="[^"]*' . $this->almaz->id . '/',
            $html
        );
    }

    public function test_zero_price_history_does_not_mark_former_team(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price_cents' => 0,
            'is_paid' => 0,
        ]);

        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->dubl->id]);

        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/data-user-id="' . $this->student->id . '"[^>]*data-former-team-ids="[^"]*' . $this->almaz->id . '/',
            $html
        );

        $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'year' => 2026,
        ])->assertStatus(422);
    }

    public function test_user_year_prices_ajax_contract_for_former_member(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'is_paid' => 1,
        ]);
        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->dubl->id]);

        $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'year' => 2026,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_former_member', true)
            ->assertJsonPath('can_manage_manual_paid', false)
            ->assertJsonPath('user.team_id', $this->almaz->id)
            ->assertJsonStructure([
                'success',
                'is_former_member',
                'can_manage_manual_paid',
                'user' => ['id', 'name', 'lastname', 'team_id', 'team_name'],
                'year',
                'months' => [
                    [
                        'month',
                        'month_label',
                        'new_month',
                        'price',
                        'lesson_package_id',
                        'is_paid',
                        'is_manual_paid',
                        'effective_is_paid',
                        'has_price_row',
                        'manual_paid_note',
                    ],
                ],
                'lessonPackages',
            ]);
    }

    public function test_user_year_prices_history_from_any_month_opens_other_year_months(): void
    {
        // История price>0 только в апреле — достаточно для read-only доступа ко всему году.
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-04-01',
            'price_cents' => 80000,
            'is_paid' => 1,
        ]);
        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->dubl->id]);

        $json = $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'year' => 2026,
        ])->assertOk()
            ->assertJsonPath('is_former_member', true)
            ->json();

        $this->assertCount(12, $json['months']);
        $april = collect($json['months'])->firstWhere('new_month', '2026-04-01');
        $february = collect($json['months'])->firstWhere('new_month', '2026-02-01');
        $this->assertEquals(800, (float) $april['price']);
        $this->assertEquals(0, (float) $february['price']);
        $this->assertFalse((bool) $february['has_price_row']);
    }

    public function test_save_current_team_still_works_while_former_history_exists(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'is_paid' => 0,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->dubl->id,
            'new_month' => '2026-02-01',
            'price_cents' => 50000,
            'is_paid' => 0,
        ]);
        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->dubl->id]);

        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->dubl->id,
            'year' => 2026,
            'prices' => [
                [
                    'new_month' => '2026-02-01',
                    'price' => 1500,
                    'lesson_package_id' => null,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->dubl->id,
            'new_month' => '2026-02-01',
            'price_cents' => 150000,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
        ]);
    }
}
