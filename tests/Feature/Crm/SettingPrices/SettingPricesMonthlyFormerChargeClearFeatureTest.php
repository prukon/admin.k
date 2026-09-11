<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Enums\AuditEvent;
use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\SettingPrices\FormerMemberMonthChargeService;
use App\Services\TeamUserSyncService;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Корзина неоплаченного начисления бывшего на вкладке «По месяцам».
 */
final class SettingPricesMonthlyFormerChargeClearFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $currentStudent;

    private User $formerStudent;

    private LessonPackage $fixedPackage;

    private TeamUserSyncService $teamSync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule', 'postpay']);

        $this->teamSync = app(TeamUserSyncService::class);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Алмаз корзина',
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

        $this->fixedPackage = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 60)->create([
            'name' => 'Фикс корзина',
            'price_cents' => 500000,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    /**
     * @return array{user_id: int, team_id: int, selectedDate: string}
     */
    private function clearPayload(?User $student = null): array
    {
        return [
            'user_id' => (int) ($student ?? $this->formerStudent)->id,
            'team_id' => (int) $this->team->id,
            'selectedDate' => 'Февраль 2026',
        ];
    }

    private function makeUnpaidRow(User $student, int $priceCents = 326700, ?int $packageId = null, bool $paid = false): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => $priceCents,
            'is_paid' => $paid ? 1 : 0,
            'lesson_package_id' => $packageId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formerPriceFromGetTeam(): ?array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $row = collect($json['usersPrice'] ?? [])->firstWhere('user_id', $this->formerStudent->id);

        return is_array($row) ? $row : null;
    }

    private function assignFixedWhileMember(): UserPrice
    {
        $row = $this->makeUnpaidRow($this->formerStudent, 0, null);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Февраль 2026',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->formerStudent->id,
                    'price' => 5000.0,
                    'lesson_package_id' => $this->fixedPackage->id,
                    'user' => ['name' => $this->formerStudent->name],
                ],
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $row->refresh();
        $this->assertNotNull($row->user_lesson_package_id);

        return $row;
    }

    private function seedConsumingPostpayVisit(): void
    {
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'weekday' => 2,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->formerStudent->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => '2026-02-10',
            'user_lesson_package_id' => null,
            'lesson_occurrence_status_id' => $attendedId,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_get_team_price_flags_unpaid_former_can_clear(): void
    {
        $this->makeUnpaidRow($this->formerStudent);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $formerPrice = $this->formerPriceFromGetTeam();
        $this->assertNotNull($formerPrice);
        $this->assertTrue((bool) ($formerPrice['is_former_member'] ?? false));
        $this->assertArrayHasKey('can_clear_former_charge', $formerPrice);
        $this->assertTrue((bool) $formerPrice['can_clear_former_charge']);
        $this->assertSame('', (string) ($formerPrice['former_charge_clear_block_reason'] ?? ''));
    }

    public function test_get_team_price_flags_paid_former_cannot_clear(): void
    {
        $this->makeUnpaidRow($this->formerStudent, 326700, null, true);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $formerPrice = $this->formerPriceFromGetTeam();
        $this->assertNotNull($formerPrice);
        $this->assertTrue((bool) ($formerPrice['is_paid'] ?? false));
        $this->assertFalse((bool) ($formerPrice['can_clear_former_charge'] ?? true));
        $this->assertSame(
            FormerMemberMonthChargeService::REASON_PAID,
            (string) ($formerPrice['former_charge_clear_block_reason'] ?? '')
        );
    }

    public function test_clear_unpaid_former_charge_zeros_row_deletes_ulp_and_writes_audit(): void
    {
        $row = $this->assignFixedWhileMember();
        $ulpId = (int) $row->user_lesson_package_id;
        $this->assertGreaterThan(0, $ulpId);
        $this->assertNotNull(UserLessonPackage::query()->find($ulpId));

        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->clearPayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Начисление снято.');

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 0,
            'lesson_package_id' => null,
            'user_lesson_package_id' => null,
        ]);
        $this->assertNull(UserLessonPackage::query()->find($ulpId));

        $this->assertTrue(
            MyLog::query()->where('event', AuditEvent::PricingFormerChargeCleared->value)->exists()
        );

        $this->assertNull($this->formerPriceFromGetTeam());
    }

    public function test_clear_rejects_current_team_member(): void
    {
        $this->makeUnpaidRow($this->currentStudent, 150000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->clearPayload($this->currentStudent))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['charge']);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 150000,
        ]);
    }

    public function test_clear_rejects_paid_former_charge(): void
    {
        $this->makeUnpaidRow($this->formerStudent, 326700, null, true);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->clearPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.charge.0', FormerMemberMonthChargeService::REASON_PAID);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'is_paid' => 1,
        ]);
    }

    public function test_clear_rejects_postpay_with_visits_and_get_team_price_shows_disabled_reason(): void
    {
        $postpay = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->postpay()
            ->create([
                'name' => 'Постоплата бывший',
                'price_cents' => 50000,
                'is_active' => true,
            ]);

        $this->makeUnpaidRow($this->formerStudent, 50000, (int) $postpay->id);
        $this->seedConsumingPostpayVisit();
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $formerPrice = $this->formerPriceFromGetTeam();
        $this->assertNotNull($formerPrice);
        $this->assertFalse((bool) ($formerPrice['can_clear_former_charge'] ?? true));
        $this->assertSame(
            FormerMemberMonthChargeService::REASON_POSTPAY_VISITS,
            (string) ($formerPrice['former_charge_clear_block_reason'] ?? '')
        );

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->clearPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.charge.0', FormerMemberMonthChargeService::REASON_POSTPAY_VISITS);

        $this->assertGreaterThan(
            0,
            (int) UserPrice::query()
                ->where('user_id', $this->formerStudent->id)
                ->where('team_id', $this->team->id)
                ->where('new_month', '2026-02-01')
                ->value('price_cents')
        );
    }

    public function test_clear_rejects_laid_out_ulp(): void
    {
        $row = $this->assignFixedWhileMember();
        UserLessonPackage::query()->whereKey($row->user_lesson_package_id)->update([
            'starts_at' => '2026-02-01',
        ]);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $formerPrice = $this->formerPriceFromGetTeam();
        $this->assertNotNull($formerPrice);
        $this->assertFalse((bool) ($formerPrice['can_clear_former_charge'] ?? true));
        $this->assertSame(
            FormerMemberMonthChargeService::REASON_LAID_OUT,
            (string) ($formerPrice['former_charge_clear_block_reason'] ?? '')
        );

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->clearPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.charge.0', FormerMemberMonthChargeService::REASON_LAID_OUT);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 500000,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);
    }

    public function test_clear_rejects_used_ulp(): void
    {
        $row = $this->assignFixedWhileMember();
        UserLessonPackage::query()->whereKey($row->user_lesson_package_id)->update([
            'lessons_remaining' => DB::raw('lessons_total - 1'),
        ]);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $formerPrice = $this->formerPriceFromGetTeam();
        $this->assertNotNull($formerPrice);
        $this->assertFalse((bool) ($formerPrice['can_clear_former_charge'] ?? true));
        $this->assertSame(
            FormerMemberMonthChargeService::REASON_USED,
            (string) ($formerPrice['former_charge_clear_block_reason'] ?? '')
        );

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->clearPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.charge.0', FormerMemberMonthChargeService::REASON_USED);
    }

    public function test_clear_rejects_missing_positive_charge(): void
    {
        $this->makeUnpaidRow($this->formerStudent, 0);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->clearPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['charge']);
    }

    public function test_current_member_row_is_not_former_so_trash_rule_does_not_apply(): void
    {
        $this->makeUnpaidRow($this->currentStudent, 150000);
        $this->makeUnpaidRow($this->formerStudent, 326700);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertOk()
            ->json();

        $current = collect($json['usersPrice'])->firstWhere('user_id', $this->currentStudent->id);
        $former = collect($json['usersPrice'])->firstWhere('user_id', $this->formerStudent->id);

        $this->assertNotNull($current);
        $this->assertFalse((bool) ($current['is_former_member'] ?? true));
        $this->assertNotNull($former);
        $this->assertTrue((bool) ($former['is_former_member'] ?? false));
        $this->assertTrue((bool) ($former['can_clear_former_charge'] ?? false));
    }

    public function test_manual_paid_former_cannot_clear(): void
    {
        $row = $this->makeUnpaidRow($this->formerStudent, 326700);
        $row->forceFill([
            'is_paid' => 0,
            'is_manual_paid' => 1,
            'manual_paid_note' => 'Отмечено вручную',
        ])->save();
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $formerPrice = $this->formerPriceFromGetTeam();
        $this->assertNotNull($formerPrice);
        $this->assertTrue((bool) ($formerPrice['effective_is_paid'] ?? false));
        $this->assertFalse((bool) ($formerPrice['can_clear_former_charge'] ?? true));
        $this->assertSame(
            FormerMemberMonthChargeService::REASON_PAID,
            (string) ($formerPrice['former_charge_clear_block_reason'] ?? '')
        );

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->clearPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.charge.0', FormerMemberMonthChargeService::REASON_PAID);
    }

    public function test_users_tab_year_prices_do_not_include_clear_flags(): void
    {
        $this->makeUnpaidRow($this->formerStudent, 326700);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        $json = $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'year' => 2026,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_former_member', true)
            ->json();

        $february = collect($json['months'] ?? [])->firstWhere('new_month', '2026-02-01');
        $this->assertNotNull($february);
        $this->assertArrayNotHasKey('can_clear_former_charge', $february);
        $this->assertArrayNotHasKey('former_charge_clear_block_reason', $february);
        $this->assertEquals(3267, (float) $february['price']);
    }
}
