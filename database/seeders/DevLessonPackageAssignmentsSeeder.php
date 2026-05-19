<?php

namespace Database\Seeders;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Role;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Database\Seeders\Support\DevSchoolCalendarBinder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DevLessonPackageAssignmentsSeeder extends Seeder
{
    use GuardsDevSeedData;

    private const ASSIGNMENTS_PER_PARTNER = 16;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $binder = app(DevSchoolCalendarBinder::class);
        $userRoleId = Role::query()->where('name', 'user')->value('id');

        $partnerIds = Partner::query()->pluck('id')->all();

        foreach ($partnerIds as $partnerId) {
            $this->seedAssignmentsForPartner((int) $partnerId, $userRoleId, $binder);
        }
    }

    private function seedAssignmentsForPartner(
        int $partnerId,
        ?int $userRoleId,
        DevSchoolCalendarBinder $binder,
    ): void {
        $packages = LessonPackage::query()
            ->where('partner_id', $partnerId)
            ->where('is_active', true)
            ->get();

        if ($packages->isEmpty()) {
            return;
        }

        $studentsQuery = User::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1);

        if ($userRoleId) {
            $studentsQuery->where('role_id', $userRoleId);
        }

        $students = $studentsQuery
            ->inRandomOrder()
            ->limit(self::ASSIGNMENTS_PER_PARTNER)
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $slots = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->get();

        $createdBy = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'superadmin']))
            ->value('id');

        foreach ($students as $student) {
            /** @var LessonPackage $package */
            $package = $packages->random();

            $ulp = UserLessonPackage::query()->create([
                'user_id' => (int) $student->id,
                'lesson_package_id' => (int) $package->id,
                'starts_at' => null,
                'ends_at' => null,
                'lessons_total' => (int) $package->lessons_count,
                'lessons_remaining' => (int) $package->lessons_count,
                'fee_amount' => round($package->price_cents / 100, 2),
                'is_paid' => (bool) random_int(0, 1),
                'created_by' => $createdBy,
            ]);

            if (random_int(0, 99) >= 55 || $slots->isEmpty()) {
                continue;
            }

            $this->tryBindToCalendar($binder, $student, $ulp, $package, $slots, $createdBy);
        }
    }

    private function tryBindToCalendar(
        DevSchoolCalendarBinder $binder,
        User $student,
        UserLessonPackage $ulp,
        LessonPackage $package,
        Collection $slots,
        ?int $createdBy,
    ): void {
        $scheduleType = (string) $package->schedule_type;

        try {
            if ($scheduleType === 'flexible') {
                $this->bindFlexibleDemo($binder, $ulp, $slots, $createdBy);

                return;
            }

            if ($scheduleType === 'no_schedule') {
                $this->bindSingleDemo($binder, $ulp, $slots, $createdBy);

                return;
            }

            if ($scheduleType === 'fixed') {
                $this->bindFixedDemo($binder, $student, $ulp, $slots, $createdBy);
            }
        } catch (\Throwable $e) {
            Log::debug('DevLessonPackageAssignmentsSeeder: calendar bind skipped', [
                'user_lesson_package_id' => $ulp->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function bindFlexibleDemo(
        DevSchoolCalendarBinder $binder,
        UserLessonPackage $ulp,
        Collection $slots,
        ?int $createdBy,
    ): void {
        $ulp->loadMissing('user');

        $slot = $slots->random();
        $occurrence = DevSchoolCalendarBinder::occurrenceDateForSlot($slot);

        $binder->bindFlexible($ulp, $slot, $occurrence, $createdBy);

        if ((int) $ulp->lessons_total > 1 && random_int(0, 1) === 1) {
            $secondSlot = $slots->where('id', '!=', $slot->id)->random();
            if ($secondSlot) {
                $secondDate = DevSchoolCalendarBinder::occurrenceDateForSlot($secondSlot, $occurrence->addWeek());
                $exists = UserTeamScheduleSlot::query()
                    ->where('user_lesson_package_id', $ulp->id)
                    ->where('team_schedule_slot_id', $secondSlot->id)
                    ->whereDate('starts_at', $secondDate->toDateString())
                    ->exists();

                if (! $exists) {
                    $binder->bindFlexible($ulp->fresh(['user']), $secondSlot, $secondDate, $createdBy);
                }
            }
        }
    }

    private function bindSingleDemo(
        DevSchoolCalendarBinder $binder,
        UserLessonPackage $ulp,
        Collection $slots,
        ?int $createdBy,
    ): void {
        $ulp->loadMissing('user');
        $slot = $slots->random();
        $occurrence = DevSchoolCalendarBinder::occurrenceDateForSlot($slot);
        $binder->bindSingleLesson($ulp, $slot, $occurrence, $createdBy);
    }

    private function bindFixedDemo(
        DevSchoolCalendarBinder $binder,
        User $student,
        UserLessonPackage $ulp,
        Collection $slots,
        ?int $createdBy,
    ): void {
        foreach ($slots->shuffle() as $anchorSlot) {
            $anchorDate = DevSchoolCalendarBinder::occurrenceDateForSlot($anchorSlot);
            $patterns = collect([DevSchoolCalendarBinder::patternFromSlot($anchorSlot)]);

            try {
                $binder->bindFixed($student, $ulp->fresh(), $anchorSlot, $anchorDate, $patterns, $createdBy);

                return;
            } catch (\Throwable) {
                continue;
            }
        }
    }
}
