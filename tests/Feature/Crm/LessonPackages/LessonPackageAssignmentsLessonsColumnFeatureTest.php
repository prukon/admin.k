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

final class LessonPackageAssignmentsLessonsColumnFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('setPrices.packageAssignments.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user);
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
    }

    public function test_assignments_page_shows_lessons_column_and_hides_team_column(): void
    {
        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('>Занятия<', false)
            ->assertSee('data-column-key="lessons"', false)
            ->assertSee('ulpAssignmentLessonsModal', false)
            ->assertSee('schedule-modal-content cell-edit-modal', false)
            ->assertSee('cell-edit-context', false)
            ->assertSee('Посл.:', false)
            ->assertSee('js-ulp-assignment-lessons', false)
            ->assertDontSee('data-column-key="team_label"', false)
            ->assertDontSee('<th>Группа</th>', false);
    }

    public function test_assignments_data_includes_last_lesson_fields(): void
    {
        $ctx = $this->seedFlexibleWithLessons();

        $response = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $ctx['assignment']->id);
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('team_label', $row);
        $this->assertTrue((bool) $row['has_lessons']);
        $this->assertSame('2026-08-14', $row['last_lesson_date']);
        $this->assertSame('14 августа 2026', $row['last_lesson_date_label']);
        $this->assertSame('Посетил', $row['last_lesson_status_title']);
        // «Сегодня» в CrmTestCase — 2026-08-10; 14.08 ещё впереди.
        $this->assertFalse((bool) $row['last_lesson_is_past']);
    }

    public function test_assignments_data_marks_past_last_lesson(): void
    {
        $ctx = $this->seedFlexibleWithLessons();
        // Последнее занятие в фикстуре — 14.08; сдвигаем на прошедшую дату.
        UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ctx['assignment']->id)
            ->whereDate('starts_at', '2026-08-14')
            ->update(['starts_at' => '2026-08-09']);
        UserLessonOccurrenceStatusEvent::query()
            ->where('user_lesson_package_id', $ctx['assignment']->id)
            ->whereDate('occurrence_date', '2026-08-14')
            ->update(['occurrence_date' => '2026-08-09']);

        // По умолчанию прошедшие скрыты — включаем чекбокс «Прошедшие».
        $row = collect($this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'filter_past_lessons' => '1',
        ]))->json('data'))->firstWhere('id', $ctx['assignment']->id);

        $this->assertNotNull($row);
        $this->assertSame('2026-08-09', $row['last_lesson_date']);
        $this->assertTrue((bool) $row['last_lesson_is_past']);
    }

    public function test_assignments_page_shows_past_lessons_checkbox(): void
    {
        $defaultHtml = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('id="ulp-filter-past-lessons"', false)
            ->assertSee('>Прошедшие<', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/id="ulp-filter-past-lessons"[^>]*\bchecked\b/',
            $defaultHtml
        );

        $withPastHtml = $this->get(route('admin.lesson-packages.assignments', [
            'filter_past_lessons' => '1',
        ]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/class="collapse\s+show[^"]*"\s+id="ulpAssignmentsFiltersCollapse"/',
            $withPastHtml
        );
        $this->assertMatchesRegularExpression(
            '/id="ulp-filter-past-lessons"[^>]*\bchecked\b/',
            $withPastHtml
        );
    }

    public function test_assignments_data_hides_past_last_lesson_by_default(): void
    {
        $past = $this->seedFlexibleWithLessons('past');
        UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $past['assignment']->id)
            ->update(['starts_at' => '2026-08-01']);

        $future = $this->seedFlexibleWithLessons('future');
        $noSlotsPackage = LessonPackage::factory()->create([
            'partner_id' => $this->partner->id,
            'schedule_type' => 'no_schedule',
        ]);
        $noSlotsStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $noSlots = UserLessonPackage::query()->create([
            'user_id' => $noSlotsStudent->id,
            'lesson_package_id' => $noSlotsPackage->id,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount_cents' => 100000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $base = ['draw' => 1, 'start' => 0, 'length' => 50];
        $defaultIds = collect($this->getJson(route('admin.lesson-packages.assignments.data', $base))
            ->json('data'))->pluck('id')->map(static fn ($id) => (int) $id)->all();

        $this->assertNotContains((int) $past['assignment']->id, $defaultIds);
        $this->assertContains((int) $future['assignment']->id, $defaultIds);
        $this->assertContains((int) $noSlots->id, $defaultIds);

        $withPastIds = collect($this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_past_lessons' => '1',
        ]))->json('data'))->pluck('id')->map(static fn ($id) => (int) $id)->all();

        $this->assertContains((int) $past['assignment']->id, $withPastIds);
        $this->assertContains((int) $future['assignment']->id, $withPastIds);
        $this->assertContains((int) $noSlots->id, $withPastIds);
    }

    public function test_assignments_data_without_lessons_returns_empty_last_lesson(): void
    {
        $package = LessonPackage::factory()->create([
            'partner_id' => $this->partner->id,
            'schedule_type' => 'no_schedule',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount_cents' => 100000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $row = collect($this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->json('data'))->firstWhere('id', $assignment->id);

        $this->assertNotNull($row);
        $this->assertFalse((bool) $row['has_lessons']);
        $this->assertNull($row['last_lesson_date']);
        $this->assertNull($row['last_lesson_status_title']);
        $this->assertFalse((bool) $row['last_lesson_is_past']);
    }

    public function test_assignment_lessons_endpoint_fixed_shows_team_in_header(): void
    {
        $ctx = $this->seedFixedWithLessons();

        $this->getJson(route('admin.lesson-packages.assignments.lessons', [
            'assignment' => $ctx['assignment']->id,
        ]))
            ->assertOk()
            ->assertJsonPath('fee', '6 000 руб.')
            ->assertJsonPath('balance', '0/4')
            ->assertJsonPath('schedule_type', 'fixed')
            ->assertJsonPath('team_label', 'Локомотив')
            ->assertJsonPath('show_team_per_lesson', false)
            ->assertJsonPath('lessons.0.date', '2026-08-14')
            ->assertJsonPath('lessons.0.status_title', 'Посетил')
            ->assertJsonPath('lessons.1.date', '2026-08-05')
            ->assertJsonPath('lessons.1.status_title', null)
            ->assertJsonCount(2, 'lessons');
    }

    public function test_assignment_lessons_endpoint_flexible_shows_team_per_row(): void
    {
        $ctx = $this->seedFlexibleWithLessons();

        $json = $this->getJson(route('admin.lesson-packages.assignments.lessons', [
            'assignment' => $ctx['assignment']->id,
        ]))
            ->assertOk()
            ->assertJsonPath('schedule_type', 'flexible')
            ->assertJsonPath('team_label', null)
            ->assertJsonPath('show_team_per_lesson', true)
            ->json();

        $this->assertSame('Локомотив', $json['lessons'][0]['team_label']);
        $this->assertSame('Резерв', $json['lessons'][1]['team_label']);
    }

    public function test_assignment_lessons_forbidden_without_permission(): void
    {
        $ctx = $this->seedFlexibleWithLessons();

        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('setPrices.packageAssignments.view'))
            ->delete();

        $this->getJson(route('admin.lesson-packages.assignments.lessons', [
            'assignment' => $ctx['assignment']->id,
        ]))->assertForbidden();
    }

    /**
     * @return array{assignment: UserLessonPackage, student: User}
     */
    private function seedFlexibleWithLessons(string $suffix = ''): array
    {
        $tag = $suffix !== '' ? ' '.$suffix : '';
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий тест'.$tag,
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 600000,
            'is_active' => 1,
        ]);
        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-08-31',
            'lessons_total' => 4,
            'lessons_remaining' => 0,
            'fee_amount_cents' => 600000,
            'is_paid' => true,
            'created_by' => $this->user->id,
        ]);

        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Локомотив'.$tag,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Резерв'.$tag,
        ]);
        $slotA = $this->makeSlot($teamA->id);
        $slotB = $this->makeSlot($teamB->id);

        $this->makeUtss($assignment, $student, $slotA, '2026-08-14');
        $this->makeUtss($assignment, $student, $slotB, '2026-08-05');
        $this->markAttended($student, $slotA, $assignment, '2026-08-14');

        return ['assignment' => $assignment, 'student' => $student];
    }

    /**
     * @return array{assignment: UserLessonPackage, student: User}
     */
    private function seedFixedWithLessons(): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс тест',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 600000,
            'is_active' => 1,
        ]);
        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-08-31',
            'lessons_total' => 4,
            'lessons_remaining' => 0,
            'fee_amount_cents' => 600000,
            'is_paid' => true,
            'created_by' => $this->user->id,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Локомотив',
        ]);
        $slot = $this->makeSlot($team->id);
        $this->makeUtss($assignment, $student, $slot, '2026-08-14');
        $this->makeUtss($assignment, $student, $slot, '2026-08-05');
        $this->markAttended($student, $slot, $assignment, '2026-08-14');

        return ['assignment' => $assignment, 'student' => $student];
    }

    private function makeSlot(int $teamId): TeamScheduleSlot
    {
        return TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamId,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
    }

    private function makeUtss(
        UserLessonPackage $assignment,
        User $student,
        TeamScheduleSlot $slot,
        string $date,
    ): UserTeamScheduleSlot {
        return UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $assignment->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => $date,
            'ends_at' => '2026-08-31',
            'created_by' => $this->user->id,
        ]);
    }

    private function markAttended(
        User $student,
        TeamScheduleSlot $slot,
        UserLessonPackage $assignment,
        string $date,
    ): void {
        $attended = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => $date,
            'user_lesson_package_id' => $assignment->id,
            'lesson_occurrence_status_id' => $attended->id,
            'created_by' => $this->user->id,
        ]);
    }
}
