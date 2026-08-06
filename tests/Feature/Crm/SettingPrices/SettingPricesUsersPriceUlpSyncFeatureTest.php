<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
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
        $this->assertSame(8000.0, (float) $ulp->fee_amount);

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
        $this->assertSame(9000.0, (float) $ulp->fee_amount);
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
        $this->assertSame(8500.0, (float) $ulp->fee_amount);
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
        $this->assertSame(8700.0, (float) $ulp->fee_amount);
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
        $this->assertSame(8000.0, (float) $row->price);
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
        $this->assertSame(12000.0, (float) $ulp->fee_amount);
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
        $this->assertSame(8000.0, (float) $ulp->fee_amount);
    }

    public function test_paid_month_does_not_create_or_change_ulp(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-11-01',
            'price' => 8000,
            'is_paid' => 1,
            'lesson_package_id' => $this->fixedA->id,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->payload($this->student, 12000.0, (int) $this->fixedB->id),
            ],
        ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-11-01')
            ->first();
        $this->assertSame((int) $this->fixedA->id, (int) $row->lesson_package_id);
        $this->assertSame(8000.0, (float) $row->price);
        $this->assertNull($row->user_lesson_package_id);
        $this->assertSame(0, UserLessonPackage::query()->where('user_id', $this->student->id)->count());
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
        $this->assertSame(8000.0, (float) $ulp->fee_amount);
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
            'price' => $price,
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
