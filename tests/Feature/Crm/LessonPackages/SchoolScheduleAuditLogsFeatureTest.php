<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Аудит «Расписание школы»: запись событий schedule.* и endpoint logs-data.
 */
final class SchoolScheduleAuditLogsFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->assertSame(
            1,
            (int) CarbonImmutable::parse(self::WEEK_MONDAY)->format('N'),
            'Тестовая дата должна быть понедельником (ISO weekday 1).'
        );
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function studentUser(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'name'       => 'Аудит',
            'lastname'   => 'Ученик',
            'is_enabled' => 1,
        ]);
    }

    private function mondaySlot(string $timeStart = '10:00', string $timeEnd = '11:00'): TeamScheduleSlot
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        return TeamScheduleSlot::query()->create([
            'partner_id'  => $this->partner->id,
            'team_id'     => $team->id,
            'location_id' => null,
            'weekday'     => 1,
            'time_start'  => $timeStart,
            'time_end'    => $timeEnd,
            'date_start'  => '2026-01-01',
            'date_end'    => '9999-12-31',
            'is_enabled'  => 1,
        ]);
    }

    /**
     * @return array{patterns: list<array{weekday: int, time_start: string, time_end: string}>}
     */
    private function fixedCalendarBindPattern(int $weekday, string $timeStart, string $timeEnd): array
    {
        return [
            'patterns' => [
                [
                    'weekday'    => $weekday,
                    'time_start' => $timeStart,
                    'time_end'   => $timeEnd,
                ],
            ],
        ];
    }

    private function latestLog(AuditEvent $event): ?MyLog
    {
        return MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', $event->value)
            ->latest('id')
            ->first();
    }

    public function test_logs_data_returns_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('logs.data.school-schedule', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->getJson(route('logs.data.school-schedule', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertStatus(403);
    }

    public function test_school_schedule_page_renders_history_icon_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('historyModal', false)
            ->assertSee('fa-clock-rotate-left', false)
            ->assertSee('showLogModal', false);
    }

    public function test_trial_registration_writes_schedule_trial_registered_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $student->forceFill(['has_used_school_schedule_trial' => false])->save();
        $slot = $this->mondaySlot();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id'               => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date'       => self::WEEK_MONDAY,
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::ScheduleTrialRegistered);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::ScheduleTrialRegistered->level(), $log->level);
        $this->assertStringContainsString('Пробное занятие', (string) $log->description);
        $this->assertStringContainsString('Аудит', (string) $log->description);
        $this->assertSame((int) $student->id, (int) $log->user_id);
    }

    public function test_trial_destroy_writes_schedule_trial_cancelled_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot('11:00', '12:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id'               => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date'       => self::WEEK_MONDAY,
        ])->assertOk();

        $trialId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('is_trial_lesson', true)
            ->value('id');

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $trialId,
        ]))->assertOk();

        $log = $this->latestLog(AuditEvent::ScheduleTrialCancelled);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Отменено пробное занятие', (string) $log->description);
        $this->assertStringContainsString(self::WEEK_MONDAY, (string) $log->target_label);
    }

    public function test_assign_flexible_writes_schedule_flexible_linked_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id'    => $this->partner->id,
            'name'          => 'Гибкий аудит',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 5,
            'price_cents'   => 5000,
            'freeze_enabled'=> 0,
            'freeze_days'   => 0,
            'is_active'     => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id'           => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at'         => '2026-04-01',
            'ends_at'           => '2026-12-31',
            'lessons_total'     => 5,
            'lessons_remaining' => 3,
            'created_by'        => $this->user->id,
        ]);
        $slot = $this->mondaySlot('12:00', '13:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id'  => $slot->id,
            'occurrence_date'        => self::WEEK_MONDAY,
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::ScheduleFlexibleLinked);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Гибкий абонемент #'.$ulp->id, (string) $log->description);
        $this->assertStringContainsString('Аудит', (string) $log->description);
        $this->assertStringContainsString(self::WEEK_MONDAY, (string) $log->description);
    }

    public function test_assign_fixed_writes_schedule_fixed_linked_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id'    => $this->partner->id,
            'name'          => 'Фикс аудит',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents'   => 8000,
            'freeze_enabled'=> 0,
            'freeze_days'   => 0,
            'is_active'     => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id'           => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at'         => null,
            'ends_at'           => null,
            'lessons_total'     => 1,
            'lessons_remaining' => 1,
            'fee_amount'        => '80.00',
            'is_paid'           => false,
            'created_by'        => $this->user->id,
        ]);
        $slot = $this->mondaySlot('13:00', '14:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), array_merge([
            'user_id'                => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id'  => $slot->id,
            'anchor_date'            => self::WEEK_MONDAY,
        ], $this->fixedCalendarBindPattern(1, '13:00', '14:00')))->assertOk();

        $log = $this->latestLog(AuditEvent::ScheduleFixedLinked);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Фиксированный абонемент #'.$ulp->id, (string) $log->description);
        $this->assertStringContainsString('записей:', (string) $log->description);
        $this->assertStringContainsString(self::WEEK_MONDAY, (string) $log->description);
    }

    public function test_assign_single_lesson_writes_schedule_single_lesson_registered_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id'    => $this->partner->id,
            'name'          => 'Разовое assign',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents'   => 150000,
            'freeze_enabled'=> 0,
            'freeze_days'   => 0,
            'is_active'     => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id'           => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at'         => null,
            'ends_at'           => null,
            'lessons_total'     => 1,
            'lessons_remaining' => 1,
            'created_by'        => $this->user->id,
        ]);
        $slot = $this->mondaySlot('14:00', '15:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-single-lesson'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id'  => $slot->id,
            'occurrence_date'        => self::WEEK_MONDAY,
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::ScheduleSingleLessonRegistered);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Разовое занятие', (string) $log->description);
        $this->assertStringContainsString('назначение #'.$ulp->id, (string) $log->description);
    }

    public function test_store_single_lesson_registration_writes_schedule_single_lesson_registered_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id'    => $this->partner->id,
            'name'          => 'Разовое store',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents'   => 200000,
            'freeze_enabled'=> 0,
            'freeze_days'   => 0,
            'is_active'     => 1,
        ]);
        $slot = $this->mondaySlot('15:00', '16:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id'               => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date'       => self::WEEK_MONDAY,
            'lesson_package_id'     => $package->id,
            'fee_amount'            => 1800,
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::ScheduleSingleLessonRegistered);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Разовое занятие', (string) $log->description);
        $this->assertStringContainsString('Аудит', (string) $log->description);
        $this->assertStringContainsString(self::WEEK_MONDAY, (string) $log->description);
    }

    public function test_single_lesson_destroy_writes_schedule_single_lesson_registration_cancelled_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id'    => $this->partner->id,
            'name'          => 'Разовое cancel',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents'   => 100000,
            'freeze_enabled'=> 0,
            'freeze_days'   => 0,
            'is_active'     => 1,
        ]);
        $slot = $this->mondaySlot('16:00', '17:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id'               => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date'       => self::WEEK_MONDAY,
            'lesson_package_id'     => $package->id,
            'fee_amount'            => 1000,
        ])->assertOk();

        $bindId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $slot->id)
            ->value('id');

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $bindId,
        ]))->assertOk();

        $log = $this->latestLog(AuditEvent::ScheduleSingleLessonRegistrationCancelled);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Отменена запись разового занятия', (string) $log->description);
        $this->assertStringContainsString(self::WEEK_MONDAY, (string) $log->target_label);
    }

    public function test_occurrence_status_store_writes_schedule_occurrence_status_set_log(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner($this->partner->id);

        $student = $this->studentUser();
        $slot = $this->mondaySlot('17:00', '18:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id'               => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date'       => self::WEEK_MONDAY,
        ])->assertOk();

        $status = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'user_id'                     => $student->id,
            'team_schedule_slot_id'       => $slot->id,
            'occurrence_date'             => self::WEEK_MONDAY,
            'lesson_occurrence_status_id' => $status->id,
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::ScheduleOccurrenceStatusSet);

        $this->assertNotNull($log);
        $this->assertStringContainsString($status->title, (string) $log->description);
        $this->assertStringContainsString(self::WEEK_MONDAY, (string) $log->description);
    }

    public function test_logs_data_returns_written_schedule_event_in_table(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $student->forceFill(['has_used_school_schedule_trial' => false])->save();
        $slot = $this->mondaySlot('18:00', '19:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id'               => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date'       => self::WEEK_MONDAY,
        ])->assertOk();

        $descriptions = collect($this->getJson(route('logs.data.school-schedule', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'Пробное занятие')),
            'Ожидалась запись schedule.trial_registered в logs-data.'
        );
    }
}
