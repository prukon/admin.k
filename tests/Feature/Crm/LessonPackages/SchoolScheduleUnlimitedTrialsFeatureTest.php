<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Несколько пробных в календаре школы: кнопка другого слота остаётся доступной,
 * занятая ячейка — нет, счётчик уменьшается только при отмене строки.
 */
final class SchoolScheduleUnlimitedTrialsFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

    private const WEEK_TUESDAY = '2026-05-05';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_add_trial_button_stays_available_on_another_slot_after_the_first_trial(): void
    {
        $this->grantPermission('lessonPackages.view');
        $student = $this->studentUser();
        $monday = $this->slot(1, '14:00', '15:00');
        $tuesday = $this->slot(2, '10:00', '11:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $monday->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk()
            ->assertJsonPath('message', 'Пробное занятие добавлено в расписание.');

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $tuesday->id,
            'occurrence_date' => self::WEEK_TUESDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('trial.allowed', true)
            ->assertJsonPath('trial.reason', null);

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $tuesday->id,
            'occurrence_date' => self::WEEK_TUESDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('reason', null);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $tuesday->id,
            'occurrence_date' => self::WEEK_TUESDAY,
        ])->assertOk();

        $student->refresh();
        $this->assertSame(2, (int) $student->school_schedule_trial_lessons_count);

        $week = $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))->assertOk()->json();

        $trialRows = collect($week['occurrences'] ?? [])
            ->flatMap(fn (array $occurrence): array => $occurrence['registrations'] ?? [])
            ->filter(fn (array $row): bool => ($row['registration_kind'] ?? '') === 'trial'
                && (int) ($row['user_id'] ?? 0) === (int) $student->id);

        $this->assertCount(2, $trialRows);
    }

    public function test_add_trial_button_stays_blocked_on_the_occupied_cell(): void
    {
        $this->grantPermission('lessonPackages.view');
        $student = $this->studentUser();
        $monday = $this->slot(1, '16:00', '17:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $monday->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $reason = 'Пробная запись на это занятие уже добавлена.';

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $monday->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('trial.allowed', false)
            ->assertJsonPath('trial.reason', $reason);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $monday->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', $reason)
            ->assertJsonPath('errors.user_id.0', $reason);

        $this->assertSame(1, (int) $student->fresh()->school_schedule_trial_lessons_count);
    }

    public function test_cancelling_one_trial_keeps_the_other_and_lowers_the_count(): void
    {
        $this->grantPermission('lessonPackages.view');
        $student = $this->studentUser();
        $monday = $this->slot(1, '11:00', '12:00');
        $tuesday = $this->slot(2, '11:00', '12:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $monday->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();
        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $tuesday->id,
            'occurrence_date' => self::WEEK_TUESDAY,
        ])->assertOk();

        $mondayTrialId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $monday->id)
            ->where('is_trial_lesson', true)
            ->value('id');
        $this->assertGreaterThan(0, $mondayTrialId);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $mondayTrialId,
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Пробное занятие отменено.');

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $mondayTrialId]);
        $this->assertSame(1, (int) $student->fresh()->school_schedule_trial_lessons_count);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $monday->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))->assertOk()->assertJsonPath('trial.allowed', true);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $tuesday->id,
            'occurrence_date' => self::WEEK_TUESDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('trial.allowed', false)
            ->assertJsonPath('trial.reason', 'Пробная запись на это занятие уже добавлена.');
    }

    public function test_visited_trial_does_not_lower_the_count_and_another_trial_is_allowed(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $student = $this->studentUser();
        $monday = $this->slot(1, '09:00', '10:00');
        $tuesday = $this->slot(2, '09:00', '10:00');
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $monday->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => $monday->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
            'lesson_occurrence_status_id' => $attendedId,
        ])->assertOk();

        $student->refresh();
        $this->assertSame(1, (int) $student->school_schedule_trial_lessons_count);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $tuesday->id,
            'occurrence_date' => self::WEEK_TUESDAY,
        ])->assertOk();

        $this->assertSame(2, (int) $student->fresh()->school_schedule_trial_lessons_count);
    }

    public function test_plain_post_of_another_trial_saves_json_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');
        $student = $this->studentUser();
        $monday = $this->slot(1, '18:00', '19:00');
        $tuesday = $this->slot(2, '18:00', '19:00');

        $this->post(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'team_schedule_slot_id' => $monday->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk()
            ->assertJsonPath('message', 'Пробное занятие добавлено в расписание.');

        $second = $this->post(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'team_schedule_slot_id' => $tuesday->id,
            'occurrence_date' => self::WEEK_TUESDAY,
        ]);

        $second->assertOk();
        $this->assertNotSame('', trim((string) $second->getContent()));
        $second->assertJsonPath('message', 'Пробное занятие добавлено в расписание.');
        $this->assertSame(2, (int) $student->fresh()->school_schedule_trial_lessons_count);
    }

    public function test_missing_fields_return_422_under_each_field(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [])
            ->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['user_id', 'team_schedule_slot_id', 'occurrence_date'],
            ]);
    }

    public function test_bind_actions_without_student_keep_trial_disabled(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => 0,
            'team_schedule_slot_id' => 0,
            'occurrence_date' => '',
        ]))
            ->assertOk()
            ->assertJsonPath('trial.allowed', false)
            ->assertJsonPath('trial.reason', 'Укажите ученика, слот и дату.');
    }

    public function test_school_schedule_page_renders_trial_button_disabled_until_student_is_chosen(): void
    {
        $this->grantPermission('lessonPackages.view');

        $page = $this->get(route('admin.lesson-packages.school-schedule'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('id="schoolCalOpenTrial" disabled', false);
        $page->assertSee('Добавить пробное занятие', false);
        $page->assertSee('id="schoolCalSlotTrialErr"', false);
        $page->assertSee('id="schoolCalSlotBindButtonsWrap"', false);
    }

    public function test_guest_and_user_without_permission_cannot_add_another_trial(): void
    {
        $this->grantPermission('lessonPackages.view');
        $student = $this->studentUser();
        $monday = $this->slot(1, '08:00', '09:00');
        $tuesday = $this->slot(2, '08:00', '09:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $monday->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ])->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $tuesday->id,
            'occurrence_date' => self::WEEK_TUESDAY,
        ])->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $tuesday->id,
            'occurrence_date' => self::WEEK_TUESDAY,
        ]))->assertForbidden();

        Auth::logout();
        $this->get(route('admin.lesson-packages.school-schedule'))->assertRedirect();
        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $tuesday->id,
            'occurrence_date' => self::WEEK_TUESDAY,
        ])->assertUnauthorized();

        $this->assertSame(1, (int) $student->fresh()->school_schedule_trial_lessons_count);
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

    private function studentUser(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'name' => 'Пробный',
            'lastname' => 'Много',
            'is_enabled' => 1,
        ]);
    }

    private function slot(int $weekday, string $timeStart, string $timeEnd): TeamScheduleSlot
    {
        $this->assertSame($weekday, (int) CarbonImmutable::parse(
            $weekday === 1 ? self::WEEK_MONDAY : self::WEEK_TUESDAY
        )->format('N'));

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        return TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => $weekday,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
    }
}
