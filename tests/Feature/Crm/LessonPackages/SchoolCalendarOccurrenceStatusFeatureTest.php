<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class SchoolCalendarOccurrenceStatusFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantLessonPackagesView(): void
    {
        $permId = $this->permissionId('lessonPackages.view');

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_occurrence_status_store_and_history_and_week_snapshot(): void
    {
        $this->grantLessonPackagesView();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий статусы',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 10,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 10,
            'lessons_remaining' => 3,
            'created_by' => $this->user->id,
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => '2026-12-31',
            'created_by' => $this->user->id,
        ]);

        $statusAttended = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        $this->actingAs($this->user);

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $statusAttended->id,
        ])
            ->assertOk()
            ->assertJsonPath('event.lesson_occurrence_status.title', 'Посетил');

        $this->assertSame(1, UserLessonOccurrenceStatusEvent::query()->count());

        $this->getJson(route('admin.lesson-packages.school-schedule.occurrence-status.history', [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'events');

        $week = $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))->assertOk();

        $occurrences = $week->json('occurrences');
        $hit = null;
        foreach ($occurrences as $o) {
            if ((int) $o['id'] === (int) $slot->id && $o['date'] === self::WEEK_MONDAY) {
                $hit = $o;
                break;
            }
        }
        $this->assertNotNull($hit);
        $regs = $hit['registrations'] ?? [];
        $this->assertNotEmpty($regs);
        $this->assertSame('Посетил', $regs[0]['current_status']['title'] ?? null);
    }

    public function test_occurrence_status_store_forbidden_without_lesson_packages_view(): void
    {
        $other = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($other);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => 1,
            'user_lesson_package_id' => 1,
            'lesson_occurrence_status_id' => 1,
        ])->assertForbidden();
    }
}
