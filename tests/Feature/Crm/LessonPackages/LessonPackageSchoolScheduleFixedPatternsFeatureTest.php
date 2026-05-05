<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фиксированный абонемент с несколькими слотами шаблона (patterns[]), дедупликация, валидация
 * и проверка «все вхождения дней в периоде» (assertEveryFixedPatternOccurrenceResolvableInPeriod).
 */
final class LessonPackageSchoolScheduleFixedPatternsFeatureTest extends CrmTestCase
{
    private const ANCHOR_MONDAY = '2026-05-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->assertSame(
            1,
            (int) CarbonImmutable::parse(self::ANCHOR_MONDAY)->format('N'),
            'Тестовая дата якоря должна быть понедельником (ISO weekday 1).'
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
            'name' => 'Иван',
            'lastname' => 'Тестов',
            'is_enabled' => 1,
        ]);
    }

    /**
     * @param list<array{weekday: int, time_start: string, time_end: string}> $rows
     *
     * @return array{patterns: list<array{weekday: int, time_start: string, time_end: string}>}
     */
    private function fixedPatternsPayload(array $rows): array
    {
        return ['patterns' => $rows];
    }

    private function createTeamWithMonAndThuSlots(): array
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slotMon = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '15:00',
            'time_end' => '16:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $slotThu = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 4,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        return [$team, $slotMon, $slotThu];
    }

    public function test_assign_fixed_with_two_patterns_schedules_monday_then_thursday(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        [, $slotMon, $slotThu] = $this->createTeamWithMonAndThuSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс два слота',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 2,
            'price_cents' => 8000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 2,
            'lessons_remaining' => 2,
            'fee_amount' => '80.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), array_merge([
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotMon->id,
            'anchor_date' => self::ANCHOR_MONDAY,
        ], $this->fixedPatternsPayload([
            ['weekday' => 1, 'time_start' => '15:00', 'time_end' => '16:00'],
            ['weekday' => 4, 'time_start' => '10:00', 'time_end' => '11:00'],
        ])))->assertOk()
            ->assertJsonPath('message', 'Абонемент назначен, занятия привязаны к расписанию школы.');

        $ulp->refresh();
        $this->assertSame(2, (int) $ulp->lessons_remaining);

        $rows = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->orderBy('starts_at')
            ->get(['team_schedule_slot_id', 'starts_at']);

        $this->assertCount(2, $rows);
        $this->assertSame((int) $slotMon->id, (int) $rows[0]->team_schedule_slot_id);
        $this->assertSame(self::ANCHOR_MONDAY, Carbon::parse((string) $rows[0]->starts_at)->format('Y-m-d'));
        $this->assertSame((int) $slotThu->id, (int) $rows[1]->team_schedule_slot_id);
        $this->assertSame('2026-05-07', Carbon::parse((string) $rows[1]->starts_at)->format('Y-m-d'));
    }

    public function test_assign_fixed_422_when_extra_pattern_weekday_has_no_slot_in_period(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        [, $slotMon] = $this->createTeamWithMonAndThuSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс лишний день',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $payload = $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), array_merge([
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotMon->id,
            'anchor_date' => self::ANCHOR_MONDAY,
        ], $this->fixedPatternsPayload([
            ['weekday' => 1, 'time_start' => '15:00', 'time_end' => '16:00'],
            ['weekday' => 4, 'time_start' => '10:00', 'time_end' => '11:00'],
            ['weekday' => 6, 'time_start' => '12:00', 'time_end' => '13:00'],
        ])))->assertStatus(422)->json();

        $this->assertArrayHasKey('patterns', $payload['errors'] ?? []);
        $this->assertStringContainsString(
            'В периоде абонемента нет занятия группы',
            (string) ($payload['errors']['patterns'][0] ?? '')
        );
    }

    public function test_assign_fixed_422_when_patterns_do_not_include_anchor_slot(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        [, $slotMon] = $this->createTeamWithMonAndThuSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс без якоря в шаблоне',
            'schedule_type' => 'fixed',
            'duration_days' => 14,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), array_merge([
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotMon->id,
            'anchor_date' => self::ANCHOR_MONDAY,
        ], $this->fixedPatternsPayload([
            ['weekday' => 4, 'time_start' => '10:00', 'time_end' => '11:00'],
        ])))->assertStatus(422)
            ->assertJsonValidationErrors(['patterns']);
    }

    public function test_assign_fixed_validation_rejects_second_row_when_time_end_not_after_start(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        [, $slotMon] = $this->createTeamWithMonAndThuSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс валидация времени',
            'schedule_type' => 'fixed',
            'duration_days' => 14,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), array_merge([
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotMon->id,
            'anchor_date' => self::ANCHOR_MONDAY,
        ], $this->fixedPatternsPayload([
            ['weekday' => 1, 'time_start' => '15:00', 'time_end' => '16:00'],
            ['weekday' => 4, 'time_start' => '11:00', 'time_end' => '10:00'],
        ])))->assertStatus(422)
            ->assertJsonValidationErrors(['patterns.1.time_end']);
    }

    public function test_assign_fixed_duplicate_pattern_rows_are_deduped_and_succeed(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        [, $slotMon] = $this->createTeamWithMonAndThuSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс дубликат строк',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), array_merge([
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotMon->id,
            'anchor_date' => self::ANCHOR_MONDAY,
        ], $this->fixedPatternsPayload([
            ['weekday' => 1, 'time_start' => '15:00', 'time_end' => '16:00'],
            ['weekday' => 1, 'time_start' => '15:00', 'time_end' => '16:00'],
        ])))->assertOk();

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_school_schedule_html_includes_fixed_patterns_template_container(): void
    {
        $this->grantPermission('lessonPackages.view');

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="schoolCalFixedPatternsHost"', $html);
        $this->assertStringContainsString('schoolCalFixedAddPattern', $html);
    }
}
