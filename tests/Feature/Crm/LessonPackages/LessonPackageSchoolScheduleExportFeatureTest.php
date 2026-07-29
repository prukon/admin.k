<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\ParentProfile;
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
 * Содержимое выгрузки Excel: листы «Занятия» / «Назначения», контакты ученика/родителя,
 * изоляция партнёра, UI-маркеры модалки, [P2] HTTP-smoke workflow.
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
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванова',
            'firstname' => 'Мария',
            'middlename' => 'Петровна',
            'phone' => '+79001112233',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Иванов',
            'name' => 'Иван',
            'phone' => '+79005556677',
            'parent_id' => $parent->id,
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
            'lessons_remaining' => 3, // текущий счётчик намеренно «бит» — в Excel должен быть исторический 7
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
        $this->assertSame('Телефон ученика', (string) $lessons->getCell([5, 1])->getValue());
        $this->assertSame('ФИО родителя', (string) $lessons->getCell([6, 1])->getValue());
        $this->assertSame('Телефон родителя', (string) $lessons->getCell([7, 1])->getValue());
        $this->assertSame(self::OCCURRENCE_DATE, (string) $lessons->getCell([1, 2])->getValue());
        $this->assertSame('16:00', (string) $lessons->getCell([2, 2])->getValue());
        $this->assertSame('17:00', (string) $lessons->getCell([3, 2])->getValue());
        $this->assertSame('Иванов Иван', (string) $lessons->getCell([4, 2])->getValue());
        $this->assertSame('+79005556677', (string) $lessons->getCell([5, 2])->getValue());
        $this->assertSame('Иванова Мария Петровна', (string) $lessons->getCell([6, 2])->getValue());
        $this->assertSame('+79001112233', (string) $lessons->getCell([7, 2])->getValue());
        $this->assertSame('Группа Экспорт', (string) $lessons->getCell([8, 2])->getValue());
        $this->assertSame('Гибкий', (string) $lessons->getCell([9, 2])->getValue());
        $this->assertSame('Гибкий 8', (string) $lessons->getCell([10, 2])->getValue());
        $this->assertSame((string) $ulp->id, (string) $lessons->getCell([11, 2])->getValue());
        $this->assertSame('4500.00', (string) $lessons->getCell([12, 2])->getValue());
        $this->assertSame('Оплачен', (string) $lessons->getCell([13, 2])->getValue());
        $this->assertSame('Посетил', (string) $lessons->getCell([14, 2])->getValue());
        $this->assertSame('да', (string) $lessons->getCell([15, 2])->getValue());
        $this->assertSame('вручную', (string) $lessons->getCell([16, 2])->getValue());
        $this->assertSame('7', (string) $lessons->getCell([17, 2])->getValue());
        $this->assertSame('8', (string) $lessons->getCell([18, 2])->getValue());

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
        $this->assertSame('Телефон ученика', (string) $assignments->getCell([2, 1])->getValue());
        $this->assertSame('ФИО родителя', (string) $assignments->getCell([3, 1])->getValue());
        $this->assertSame('Телефон родителя', (string) $assignments->getCell([4, 1])->getValue());

        $assignmentIds = [];
        $unboundRow = null;
        $boundRow = null;
        for ($r = 2; $r <= 20; $r++) {
            $id = (string) $assignments->getCell([8, $r])->getValue();
            if ($id === '') {
                break;
            }
            $assignmentIds[] = (int) $id;
            if ((int) $id === (int) $unbound->id) {
                $unboundRow = $r;
            }
            if ((int) $id === (int) $ulp->id) {
                $boundRow = $r;
            }
        }
        $this->assertContains((int) $ulp->id, $assignmentIds);
        $this->assertContains((int) $unbound->id, $assignmentIds);
        $this->assertNotNull($unboundRow);
        $this->assertNotNull($boundRow);
        $this->assertSame('+79005556677', (string) $assignments->getCell([2, $boundRow])->getValue());
        $this->assertSame('Иванова Мария Петровна', (string) $assignments->getCell([3, $boundRow])->getValue());
        $this->assertSame('+79001112233', (string) $assignments->getCell([4, $boundRow])->getValue());
        $this->assertSame('не задан', (string) $assignments->getCell([9, $unboundRow])->getValue());
        $this->assertSame('не задан', (string) $assignments->getCell([10, $unboundRow])->getValue());
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
            $this->assertSame('Фиксированный', (string) $sheet->getCell([9, $r])->getValue());
            $this->assertSame('Не оплачен (ручная отметка)', (string) $sheet->getCell([13, $r])->getValue());
            $this->assertSame('Отмена', (string) $sheet->getCell([14, $r])->getValue());
            $this->assertSame('нет', (string) $sheet->getCell([15, $r])->getValue());
            $this->assertSame('авто', (string) $sheet->getCell([16, $r])->getValue());
            // Статус не списывает: остаток после перехода = lessons_total (4).
            $this->assertSame('4', (string) $sheet->getCell([17, $r])->getValue());
            $this->assertSame('4', (string) $sheet->getCell([18, $r])->getValue());
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

    public function test_lessons_sheet_uses_historical_remaining_not_current_counter(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'История',
            'name' => 'Остатков',
            'is_enabled' => 1,
        ]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа История']);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий история',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 8,
            'price_cents' => 1000,
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
            // После двух списаний реально 6, но в выгрузке считаем по событиям.
            'lessons_remaining' => 1,
            'fee_amount' => 1000,
            'created_by' => $this->user->id,
        ]);

        $slotEarly = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => (int) CarbonImmutable::parse('2026-07-10')->format('N'),
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $slotLate = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => (int) CarbonImmutable::parse('2026-07-20')->format('N'),
            'time_start' => '18:00',
            'time_end' => '19:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $slotNoStatus = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => (int) CarbonImmutable::parse('2026-07-25')->format('N'),
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotEarly->id,
            'starts_at' => '2026-07-10',
            'ends_at' => '2026-12-31',
            'is_trial_lesson' => false,
            'created_by' => $this->user->id,
        ]);
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotLate->id,
            'starts_at' => '2026-07-20',
            'ends_at' => '2026-12-31',
            'is_trial_lesson' => false,
            'created_by' => $this->user->id,
        ]);
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotNoStatus->id,
            'starts_at' => '2026-07-25',
            'ends_at' => '2026-12-31',
            'is_trial_lesson' => false,
            'created_by' => $this->user->id,
        ]);

        // Списание вне периода выгрузки — должно учитываться в реконструкции.
        $slotBeforePeriod = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '08:00',
            'time_end' => '09:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotBeforePeriod->id,
            'starts_at' => '2026-06-15',
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
            'team_schedule_slot_id' => $slotBeforePeriod->id,
            'occurrence_date' => '2026-06-15',
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $attended->id,
            'created_by' => $this->user->id,
        ]);
        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slotEarly->id,
            'occurrence_date' => '2026-07-10',
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $attended->id,
            'created_by' => $this->user->id,
        ]);
        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slotLate->id,
            'occurrence_date' => '2026-07-20',
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $attended->id,
            'created_by' => $this->user->id,
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'ulp_hist_');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $this->get($this->exportUrl($this->validExportPeriod()))->streamedContent());
        $sheet = IOFactory::load($tmp)->getSheetByName('Занятия');
        @unlink($tmp);
        $this->assertNotNull($sheet);

        $byDate = [];
        for ($r = 2; $r <= 20; $r++) {
            $date = (string) $sheet->getCell([1, $r])->getValue();
            $name = (string) $sheet->getCell([4, $r])->getValue();
            if ($date === '' || $name === '') {
                break;
            }
            if ($name !== 'История Остатков') {
                continue;
            }
            $byDate[$date] = [
                'remaining' => (string) $sheet->getCell([17, $r])->getValue(),
                'total' => (string) $sheet->getCell([18, $r])->getValue(),
                'status' => (string) $sheet->getCell([14, $r])->getValue(),
            ];
        }

        // 8 − 1 (июнь, вне периода) − 1 (10.07) = 6 после 10.07
        $this->assertSame('6', $byDate['2026-07-10']['remaining']);
        $this->assertSame('Посетил', $byDate['2026-07-10']['status']);
        // ещё −1 (20.07) = 5
        $this->assertSame('5', $byDate['2026-07-20']['remaining']);
        // без статуса: остаток «до статуса» = после всех более ранних = 5
        $this->assertSame('5', $byDate['2026-07-25']['remaining']);
        $this->assertSame('', $byDate['2026-07-25']['status']);
        $this->assertSame('8', $byDate['2026-07-10']['total']);
        // Не берём текущий счётчик lessons_remaining=1
        $this->assertNotSame('1', $byDate['2026-07-10']['remaining']);
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

    public function test_export_headers_include_student_and_parent_contact_columns_on_both_sheets(): void
    {
        $spreadsheet = $this->loadExportSpreadsheet($this->validExportPeriod());

        $lessons = $spreadsheet->getSheetByName('Занятия');
        $this->assertNotNull($lessons);
        $this->assertSame('Ученик', (string) $lessons->getCell([4, 1])->getValue());
        $this->assertSame('Телефон ученика', (string) $lessons->getCell([5, 1])->getValue());
        $this->assertSame('ФИО родителя', (string) $lessons->getCell([6, 1])->getValue());
        $this->assertSame('Телефон родителя', (string) $lessons->getCell([7, 1])->getValue());
        $this->assertSame('Группа', (string) $lessons->getCell([8, 1])->getValue());

        $assignments = $spreadsheet->getSheetByName('Назначения');
        $this->assertNotNull($assignments);
        $this->assertSame('Ученик', (string) $assignments->getCell([1, 1])->getValue());
        $this->assertSame('Телефон ученика', (string) $assignments->getCell([2, 1])->getValue());
        $this->assertSame('ФИО родителя', (string) $assignments->getCell([3, 1])->getValue());
        $this->assertSame('Телефон родителя', (string) $assignments->getCell([4, 1])->getValue());
        $this->assertSame('Группа', (string) $assignments->getCell([5, 1])->getValue());
    }

    public function test_export_fills_student_and_parent_contacts_on_both_sheets(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Сидорова',
            'firstname' => 'Анна',
            'middlename' => 'Игоревна',
            'phone' => '+79001234567',
        ]);
        $fixture = $this->seedExportStudentWithLessonAndAssignment([
            'lastname' => 'Сидоров',
            'name' => 'Алексей',
            'phone' => '+79007654321',
            'parent_id' => $parent->id,
        ]);

        $spreadsheet = $this->loadExportSpreadsheet($this->validExportPeriod());

        $lessons = $spreadsheet->getSheetByName('Занятия');
        $this->assertNotNull($lessons);
        $lessonRow = $this->findSheetRowByStudentName($lessons, 'Сидоров Алексей', 4);
        $this->assertNotNull($lessonRow);
        $this->assertSame('+79007654321', (string) $lessons->getCell([5, $lessonRow])->getValue());
        $this->assertSame('Сидорова Анна Игоревна', (string) $lessons->getCell([6, $lessonRow])->getValue());
        $this->assertSame('+79001234567', (string) $lessons->getCell([7, $lessonRow])->getValue());

        $assignments = $spreadsheet->getSheetByName('Назначения');
        $this->assertNotNull($assignments);
        $assignmentRow = $this->findSheetRowByAssignmentId($assignments, (int) $fixture['ulp']->id);
        $this->assertNotNull($assignmentRow);
        $this->assertSame('Сидоров Алексей', (string) $assignments->getCell([1, $assignmentRow])->getValue());
        $this->assertSame('+79007654321', (string) $assignments->getCell([2, $assignmentRow])->getValue());
        $this->assertSame('Сидорова Анна Игоревна', (string) $assignments->getCell([3, $assignmentRow])->getValue());
        $this->assertSame('+79001234567', (string) $assignments->getCell([4, $assignmentRow])->getValue());
    }

    public function test_export_without_parent_leaves_parent_fields_blank_but_keeps_student_phone(): void
    {
        $this->seedExportStudentWithLessonAndAssignment([
            'lastname' => 'Безродитель',
            'name' => 'Кирилл',
            'phone' => '+79009998877',
            'parent_id' => null,
        ]);

        $spreadsheet = $this->loadExportSpreadsheet($this->validExportPeriod());

        $lessons = $spreadsheet->getSheetByName('Занятия');
        $this->assertNotNull($lessons);
        $lessonRow = $this->findSheetRowByStudentName($lessons, 'Безродитель Кирилл', 4);
        $this->assertNotNull($lessonRow);
        $this->assertSame('+79009998877', (string) $lessons->getCell([5, $lessonRow])->getValue());
        $this->assertSame('', (string) $lessons->getCell([6, $lessonRow])->getValue());
        $this->assertSame('', (string) $lessons->getCell([7, $lessonRow])->getValue());

        $assignments = $spreadsheet->getSheetByName('Назначения');
        $this->assertNotNull($assignments);
        $assignmentRow = $this->findSheetRowByStudentName($assignments, 'Безродитель Кирилл', 1);
        $this->assertNotNull($assignmentRow);
        $this->assertSame('+79009998877', (string) $assignments->getCell([2, $assignmentRow])->getValue());
        $this->assertSame('', (string) $assignments->getCell([3, $assignmentRow])->getValue());
        $this->assertSame('', (string) $assignments->getCell([4, $assignmentRow])->getValue());
    }

    public function test_export_empty_phones_leave_phone_cells_blank_but_keep_parent_fio(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Пустова',
            'firstname' => 'Елена',
            'middlename' => null,
            'phone' => null,
        ]);
        $this->seedExportStudentWithLessonAndAssignment([
            'lastname' => 'Пустов',
            'name' => 'Олег',
            'phone' => null,
            'parent_id' => $parent->id,
        ]);

        $spreadsheet = $this->loadExportSpreadsheet($this->validExportPeriod());

        $lessons = $spreadsheet->getSheetByName('Занятия');
        $this->assertNotNull($lessons);
        $lessonRow = $this->findSheetRowByStudentName($lessons, 'Пустов Олег', 4);
        $this->assertNotNull($lessonRow);
        $this->assertSame('', (string) $lessons->getCell([5, $lessonRow])->getValue());
        $this->assertSame('Пустова Елена', (string) $lessons->getCell([6, $lessonRow])->getValue());
        $this->assertSame('', (string) $lessons->getCell([7, $lessonRow])->getValue());

        $assignments = $spreadsheet->getSheetByName('Назначения');
        $this->assertNotNull($assignments);
        $assignmentRow = $this->findSheetRowByStudentName($assignments, 'Пустов Олег', 1);
        $this->assertNotNull($assignmentRow);
        $this->assertSame('', (string) $assignments->getCell([2, $assignmentRow])->getValue());
        $this->assertSame('Пустова Елена', (string) $assignments->getCell([3, $assignmentRow])->getValue());
        $this->assertSame('', (string) $assignments->getCell([4, $assignmentRow])->getValue());
    }

    /**
     * [P2] HTTP-smoke: страница → маркеры модалки → AJAX GET export → .xlsx с колонками контактов
     * (без браузера / F5; аналог E2E для download-модалки).
     */
    public function test_export_workflow_page_modal_ajax_download_includes_contact_columns(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Смокова',
            'firstname' => 'Ольга',
            'middlename' => 'Дмитриевна',
            'phone' => '+79003334455',
        ]);
        $this->seedExportStudentWithLessonAndAssignment([
            'lastname' => 'Смоков',
            'name' => 'Игорь',
            'phone' => '+79002223344',
            'parent_id' => $parent->id,
        ]);

        $this->withoutVite();

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('id="schoolCalExportBtn"', false)
            ->assertSee('id="schoolCalExportModal"', false)
            ->assertSee('id="schoolCalExportSubmit"', false)
            ->assertSee('initSchoolCalExport', false)
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('schoolCalExportDateFromErr', $html);
        $this->assertStringContainsString('exportXlsx', $html);

        $response = $this->call(
            'GET',
            $this->exportUrl($this->validExportPeriod()),
            [],
            [],
            [],
            $this->exportAjaxHeaders(),
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $body = (string) $response->streamedContent();
        $this->assertNotSame('', trim($body));
        $this->assertStringStartsWith('PK', $body);
        $this->assertNotSame(500, $response->getStatusCode());

        $tmp = tempnam(sys_get_temp_dir(), 'ulp_export_p2_');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $body);
        $spreadsheet = IOFactory::load($tmp);
        @unlink($tmp);

        $lessons = $spreadsheet->getSheetByName('Занятия');
        $this->assertNotNull($lessons);
        $this->assertSame('Телефон ученика', (string) $lessons->getCell([5, 1])->getValue());
        $lessonRow = $this->findSheetRowByStudentName($lessons, 'Смоков Игорь', 4);
        $this->assertNotNull($lessonRow, 'Строка занятия должна быть в .xlsx сразу после AJAX-download');
        $this->assertSame('+79002223344', (string) $lessons->getCell([5, $lessonRow])->getValue());
        $this->assertSame('Смокова Ольга Дмитриевна', (string) $lessons->getCell([6, $lessonRow])->getValue());
        $this->assertSame('+79003334455', (string) $lessons->getCell([7, $lessonRow])->getValue());

        $assignments = $spreadsheet->getSheetByName('Назначения');
        $this->assertNotNull($assignments);
        $assignmentRow = $this->findSheetRowByStudentName($assignments, 'Смоков Игорь', 1);
        $this->assertNotNull($assignmentRow);
        $this->assertSame('+79002223344', (string) $assignments->getCell([2, $assignmentRow])->getValue());
        $this->assertSame('Смокова Ольга Дмитриевна', (string) $assignments->getCell([3, $assignmentRow])->getValue());
        $this->assertSame('+79003334455', (string) $assignments->getCell([4, $assignmentRow])->getValue());
    }

    /**
     * @param  array{lastname?: string, name?: string, phone?: ?string, parent_id?: ?int}  $studentAttrs
     * @return array{student: User, ulp: UserLessonPackage}
     */
    private function seedExportStudentWithLessonAndAssignment(array $studentAttrs): array
    {
        $student = User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ], $studentAttrs));

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа контакты',
        ]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет контакты',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 1000,
            'freeze_enabled' => false,
            'freeze_days' => 0,
            'is_active' => true,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $team->id,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount' => 1000,
            'created_by' => $this->user->id,
        ]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => (int) CarbonImmutable::parse(self::OCCURRENCE_DATE)->format('N'),
            'time_start' => '14:00',
            'time_end' => '15:00',
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

        return ['student' => $student, 'ulp' => $ulp];
    }

    /**
     * @param  array{date_from: string, date_to: string}  $period
     */
    private function loadExportSpreadsheet(array $period): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ulp_export_');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $this->get($this->exportUrl($period))->assertOk()->streamedContent());
        $spreadsheet = IOFactory::load($tmp);
        @unlink($tmp);

        return $spreadsheet;
    }

    private function findSheetRowByStudentName(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $studentName,
        int $nameColumn,
    ): ?int {
        for ($r = 2; $r <= 50; $r++) {
            $name = (string) $sheet->getCell([$nameColumn, $r])->getValue();
            if ($name === '') {
                break;
            }
            if ($name === $studentName) {
                return $r;
            }
        }

        return null;
    }

    private function findSheetRowByAssignmentId(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $assignmentId,
    ): ?int {
        for ($r = 2; $r <= 50; $r++) {
            $id = (string) $sheet->getCell([8, $r])->getValue();
            if ($id === '') {
                break;
            }
            if ((int) $id === $assignmentId) {
                return $r;
            }
        }

        return null;
    }
}
