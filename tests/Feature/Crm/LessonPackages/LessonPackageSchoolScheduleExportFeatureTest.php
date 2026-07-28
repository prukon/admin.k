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
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageSchoolScheduleExportTestHelpers;

/**
 * Содержимое выгрузки Excel: листы «Занятия» / «Назначения», изоляция партнёра, UI-маркеры модалки.
 *
 * Доступ / AJAX / non-AJAX — отдельные классы ExportAccess / ExportAjaxContract / ExportNonAjaxSafetyNet.
 */
final class LessonPackageSchoolScheduleExportFeatureTest extends CrmTestCase
{
    use LessonPackageSchoolScheduleExportTestHelpers;

    private const OCCURRENCE_DATE = '2026-07-15';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $this->grantExportAccess($this->user);
        $this->actingAs($this->user);
        $this->requirePhpSpreadsheet();
    }

    public function test_export_xlsx_contains_lessons_and_assignments_sheets(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Иванов',
            'name' => 'Иван',
            'is_enabled' => 1,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа Экспорт',
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий 8',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 8,
            'price_cents' => 8000,
            'freeze_enabled' => false,
            'freeze_days' => 0,
            'is_active' => true,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $team->id,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-09-30',
            'lessons_total' => 8,
            'lessons_remaining' => 7,
            'fee_amount' => 4500.00,
            'is_paid' => true,
            'is_manual_paid' => null,
            'created_by' => $this->user->id,
        ]);

        $unbound = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $team->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => 1000.00,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => (int) CarbonImmutable::parse(self::OCCURRENCE_DATE)->format('N'),
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::OCCURRENCE_DATE,
            'ends_at' => '2026-12-31',
            'is_trial_lesson' => false,
            'created_by' => $this->user->id,
        ]);

        $attended = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::OCCURRENCE_DATE,
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $attended->id,
            'created_by' => $this->user->id,
        ]);

        $trialStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Петров',
            'name' => 'Пётр',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $trialStudent->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::OCCURRENCE_DATE,
            'ends_at' => '2026-12-31',
            'is_trial_lesson' => true,
            'trial_lessons_remaining' => 1,
            'trial_lessons_total' => 1,
            'created_by' => $this->user->id,
        ]);

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Чужой',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);
        $foreignPackage = LessonPackage::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой пакет',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 1000,
            'freeze_enabled' => false,
            'freeze_days' => 0,
            'is_active' => true,
        ]);
        UserLessonPackage::query()->create([
            'user_id' => $foreignStudent->id,
            'lesson_package_id' => $foreignPackage->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount' => 10,
            'created_by' => $this->foreignUser->id,
        ]);

        $response = $this->get($this->exportUrl($this->validExportPeriod()));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $tmp = tempnam(sys_get_temp_dir(), 'ulp_export_');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $response->streamedContent());

        $spreadsheet = IOFactory::load($tmp);
        @unlink($tmp);

        $this->assertSame(['Занятия', 'Назначения'], $spreadsheet->getSheetNames());

        $lessons = $spreadsheet->getSheetByName('Занятия');
        $this->assertNotNull($lessons);
        $this->assertSame('Дата', (string) $lessons->getCell([1, 1])->getValue());
        $this->assertSame(self::OCCURRENCE_DATE, (string) $lessons->getCell([1, 2])->getValue());
        $this->assertSame('16:00', (string) $lessons->getCell([2, 2])->getValue());
        $this->assertSame('17:00', (string) $lessons->getCell([3, 2])->getValue());
        $this->assertSame('Иванов Иван', (string) $lessons->getCell([4, 2])->getValue());
        $this->assertSame('Группа Экспорт', (string) $lessons->getCell([5, 2])->getValue());
        $this->assertSame('Гибкий', (string) $lessons->getCell([6, 2])->getValue());
        $this->assertSame('Гибкий 8', (string) $lessons->getCell([7, 2])->getValue());
        $this->assertSame((string) $ulp->id, (string) $lessons->getCell([8, 2])->getValue());
        $this->assertSame('4500.00', (string) $lessons->getCell([9, 2])->getValue());
        $this->assertSame('Оплачен', (string) $lessons->getCell([10, 2])->getValue());
        $this->assertSame('Посетил', (string) $lessons->getCell([11, 2])->getValue());
        $this->assertSame('да', (string) $lessons->getCell([12, 2])->getValue());
        $this->assertSame('вручную', (string) $lessons->getCell([13, 2])->getValue());
        $this->assertSame('7', (string) $lessons->getCell([14, 2])->getValue());
        $this->assertSame('8', (string) $lessons->getCell([15, 2])->getValue());

        $lessonValues = [];
        for ($r = 2; $r <= 10; $r++) {
            $name = (string) $lessons->getCell([4, $r])->getValue();
            if ($name === '') {
                break;
            }
            $lessonValues[] = $name;
        }
        $this->assertContains('Петров Пётр', $lessonValues);
        $this->assertNotContains('Чужой Ученик', $lessonValues);

        $assignments = $spreadsheet->getSheetByName('Назначения');
        $this->assertNotNull($assignments);
        $this->assertSame('Ученик', (string) $assignments->getCell([1, 1])->getValue());

        $assignmentIds = [];
        $unboundRow = null;
        for ($r = 2; $r <= 20; $r++) {
            $id = (string) $assignments->getCell([5, $r])->getValue();
            if ($id === '') {
                break;
            }
            $assignmentIds[] = (int) $id;
            if ((int) $id === (int) $unbound->id) {
                $unboundRow = $r;
            }
        }
        $this->assertContains((int) $ulp->id, $assignmentIds);
        $this->assertContains((int) $unbound->id, $assignmentIds);
        $this->assertNotNull($unboundRow);
        $this->assertSame('не задан', (string) $assignments->getCell([6, $unboundRow])->getValue());
        $this->assertSame('не задан', (string) $assignments->getCell([7, $unboundRow])->getValue());
        $this->assertNotContains(
            (int) UserLessonPackage::query()->where('lesson_package_id', $foreignPackage->id)->value('id'),
            $assignmentIds
        );
    }

    public function test_export_marks_auto_status_source_and_non_consuming_status(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Сидоров',
            'name' => 'Сидор',
            'is_enabled' => 1,
        ]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа Авто']);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс авто',
            'schedule_type' => 'fixed',
            'duration_days' => 60,
            'lessons_count' => 4,
            'price_cents' => 2000,
            'freeze_enabled' => false,
            'freeze_days' => 0,
            'is_active' => true,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $team->id,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-08-31',
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount' => 2000,
            'is_paid' => false,
            'is_manual_paid' => false,
            'created_by' => $this->user->id,
        ]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => (int) CarbonImmutable::parse(self::OCCURRENCE_DATE)->format('N'),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::OCCURRENCE_DATE,
            'ends_at' => '2026-12-31',
            'is_trial_lesson' => false,
            'created_by' => $this->user->id,
        ]);

        $cancelled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'cancelled')
            ->firstOrFail();

        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::OCCURRENCE_DATE,
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $cancelled->id,
            'created_by' => null,
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'ulp_export_auto_');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $this->get($this->exportUrl($this->validExportPeriod()))->streamedContent());
        $sheet = IOFactory::load($tmp)->getSheetByName('Занятия');
        @unlink($tmp);
        $this->assertNotNull($sheet);

        $found = false;
        for ($r = 2; $r <= 20; $r++) {
            if ((string) $sheet->getCell([4, $r])->getValue() !== 'Сидоров Сидор') {
                continue;
            }
            $found = true;
            $this->assertSame('Фиксированный', (string) $sheet->getCell([6, $r])->getValue());
            $this->assertSame('Не оплачен (ручная отметка)', (string) $sheet->getCell([10, $r])->getValue());
            $this->assertSame('Отмена', (string) $sheet->getCell([11, $r])->getValue());
            $this->assertSame('нет', (string) $sheet->getCell([12, $r])->getValue());
            $this->assertSame('авто', (string) $sheet->getCell([13, $r])->getValue());
            break;
        }
        $this->assertTrue($found, 'Строка занятия Сидорова не найдена в выгрузке');
    }

    public function test_school_schedule_page_export_modal_has_ajax_markers(): void
    {
        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="schoolCalExportModal"', $html);
        $this->assertStringContainsString('id="schoolCalExportSubmit"', $html);
        $this->assertStringContainsString('id="schoolCalExportDateFromErr"', $html);
        $this->assertStringContainsString('id="schoolCalExportDateToErr"', $html);
        $this->assertStringContainsString('data-err="date_from"', $html);
        $this->assertStringContainsString('data-err="date_to"', $html);
        $this->assertStringContainsString('schoolCalExportPresetThisMonth', $html);
        $this->assertStringContainsString('schoolCalExportPresetLastMonth', $html);
        $this->assertStringContainsString('initSchoolCalExport', $html);
        $this->assertStringContainsString("Accept': 'application/json'", $html);
        $this->assertStringContainsString('X-Requested-With', $html);
    }

    public function test_lessons_outside_period_are_excluded(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Внепериод',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий вне',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 2,
            'price_cents' => 100,
            'freeze_enabled' => false,
            'freeze_days' => 0,
            'is_active' => true,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $team->id,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-06-30',
            'lessons_total' => 2,
            'lessons_remaining' => 2,
            'fee_amount' => 100,
            'created_by' => $this->user->id,
        ]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2026-06-15',
            'ends_at' => '2026-12-31',
            'is_trial_lesson' => false,
            'created_by' => $this->user->id,
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'ulp_export_out_');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $this->get($this->exportUrl($this->validExportPeriod()))->streamedContent());
        $sheet = IOFactory::load($tmp)->getSheetByName('Занятия');
        @unlink($tmp);
        $this->assertNotNull($sheet);

        for ($r = 2; $r <= 30; $r++) {
            $name = (string) $sheet->getCell([4, $r])->getValue();
            if ($name === '') {
                break;
            }
            $this->assertNotSame('Внепериод Ученик', $name);
        }
    }
}
