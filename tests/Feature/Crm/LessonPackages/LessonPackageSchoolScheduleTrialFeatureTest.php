<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Пробное занятие в календаре школы: сохранение, eligibility, удаление, ошибки и метаданные в JSON недели.
 */
final class LessonPackageSchoolScheduleTrialFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->assertSame(
            1,
            (int) CarbonImmutable::parse(self::WEEK_MONDAY)->format('N'),
            'Тестовая дата должна быть понедельником (ISO weekday 1).'
        );
    }

    private function grantPermission(string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function studentUser(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'name' => 'Пробный',
            'lastname' => 'Ученик',
            'is_enabled' => 1,
        ]);
    }

    /** Слот на понедельник, активен на якорную неделю тестов. */
    private function mondaySlot(): TeamScheduleSlot
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        return TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
    }

    public function test_trial_eligibility_returns_allowed_true_when_cell_is_free(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('reason', null);
    }

    public function test_trial_eligibility_returns_allowed_false_when_query_incomplete(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => 0,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('allowed', false);
    }

    public function test_trial_store_validation_errors_when_required_fields_missing(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'team_schedule_slot_id', 'occurrence_date']);
    }

    public function test_trial_store_rejects_disabled_student(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 0,
        ]);
        $slot = $this->mondaySlot();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_trial_store_rejects_occurrence_on_wrong_weekday(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => '2026-05-06',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['occurrence_date']);
    }

    public function test_trial_store_rejects_skipped_school_occurrence(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();

        $this->postJson(route('admin.team-schedule-slots.skip-occurrence', $slot), [
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.occurrence_date.0', 'На эту дату занятие исключено из расписания школы.');
    }

    public function test_trial_destroy_returns_404_for_package_registration_row(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий для destroy trial 404',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 5,
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
            'lessons_total' => 5,
            'lessons_remaining' => 2,
            'created_by' => $this->user->id,
        ]);

        $slot = $this->mondaySlot();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $bindingId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $slot->id)
            ->whereDate('starts_at', self::WEEK_MONDAY)
            ->value('id');
        $this->assertGreaterThan(0, $bindingId);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $bindingId,
        ]))->assertStatus(404);
    }

    public function test_trial_destroy_returns_404_for_other_partner_trial_row(): void
    {
        $this->grantPermission('lessonPackages.view');

        $roleUserId = Role::query()->where('name', 'user')->value('id');
        $this->assertNotNull($roleUserId);

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => (int) $roleUserId,
            'is_enabled' => 1,
        ]);

        $team = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $trialRow = UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'user_id' => $foreignStudent->id,
            'user_lesson_package_id' => null,
            'is_trial_lesson' => true,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => '9999-12-31',
            'created_by' => $foreignStudent->id,
        ]);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $trialRow->id,
        ]))->assertStatus(404);

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $trialRow->id]);
    }

    public function test_trial_week_json_contains_registration_kind_trial_and_label(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $week = $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->json();

        $hit = collect($week['occurrences'] ?? [])->first(function (array $o) use ($slot): bool {
            return ($o['date'] ?? '') === self::WEEK_MONDAY
                && (int) ($o['id'] ?? 0) === $slot->id;
        });
        $this->assertNotNull($hit);

        $regs = $hit['registrations'] ?? [];
        $trialReg = collect($regs)->first(fn (array $r): bool => ($r['registration_kind'] ?? '') === 'trial');
        $this->assertNotNull($trialReg);
        $this->assertTrue(! empty($trialReg['is_trial_lesson']));
        $this->assertNotEmpty($trialReg['line'] ?? '');
        $this->assertStringContainsStringIgnoringCase('пробн', (string) ($trialReg['line'] ?? ''));
        $this->assertSame(1, (int) ($trialReg['lessons_remaining'] ?? 0));
        $this->assertSame(1, (int) ($trialReg['lessons_total'] ?? 0));

        $student->refresh();
        $this->assertTrue($student->has_used_school_schedule_trial);
    }

    public function test_trial_eligibility_blocked_when_legacy_calendar_row_without_package_and_not_trial_flag(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'is_trial_lesson' => false,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => '9999-12-31',
            'created_by' => $this->user->id,
        ]);

        $res = $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('allowed', false);

        $reason = (string) $res->json('reason');
        $this->assertStringContainsString('календаре', $reason);
    }

    public function test_trial_destroy_forbidden_without_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $trialId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $slot->id)
            ->where('is_trial_lesson', true)
            ->value('id');
        $this->assertGreaterThan(0, $trialId);

        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $trialId,
        ]))->assertForbidden();

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $trialId]);
    }

    public function test_trial_destroy_cleans_occurrence_status_events_and_writes_my_logs_row(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $student = $this->studentUser();
        $slot = $this->mondaySlot();

        // создаём пробное через штатный endpoint
        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $trialId = (int) UserTeamScheduleSlot::query()
            ->where('partner_id', $this->partner->id)
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $slot->id)
            ->whereDate('starts_at', self::WEEK_MONDAY)
            ->where('is_trial_lesson', true)
            ->value('id');
        $this->assertGreaterThan(0, $trialId);

        $statusAttended = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        // пишем статус по пробному (user_lesson_package_id = null)
        $this->actingAs($this->user);
        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
            'lesson_occurrence_status_id' => $statusAttended->id,
        ])->assertOk();

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_lesson_package_id' => null,
            'lesson_occurrence_status_id' => $statusAttended->id,
        ]);

        // отменяем пробное
        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $trialId,
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Пробное занятие отменено.');

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $trialId]);

        // статусы по этому пробному должны быть полностью вычищены
        $this->assertDatabaseMissing('user_lesson_occurrence_status_events', [
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_lesson_package_id' => null,
        ]);

        // и пишем аудит в my_logs
        $this->assertDatabaseHas('my_logs', [
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'event' => AuditEvent::ScheduleTrialCancelled->value,
            'level' => AuditEvent::ScheduleTrialCancelled->level()->value,
            'target_type' => UserTeamScheduleSlot::class,
            'target_id' => $trialId,
        ]);

        $log = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::ScheduleTrialCancelled->value)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Отменено пробное занятие', (string) $log->description);
        $this->assertStringContainsString(self::WEEK_MONDAY, (string) $log->target_label);

        $student->refresh();
        $this->assertFalse($student->has_used_school_schedule_trial);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $student->refresh();
        $this->assertTrue($student->has_used_school_schedule_trial);
    }

    public function test_trial_store_second_registration_other_slot_same_user_returns_422(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slotMon = $this->mondaySlot();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slotMon->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $teamTue = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slotTue = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamTue->id,
            'location_id' => null,
            'weekday' => 2,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slotTue->id,
            'occurrence_date' => '2026-05-05',
        ])->assertStatus(422);
    }
}
