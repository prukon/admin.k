<?php

namespace Tests\Unit\Services;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Services\UserLessonPackageConsumptionAdjuster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserLessonPackageConsumptionAdjusterTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_lessons_delta_symmetric(): void
    {
        $this->assertSame(0, UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(null, false));
        $this->assertSame(0, UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(false, false));
        $this->assertSame(0, UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(true, true));
        $this->assertSame(-1, UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(false, true));
        $this->assertSame(-1, UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(null, true));
        $this->assertSame(1, UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(true, false));
    }

    public function test_apply_remaining_lessons_delta_updates_balance(): void
    {
        $partner = Partner::withoutEvents(static fn () => Partner::factory()->create());
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $package = LessonPackage::query()->create([
            'partner_id' => $partner->id,
            'name' => 'Unit pack',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 10,
            'price_cents' => 100,
            'freeze_enabled' => false,
            'freeze_days' => 0,
            'is_active' => true,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'lessons_total' => 10,
            'lessons_remaining' => 7,
            'created_by' => $user->id,
        ]);

        UserLessonPackageConsumptionAdjuster::applyRemainingLessonsDelta($ulp, -1);
        $ulp->refresh();
        $this->assertSame(6, $ulp->lessons_remaining);

        UserLessonPackageConsumptionAdjuster::applyRemainingLessonsDelta($ulp, 1);
        $ulp->refresh();
        $this->assertSame(7, $ulp->lessons_remaining);
    }

    public function test_apply_remaining_lessons_delta_throws_when_negative_balance(): void
    {
        $partner = Partner::withoutEvents(static fn () => Partner::factory()->create());
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $package = LessonPackage::query()->create([
            'partner_id' => $partner->id,
            'name' => 'Small pack',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 3,
            'price_cents' => 100,
            'freeze_enabled' => false,
            'freeze_days' => 0,
            'is_active' => true,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'lessons_total' => 3,
            'lessons_remaining' => 0,
            'created_by' => $user->id,
        ]);

        $this->expectException(\DomainException::class);
        UserLessonPackageConsumptionAdjuster::applyRemainingLessonsDelta($ulp, -1);
    }

    public function test_apply_remaining_lessons_delta_throws_when_exceeds_total(): void
    {
        $partner = Partner::withoutEvents(static fn () => Partner::factory()->create());
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $package = LessonPackage::query()->create([
            'partner_id' => $partner->id,
            'name' => 'Pack',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 5,
            'price_cents' => 100,
            'freeze_enabled' => false,
            'freeze_days' => 0,
            'is_active' => true,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'lessons_total' => 5,
            'lessons_remaining' => 5,
            'created_by' => $user->id,
        ]);

        $this->expectException(\DomainException::class);
        UserLessonPackageConsumptionAdjuster::applyRemainingLessonsDelta($ulp, 1);
    }
}
