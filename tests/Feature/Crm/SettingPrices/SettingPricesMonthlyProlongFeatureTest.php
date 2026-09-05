<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Enums\AuditEvent;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\LessonPackages\UserLessonPackageAutoProlongGuard;
use App\Services\Pricing\UserPercentDiscount;
use App\Services\SettingPrices\MonthlyPricesProlongReport;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Ручная пролонгация абонементов M → M+1 на вкладке «По месяцам».
 *
 * @see /docs/documentation/setting-prices-monthly-users.html#month-prolong
 */
final class SettingPricesMonthlyProlongFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $package;

    private TeamUserSyncService $teamSync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->teamSync = app(TeamUserSyncService::class);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа пролонгация',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'name' => 'Иван',
            'lastname' => 'Ученик',
        ]);
        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 60)->create([
            'name' => 'Фикс месяц',
            'price_cents' => 800000,
            'is_active' => true,
        ]);
    }

    public function test_preview_does_not_write_and_apply_copies_package_with_recalculated_price(): void
    {
        $this->seedSourceMonth();

        $this->package->update(['price_cents' => 1000000]);

        $preview = $this->postJson(route('setting-prices.prolong-month.preview'), [
            'selectedDate' => 'Сентябрь 2026',
        ]);
        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_apply', true)
            ->assertJsonPath('source_month', '2026-09-01')
            ->assertJsonPath('target_month', '2026-10-01')
            ->assertJsonPath('counts.students_create', 1)
            ->assertJsonPath('counts.teams_set', 1);
        $this->assertStringContainsString('Будет пролонгировано', (string) $preview->json('message'));

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
        ]);
        $this->assertDatabaseMissing('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
        ]);

        $apply = $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ]);
        $apply->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('counts.students_create', 1);
        $this->assertStringContainsString('Пролонгировано', (string) $apply->json('message'));

        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $this->package->id,
            'price_cents' => 1000000,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $this->package->id,
            'price_cents' => 1000000,
            'is_paid' => 0,
        ]);
        $target = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2026-10-01')
            ->first();
        $this->assertNotNull($target);
        $this->assertNull($target->is_manual_paid);

        $ulp = UserLessonPackage::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('billing_month', '2026-10-01')
            ->first();
        $this->assertNotNull($ulp);
        $this->assertNull($ulp->starts_at);
        $this->assertFalse((bool) $ulp->auto_prolong_enabled);
        $this->assertSame(1000000, (int) $ulp->fee_amount_cents);

        $this->assertTrue(
            MyLog::query()->where('event', AuditEvent::PricingMonthProlonged->value)->exists()
        );
    }

    public function test_apply_uses_current_card_percent_not_source_amount(): void
    {
        $this->student->update([
            'discount_percent' => 10,
            'discount_comment' => 'Карточка',
        ]);
        $this->seedSourceMonth(priceCents: 800000, discountPercent: 50);

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])->assertOk();

        $expected = UserPercentDiscount::payableCentsForUser(800000, $this->student->fresh());
        $this->assertSame(720000, $expected);

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2026-10-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame($expected, (int) $row->price_cents);
        $this->assertSame(10, (int) $row->discount_percent);
        $this->assertSame('Карточка', $row->discount_comment);
    }

    public function test_preview_skip_reasons_count_students_and_teams_separately(): void
    {
        $preview = $this->postJson(route('setting-prices.prolong-month.preview'), [
            'selectedDate' => 'Октябрь 2026',
        ]);
        $preview->assertOk()
            ->assertJsonPath('counts.students_skip', 1)
            ->assertJsonPath('counts.teams_skip', 1);

        $empty = collect($preview->json('skip_reasons'))
            ->firstWhere('reason', MonthlyPricesProlongReport::REASON_EMPTY_SOURCE);
        $this->assertIsArray($empty);
        $this->assertSame(1, (int) $empty['students']);
        $this->assertSame(1, (int) $empty['teams']);
        $this->assertSame('В октябре не установлены абонементы', $empty['label']);
        $this->assertArrayNotHasKey('count', $empty);
    }

    public function test_skips_empty_source_new_member_former_and_already_set(): void
    {
        $this->seedSourceMonth();

        $newcomer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'name' => 'Новый',
            'lastname' => 'Ученик',
        ]);
        $this->teamSync->syncTeamsForStudent($newcomer, [(int) $this->team->id]);

        $former = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'name' => 'Бывший',
            'lastname' => 'Ученик',
        ]);
        $this->teamSync->syncTeamsForStudent($former, [(int) $this->team->id]);
        UserPrice::forceCreate([
            'user_id' => $former->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 800000,
            'lesson_package_id' => $this->package->id,
            'is_paid' => 0,
        ]);
        $this->teamSync->syncTeamsForStudent($former, []);

        $other = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(8, 60)->create([
            'name' => 'Другой',
            'price_cents' => 500000,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'price_cents' => 111000,
            'lesson_package_id' => $other->id,
            'is_paid' => 0,
        ]);

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('counts.students_create', 0);

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $newcomer->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
        ]);
        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $former->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $other->id,
            'price_cents' => 111000,
        ]);
    }

    public function test_skips_paid_target_and_auto_prolong_guard(): void
    {
        $this->seedSourceMonth();

        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'price_cents' => 0,
            'lesson_package_id' => null,
            'is_paid' => 1,
        ]);

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('counts.students_create', 0);

        $this->assertSame(0, (int) UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('new_month', '2026-10-01')
            ->value('lesson_package_id'));

        UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('new_month', '2026-10-01')
            ->delete();

        $classic = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 60)->create([
            'name' => 'Classic auto',
            'price_cents' => 300000,
        ]);
        UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'lesson_package_id' => $classic->id,
            'starts_at' => '2026-05-04',
            'ends_at' => '2026-08-04',
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount_cents' => 300000,
            'is_paid' => false,
            'auto_prolong_enabled' => true,
        ]);
        $this->assertTrue($this->autoProlongGuard()->isBlocked((int) $this->student->id));

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('counts.students_create', 0);

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_second_apply_is_idempotent(): void
    {
        $this->seedSourceMonth();

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])->assertOk()->assertJsonPath('counts.students_create', 1);

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('counts.students_create', 0)
            ->assertJsonPath('counts.students_unchanged', 1)
            ->assertJsonPath('counts.teams_unchanged', 1);

        $this->assertSame(1, UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2026-10-01')
            ->count());
    }

    public function test_postpay_without_permission_is_skipped_with_permission_is_copied(): void
    {
        $postpay = LessonPackage::factory()->forPartner((int) $this->partner->id)->postpay()->create([
            'name' => 'Постоплата',
            'price_cents' => 50000,
        ]);
        TeamPrice::query()->create([
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 50000,
            'lesson_package_id' => $postpay->id,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 0,
            'lesson_package_id' => $postpay->id,
            'is_paid' => 0,
        ]);

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('counts.students_create', 0);

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->student->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $postpay->id,
        ]);

        $this->grantPermission('lessonPackages.type.postpay');

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('counts.students_create', 1);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $postpay->id,
        ]);
        $this->assertSame(0, UserLessonPackage::query()
            ->where('user_id', $this->student->id)
            ->where('billing_month', '2026-10-01')
            ->count());
    }

    public function test_does_not_copy_foreign_partner_rows(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'deleted_at' => null,
        ]);
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => true,
        ]);
        $this->teamSync->syncTeamsForStudent($foreignStudent, [(int) $foreignTeam->id]);
        $foreignPackage = LessonPackage::factory()->forPartner((int) $this->foreignPartner->id)->create([
            'price_cents' => 400000,
        ]);
        TeamPrice::query()->create([
            'team_id' => $foreignTeam->id,
            'new_month' => '2026-09-01',
            'price_cents' => 400000,
            'lesson_package_id' => $foreignPackage->id,
        ]);
        UserPrice::forceCreate([
            'user_id' => $foreignStudent->id,
            'team_id' => $foreignTeam->id,
            'new_month' => '2026-09-01',
            'price_cents' => 400000,
            'lesson_package_id' => $foreignPackage->id,
            'is_paid' => 0,
        ]);

        $this->seedSourceMonth();
        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])->assertOk();

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $foreignStudent->id,
            'new_month' => '2026-10-01',
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_disabled_student_is_not_copied_even_with_package_in_source_month(): void
    {
        $this->seedSourceMonth();

        $disabled = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => false,
            'name' => 'Выключен',
            'lastname' => 'Ученик',
        ]);
        $this->teamSync->syncTeamsForStudent($disabled, [(int) $this->team->id]);
        UserPrice::forceCreate([
            'user_id' => $disabled->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 800000,
            'lesson_package_id' => $this->package->id,
            'is_paid' => 0,
        ]);

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('counts.students_create', 1);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $this->package->id,
        ]);
        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $disabled->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
        ]);
    }

    public function test_empty_source_month_does_not_create_empty_target_rows(): void
    {
        $this->seedSourceMonth();

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Октябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('can_apply', false)
            ->assertJsonPath('counts.students_create', 0)
            ->assertJsonPath('counts.teams_set', 0);

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->student->id,
            'new_month' => '2026-11-01',
        ]);
        $this->assertDatabaseMissing('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2026-11-01',
        ]);
    }

    public function test_skips_laid_out_target_ulp_and_does_not_overwrite(): void
    {
        $this->seedSourceMonth();

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'starts_at' => '2026-10-05',
            'ends_at' => '2026-10-31',
            'billing_month' => '2026-10-01',
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount_cents' => 800000,
            'is_paid' => false,
            'auto_prolong_enabled' => false,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'price_cents' => 0,
            'lesson_package_id' => null,
            'user_lesson_package_id' => $ulp->id,
            'is_paid' => 0,
        ]);

        $apply = $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ]);
        $apply->assertOk()
            ->assertJsonPath('counts.students_create', 0);

        $laid = collect($apply->json('skip_reasons'))
            ->firstWhere('reason', MonthlyPricesProlongReport::REASON_LAID_OUT);
        $this->assertIsArray($laid);
        $this->assertSame(1, (int) $laid['students']);
        $this->assertArrayNotHasKey('count', $laid);

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2026-10-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->price_cents);
        $this->assertNull($this->packageId($row->lesson_package_id));
        $this->assertSame((int) $ulp->id, (int) $row->user_lesson_package_id);
    }

    public function test_december_apply_writes_january_of_next_year(): void
    {
        TeamPrice::query()->create([
            'team_id' => $this->team->id,
            'new_month' => '2026-12-01',
            'price_cents' => 800000,
            'lesson_package_id' => $this->package->id,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-12-01',
            'price_cents' => 800000,
            'lesson_package_id' => $this->package->id,
            'is_paid' => 0,
        ]);

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Декабрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('counts.students_create', 1)
            ->assertJsonPath('target_month', '2027-01-01');

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2027-01-01',
            'lesson_package_id' => $this->package->id,
            'is_paid' => 0,
        ]);
        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2027-01-01',
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_preview_does_not_sum_student_and_team_skips_into_one_count(): void
    {
        $preview = $this->postJson(route('setting-prices.prolong-month.preview'), [
            'selectedDate' => 'Октябрь 2026',
        ]);
        $preview->assertOk();

        $empty = collect($preview->json('skip_reasons'))
            ->firstWhere('reason', MonthlyPricesProlongReport::REASON_EMPTY_SOURCE);
        $this->assertIsArray($empty);
        $students = (int) $empty['students'];
        $teams = (int) $empty['teams'];
        $this->assertGreaterThan(0, $students);
        $this->assertGreaterThan(0, $teams);
        $this->assertArrayNotHasKey('count', $empty);
        $this->assertNotSame($students + $teams, $students);
        $this->assertNotSame($students + $teams, $teams);
        $this->assertSame(
            $students,
            (int) $preview->json('counts.students_skip')
        );
        $this->assertSame(
            $teams,
            (int) $preview->json('counts.teams_skip')
        );
    }

    private function packageId(mixed $value): ?int
    {
        $id = (int) ($value ?? 0);

        return $id > 0 ? $id : null;
    }

    private function seedSourceMonth(int $priceCents = 800000, ?int $discountPercent = null): void
    {
        TeamPrice::query()->create([
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => $priceCents,
            'lesson_package_id' => $this->package->id,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => $priceCents,
            'lesson_package_id' => $this->package->id,
            'is_paid' => 1,
            'is_manual_paid' => 1,
            'discount_percent' => $discountPercent,
            'discount_comment' => $discountPercent !== null ? 'Старый снимок' : null,
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
    }

    private function autoProlongGuard(): UserLessonPackageAutoProlongGuard
    {
        return app(UserLessonPackageAutoProlongGuard::class);
    }
}
