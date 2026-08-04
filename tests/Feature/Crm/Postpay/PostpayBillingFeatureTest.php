<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Postpay;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\Postpay\PostpayMonth;
use App\Services\TeamUserSyncService;
use Carbon\Carbon;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Постоплата: шаблон postpay + users_prices (без user_lesson_packages).
 */
final class PostpayBillingFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $postpayPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('setPrices.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('lessonPackages.type.postpay'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->student, (int) $this->team->id);

        $this->postpayPackage = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->postpay()
            ->create([
                'name' => 'Постоплата тест',
                'price_cents' => 50000, // 500 ₽
            ]);
    }

    public function test_can_create_postpay_lesson_package_template(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Посещение после',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'price' => 350,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $pkg = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Посещение после')
            ->first();

        $this->assertNotNull($pkg);
        $this->assertSame(LessonPackage::SCHEDULE_TYPE_POSTPAY, $pkg->schedule_type);
        $this->assertSame(35000, (int) $pkg->price_cents);
        $this->assertSame(31, (int) $pkg->duration_days);
        $this->assertSame(1, (int) $pkg->lessons_count);
        $this->assertFalse((bool) $pkg->freeze_enabled);
    }

    public function test_set_price_all_users_assigns_postpay_and_recalculates_from_zero_visits(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price' => 100,
            'is_paid' => false,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Август 2026',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 9999,
                    'lesson_package_id' => $this->postpayPackage->id,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame((int) $this->postpayPackage->id, (int) $row->lesson_package_id);
        $this->assertSame(0.0, (float) $row->price);
        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    public function test_journal_create_postpay_status_recalculates_users_prices(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price' => 0,
            'lesson_package_id' => $this->postpayPackage->id,
            'is_paid' => false,
        ]);

        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $this->postJson(route('schedule.update'), [
            'user_id' => $this->student->id,
            'create_postpay' => 1,
            'team_id' => $this->team->id,
            'occurrence_date' => '2026-08-10',
            'lesson_occurrence_status_id' => $attendedId,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(500.0, (float) $row->price);
        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    public function test_journal_blocks_status_change_when_postpay_month_is_paid(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price' => 500,
            'lesson_package_id' => $this->postpayPackage->id,
            'is_paid' => true,
        ]);

        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $this->postJson(route('schedule.update'), [
            'user_id' => $this->student->id,
            'create_postpay' => 1,
            'team_id' => $this->team->id,
            'occurrence_date' => '2026-08-12',
            'lesson_occurrence_status_id' => $attendedId,
        ])->assertStatus(422);

        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    public function test_postpay_pay_available_from_is_first_day_of_next_month_moscow(): void
    {
        $this->assertSame(
            '2026-09-01',
            PostpayMonth::payAvailableFrom('2026-08-01')->format('Y-m-d')
        );
        $this->assertStringContainsString('сентября', PostpayMonth::payAvailableFromLabel('2026-08-01'));

        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00', PostpayMonth::TIMEZONE));
        $this->assertFalse(PostpayMonth::isPayAvailableNow('2026-08-01'));

        Carbon::setTestNow(Carbon::parse('2026-09-01 00:00:00', PostpayMonth::TIMEZONE));
        $this->assertTrue(PostpayMonth::isPayAvailableNow('2026-08-01'));

        Carbon::setTestNow();
    }

    public function test_template_price_change_resyncs_unpaid_postpay_row(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price' => 500,
            'lesson_package_id' => $this->postpayPackage->id,
            'is_paid' => false,
        ]);

        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->postJson(route('schedule.update'), [
            'user_id' => $this->student->id,
            'create_postpay' => 1,
            'team_id' => $this->team->id,
            'occurrence_date' => '2026-08-05',
            'lesson_occurrence_status_id' => $attendedId,
        ])->assertOk();

        $this->putJson(route('admin.lesson-packages.update', $this->postpayPackage), [
            'name' => $this->postpayPackage->name,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'price' => 700,
        ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(700.0, (float) $row->price);
    }
}
