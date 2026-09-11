<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserLessonPackagePublicPayLink;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use App\Services\Schedule\ScheduleJournalMonthService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Sync users_prices ↔ user_lesson_packages при установке цен (кроме postpay).
 */
final class SettingPricesUsersPriceUlpSyncFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $fixedA;

    private LessonPackage $fixedB;

    private LessonPackage $flexible;

    private LessonPackage $postpay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа ULP sync',
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Синк',
            'lastname' => 'Ученик',
        ]);

        $this->fixedA = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 60)->create([
            'name' => 'Fixed A 4',
            'price_cents' => 800000,
            'is_active' => true,
        ]);
        $this->fixedB = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(8, 90)->create([
            'name' => 'Fixed B 8',
            'price_cents' => 1200000,
            'is_active' => true,
        ]);
        $this->flexible = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible(6, 60)->create([
            'name' => 'Flexible 6',
            'price_cents' => 900000,
            'is_active' => true,
        ]);
        $this->postpay = LessonPackage::factory()->forPartner((int) $this->partner->id)->postpay()->create([
            'name' => 'Postpay sync',
            'price_cents' => 50000,
            'is_active' => true,
        ]);
    }

    public function test_set_price_all_users_creates_ulp_for_fixed_package(): void
    {
        $row = $this->makeUserPrice(null, 0);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 8000.0, (int) $this->fixedA->id),
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $row->refresh();
        $this->assertNotNull($row->user_lesson_package_id);

        $ulp = UserLessonPackage::query()->find($row->user_lesson_package_id);
        $this->assertNotNull($ulp);
        $this->assertSame((int) $this->student->id, (int) $ulp->user_id);
        $this->assertSame((int) $this->team->id, (int) $ulp->team_id);
        $this->assertSame((int) $this->fixedA->id, (int) $ulp->lesson_package_id);
        $this->assertSame('2025-11-01', $ulp->billing_month?->format('Y-m-d'));
        $this->assertNull($ulp->starts_at);
        $this->assertSame('2025-11-30', $ulp->ends_at?->format('Y-m-d'));
        $this->assertSame(4, (int) $ulp->lessons_total);
        $this->assertSame(4, (int) $ulp->lessons_remaining);
        $this->assertSame(800000, (int) $ulp->fee_amount_cents);

        $assignments = app(ScheduleJournalMonthService::class)
            ->fixedAssignmentsForUser((int) $this->partner->id, (int) $this->student->id);
        $match = collect($assignments)->firstWhere('id', (int) $ulp->id);
        $this->assertNotNull($match);
        $this->assertTrue((bool) $match['placeable']);
    }

    public function test_set_price_all_users_creates_ulp_for_flexible_package(): void
    {
        $this->makeUserPrice(null, 0);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 9000.0, (int) $this->flexible->id),
            ],
        ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-11-01')
            ->first();
        $this->assertNotNull($row?->user_lesson_package_id);

        $ulp = UserLessonPackage::query()->find($row->user_lesson_package_id);
        $this->assertSame((int) $this->flexible->id, (int) $ulp->lesson_package_id);
        $this->assertSame(6, (int) $ulp->lessons_total);
        $this->assertSame(900000, (int) $ulp->fee_amount_cents);
        $this->assertSame('2025-11-01', $ulp->billing_month?->format('Y-m-d'));
        $this->assertSame('2025-11-01', $ulp->starts_at?->format('Y-m-d'));
        $this->assertSame('2025-11-30', $ulp->ends_at?->format('Y-m-d'));
        $this->assertFalse($ulp->isLaidOutInSchedule());
        $this->assertSame(6, $ulp->calendarSlotsRemaining());
    }

    public function test_postpay_does_not_create_ulp(): void
    {
        $this->grantPermission('lessonPackages.type.postpay');
        $this->makeUserPrice(null, 0);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 0.0, (int) $this->postpay->id),
            ],
        ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-11-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $this->postpay->id, (int) $row->lesson_package_id);
        $this->assertNull($row->user_lesson_package_id);
        $this->assertSame(0, UserLessonPackage::query()->where('user_id', $this->student->id)->count());
    }

    public function test_price_change_syncs_fee_amount_on_unplaced_ulp(): void
    {
        $row = $this->assignFixedA(8000.0);
        $ulpId = (int) $row->user_lesson_package_id;
        $this->assertGreaterThan(0, $ulpId);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 8500.0, (int) $this->fixedA->id),
            ],
        ])->assertOk();

        $ulp = UserLessonPackage::query()->find($ulpId);
        $this->assertNotNull($ulp);
        $this->assertSame(850000, (int) $ulp->fee_amount_cents);
        $this->assertSame((int) $this->fixedA->id, (int) $ulp->lesson_package_id);
    }

    public function test_price_change_syncs_fee_amount_when_ulp_already_placed(): void
    {
        $row = $this->assignFixedA(8000.0);
        $ulp = UserLessonPackage::query()->find($row->user_lesson_package_id);
        $this->assertNotNull($ulp);
        $ulp->update(['starts_at' => '2025-11-03', 'ends_at' => '2025-12-03']);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 8700.0, (int) $this->fixedA->id),
            ],
        ])->assertOk();

        $ulp->refresh();
        $this->assertSame(870000, (int) $ulp->fee_amount_cents);
        $this->assertSame((int) $this->fixedA->id, (int) $ulp->lesson_package_id);
    }

    public function test_package_change_blocked_when_ulp_placed(): void
    {
        $row = $this->assignFixedA(8000.0);
        $ulpId = (int) $row->user_lesson_package_id;
        $this->assertGreaterThan(0, $ulpId);
        UserLessonPackage::query()->whereKey($ulpId)->update([
            'starts_at' => '2025-11-03',
            'ends_at' => '2025-12-03',
        ]);

        $response = $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 12000.0, (int) $this->fixedB->id),
            ],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);

        $row->refresh();
        $this->assertSame((int) $this->fixedA->id, (int) $row->lesson_package_id);
        $this->assertSame(800000, (int) $row->price_cents);
        $this->assertSame($ulpId, (int) $row->user_lesson_package_id);
        $this->assertSame((int) $this->fixedA->id, (int) UserLessonPackage::query()->find($ulpId)->lesson_package_id);
    }

    public function test_package_change_updates_unplaced_ulp(): void
    {
        $row = $this->assignFixedA(8000.0);
        $ulpId = (int) $row->user_lesson_package_id;
        $this->assertGreaterThan(0, $ulpId);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 12000.0, (int) $this->fixedB->id),
            ],
        ])->assertOk();

        $ulp = UserLessonPackage::query()->find($ulpId);
        $this->assertNotNull($ulp);
        $this->assertSame((int) $this->fixedB->id, (int) $ulp->lesson_package_id);
        $this->assertSame(8, (int) $ulp->lessons_total);
        $this->assertSame(8, (int) $ulp->lessons_remaining);
        $this->assertSame(1200000, (int) $ulp->fee_amount_cents);
        $row->refresh();
        $this->assertSame($ulpId, (int) $row->user_lesson_package_id);
    }

    public function test_clearing_package_deletes_unplaced_ulp(): void
    {
        $row = $this->assignFixedA(8000.0);
        $ulpId = (int) $row->user_lesson_package_id;
        $this->assertGreaterThan(0, $ulpId);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 0.0, null),
            ],
        ])->assertOk();

        $row->refresh();
        $this->assertNull($row->lesson_package_id);
        $this->assertNull($row->user_lesson_package_id);
        $this->assertNull(UserLessonPackage::query()->find($ulpId));
    }

    public function test_set_team_price_creates_ulp_for_students(): void
    {
        $this->makeUserPrice(null, 0);

        $this->postJson(route('setTeamPrice'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->fixedA->id,
        ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-11-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $this->fixedA->id, (int) $row->lesson_package_id);
        $this->assertNotNull($row->user_lesson_package_id);
        $ulp = UserLessonPackage::query()->find($row->user_lesson_package_id);
        $this->assertSame(4, (int) $ulp->lessons_total);
        $this->assertSame(800000, (int) $ulp->fee_amount_cents);
    }

    public function test_paid_fixed_month_rejects_package_change(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-11-01',
            'price_cents' => 800000,
            'is_paid' => 1,
            'lesson_package_id' => $this->fixedA->id,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 12000.0, (int) $this->fixedB->id),
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-11-01')
            ->first();
        $this->assertSame((int) $this->fixedA->id, (int) $row->lesson_package_id);
        $this->assertSame(800000, (int) $row->price_cents);
        $this->assertNull($row->user_lesson_package_id);
        $this->assertSame(0, UserLessonPackage::query()->where('user_id', $this->student->id)->count());
    }

    public function test_placed_flexible_package_replace_updates_volume_and_price_when_unpaid(): void
    {
        [$from, $to] = $this->makeFlexiblePair(8, 5000.0, 12, 8000.0);
        $row = $this->assignFlexible($from, 5000.0);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->layOutFlexible($ulp);
        $ulp->update(['lessons_remaining' => 5]);
        $this->assertTrue($ulp->fresh()->isLaidOutInSchedule());

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 8000.0, (int) $to->id),
            ],
        ])->assertOk();

        $ulp->refresh();
        $row->refresh();
        $this->assertSame((int) $to->id, (int) $row->lesson_package_id);
        $this->assertSame((int) $to->id, (int) $ulp->lesson_package_id);
        $this->assertSame(800000, (int) $row->price_cents);
        $this->assertSame(800000, (int) $ulp->fee_amount_cents);
        $this->assertSame(12, (int) $ulp->lessons_total);
        $this->assertSame(9, (int) $ulp->lessons_remaining);
        $this->assertTrue($ulp->isLaidOutInSchedule());
    }

    public function test_placed_flexible_package_replace_keeps_paid_price(): void
    {
        [$from, $to] = $this->makeFlexiblePair(8, 5000.0, 12, 8000.0);
        $row = $this->assignFlexible($from, 5000.0);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->layOutFlexible($ulp);
        $ulp->update(['lessons_remaining' => 5, 'is_paid' => true]);
        $row->update(['is_paid' => 1]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 8000.0, (int) $to->id),
            ],
        ])->assertOk();

        $ulp->refresh();
        $row->refresh();
        $this->assertSame((int) $to->id, (int) $row->lesson_package_id);
        $this->assertSame((int) $to->id, (int) $ulp->lesson_package_id);
        $this->assertSame(500000, (int) $row->price_cents);
        $this->assertSame(500000, (int) $ulp->fee_amount_cents);
        $this->assertSame(12, (int) $ulp->lessons_total);
        $this->assertSame(9, (int) $ulp->lessons_remaining);
        $this->assertTrue((bool) $row->effective_is_paid);
    }

    public function test_placed_flexible_replace_blocked_when_new_volume_below_consumed(): void
    {
        [$from, $to] = $this->makeFlexiblePair(8, 5000.0, 4, 3000.0);
        $row = $this->assignFlexible($from, 5000.0);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->layOutFlexible($ulp);
        $ulp->update(['lessons_remaining' => 2]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 3000.0, (int) $to->id),
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);

        $row->refresh();
        $ulp->refresh();
        $this->assertSame((int) $from->id, (int) $row->lesson_package_id);
        $this->assertSame((int) $from->id, (int) $ulp->lesson_package_id);
        $this->assertSame(500000, (int) $row->price_cents);
        $this->assertSame(8, (int) $ulp->lessons_total);
        $this->assertSame(2, (int) $ulp->lessons_remaining);
    }

    public function test_mass_apply_saves_other_student_when_one_flexible_replace_is_blocked(): void
    {
        [$from, $to] = $this->makeFlexiblePair(8, 5000.0, 4, 3000.0);
        $okStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Ок',
            'lastname' => 'Ученик',
        ]);

        $blockedRow = $this->assignFlexible($from, 5000.0);
        $blockedUlp = UserLessonPackage::query()->findOrFail($blockedRow->user_lesson_package_id);
        $this->layOutFlexible($blockedUlp);
        $blockedUlp->update(['lessons_remaining' => 2]);

        $okRow = $this->assignFlexible($from, 5000.0, $okStudent);

        $response = $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 3000.0, (int) $to->id),
                $this->payload($okStudent, 3000.0, (int) $to->id),
            ],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);

        $okRow->refresh();
        $okUlp = UserLessonPackage::query()->findOrFail($okRow->user_lesson_package_id);
        $this->assertSame((int) $to->id, (int) $okRow->lesson_package_id);
        $this->assertSame((int) $to->id, (int) $okUlp->lesson_package_id);
        $this->assertSame(300000, (int) $okRow->price_cents);

        $blockedRow->refresh();
        $blockedUlp->refresh();
        $this->assertSame((int) $from->id, (int) $blockedRow->lesson_package_id);
        $this->assertSame(8, (int) $blockedUlp->lessons_total);
    }

    public function test_unpaid_flexible_replace_rotates_public_pay_sms_link(): void
    {
        [$from, $to] = $this->makeFlexiblePair(8, 5000.0, 12, 8000.0);
        $row = $this->assignFlexible($from, 5000.0);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->layOutFlexible($ulp);

        $oldCode = 'AbCdEfGh23';
        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'partner_id' => $this->partner->id,
            'token' => bin2hex(random_bytes(32)),
            'short_code' => $oldCode,
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 8000.0, (int) $to->id),
            ],
        ])->assertOk();

        $link = UserLessonPackagePublicPayLink::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->firstOrFail();
        $this->assertNotSame($oldCode, (string) $link->short_code);
        $this->assertNotSame('', (string) $link->short_code);
    }

    public function test_users_tab_save_replaces_paid_flexible_and_keeps_price(): void
    {
        [$from, $to] = $this->makeFlexiblePair(8, 5000.0, 12, 8000.0);
        $row = $this->assignFlexible($from, 5000.0);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->layOutFlexible($ulp);
        $ulp->update(['lessons_remaining' => 5, 'is_paid' => true]);
        $row->update(['is_paid' => 1]);

        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2025,
            'prices' => [[
                'new_month' => '2025-11-01',
                'price' => 8000,
                'lesson_package_id' => (int) $to->id,
            ]],
        ])->assertOk();

        $row->refresh();
        $ulp->refresh();
        $this->assertSame((int) $to->id, (int) $row->lesson_package_id);
        $this->assertSame(500000, (int) $row->price_cents);
        $this->assertSame(12, (int) $ulp->lessons_total);
        $this->assertSame(9, (int) $ulp->lessons_remaining);
        $this->assertSame(500000, (int) $ulp->fee_amount_cents);
    }

    public function test_reapply_same_package_creates_missing_ulp_without_value_change(): void
    {
        $row = $this->makeUserPrice((int) $this->fixedA->id, 8000);
        $this->assertNull($row->user_lesson_package_id);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 8000.0, (int) $this->fixedA->id),
            ],
        ])->assertOk();

        $row->refresh();
        $this->assertNotNull($row->user_lesson_package_id);
        $ulp = UserLessonPackage::query()->find($row->user_lesson_package_id);
        $this->assertSame((int) $this->fixedA->id, (int) $ulp->lesson_package_id);
        $this->assertSame(800000, (int) $ulp->fee_amount_cents);
        $this->assertTrue(
            (bool) collect(app(ScheduleJournalMonthService::class)
                ->fixedAssignmentsForUser((int) $this->partner->id, (int) $this->student->id))
                ->firstWhere('id', (int) $ulp->id)['placeable']
        );
    }

    public function test_reapply_same_package_fixes_monthly_ends_at_on_unplaced_ulp(): void
    {
        $row = $this->assignFixedA(8000);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $ulp->update(['ends_at' => '2026-08-31']);
        $this->assertNull($ulp->fresh()->starts_at);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 8000.0, (int) $this->fixedA->id),
            ],
        ])->assertOk();

        $ulp->refresh();
        $this->assertSame('2025-11-30', $ulp->ends_at?->format('Y-m-d'));
        $this->assertNull($ulp->starts_at);
    }

    public function test_reapply_same_flexible_fixes_monthly_starts_at_on_unplaced_ulp(): void
    {
        $this->makeUserPrice(null, 0);
        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 9000.0, (int) $this->flexible->id),
            ],
        ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-11-01')
            ->firstOrFail();
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $ulp->update(['starts_at' => null]);
        $this->assertNull($ulp->fresh()->starts_at);
        $this->assertFalse($ulp->fresh()->isLaidOutInSchedule());

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 9000.0, (int) $this->flexible->id),
            ],
        ])->assertOk();

        $ulp->refresh();
        $this->assertSame('2025-11-01', $ulp->starts_at?->format('Y-m-d'));
        $this->assertSame('2025-11-30', $ulp->ends_at?->format('Y-m-d'));
        $this->assertFalse($ulp->isLaidOutInSchedule());
    }

    /**
     * @return array{0: LessonPackage, 1: LessonPackage}
     */
    private function makeFlexiblePair(int $fromLessons, float $fromPrice, int $toLessons, float $toPrice): array
    {
        $from = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible($fromLessons, 60)->create([
            'name' => 'Flex from '.$fromLessons,
            'price_cents' => (int) round($fromPrice * 100),
            'is_active' => true,
        ]);
        $to = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible($toLessons, 60)->create([
            'name' => 'Flex to '.$toLessons,
            'price_cents' => (int) round($toPrice * 100),
            'is_active' => true,
        ]);

        return [$from, $to];
    }

    private function assignFlexible(LessonPackage $package, float $price, ?User $student = null): UserPrice
    {
        $student = $student ?? $this->student;
        $row = UserPrice::forceCreate([
            'user_id' => $student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-11-01',
            'price_cents' => (int) round($price * 100),
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($student, $price, (int) $package->id),
            ],
        ])->assertOk();

        $row->refresh();
        $this->assertNotNull($row->user_lesson_package_id);

        return $row;
    }

    private function layOutFlexible(UserLessonPackage $ulp): void
    {
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $ulp->user_id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2025-11-03',
            'ends_at' => '2025-11-03',
            'created_by' => $this->user->id,
        ]);
    }

    private function assignFixedA(float $price): UserPrice
    {
        $row = $this->makeUserPrice(null, 0);
        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, $price, (int) $this->fixedA->id),
            ],
        ])->assertOk();
        $row->refresh();

        return $row;
    }

    private function makeUserPrice(?int $packageId, float $price): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-11-01',
            'price_cents' => (int) round($price * 100),
            'is_paid' => 0,
            'lesson_package_id' => $packageId,
        ]);
    }

    /**
     * @return array{user_id:int,price:float,lesson_package_id:int|null,user:array{name:string}}
     */
    private function payload(User $student, float $price, ?int $lessonPackageId): array
    {
        return [
            'user_id' => (int) $student->id,
            'price' => $price,
            'lesson_package_id' => $lessonPackageId,
            'user' => ['name' => (string) $student->name],
        ];
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
}
