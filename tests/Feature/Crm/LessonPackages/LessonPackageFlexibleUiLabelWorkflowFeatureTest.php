<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Tests\Feature\Crm\Schedule\ScheduleJournalTestCase;

/**
 * [P2] UX: справочник и журнал показывают «Предоплата» как тип, не переименовывая название шаблона.
 *
 * @see LessonPackageFlexibleUiLabelAjaxContractFeatureTest
 */
final class LessonPackageFlexibleUiLabelWorkflowFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /**
     * [P2] Справочник → AJAX create flexible с названием «Гибкий …» → строка без F5:
     * название прежнее, колонка типа — «Предоплата».
     */
    public function test_directories_ajax_create_flexible_shows_prepay_type_without_renaming_package(): void
    {
        $this->grantLessonPackagesView();
        $this->grantLessonPackageTypePermissions($this->user, ['flexible']);
        $this->withoutVite();

        $unique = 'Гибкий 8 занятий-'.uniqid('', true);

        $page = $this->get(route('admin.directories.lesson-packages.index'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('lessonPackageCreateModal', false)
            ->assertSee('lesson-packages-table', false)
            ->assertSee('reloadPackagesTable', false)
            ->assertSee('value="flexible">Предоплата</option>', false)
            ->assertDontSee('value="flexible">Гибкий</option>', false);

        $this->postJson(
            route('admin.lesson-packages.store'),
            [
                'name' => $unique,
                'schedule_type' => 'flexible',
                'duration_days' => 30,
                'lessons_count' => 8,
                'price' => '1500.00',
                'freeze_enabled' => 0,
                'auto_attendance_enabled' => 0,
            ],
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', $unique)
            ->firstOrFail();
        $this->assertSame('flexible', (string) $package->schedule_type);

        $afterCreate = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'name' => $unique,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $row = collect($afterCreate['data'] ?? [])->firstWhere('id', $package->id);
        $this->assertIsArray($row, 'Строка должна быть в DataTables без перезагрузки страницы.');
        $this->assertSame($unique, $row['name']);
        $this->assertSame('flexible', $row['schedule_type']);
        $this->assertSame('Предоплата', $row['schedule_type_label']);
        $this->assertNotSame('Гибкий', $row['schedule_type_label']);
    }

    public function test_directories_ajax_create_fixed_does_not_get_prepay_label(): void
    {
        $this->grantLessonPackagesView();
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);
        $this->withoutVite();

        $unique = 'WF-fixed-'.uniqid('', true);

        $this->get(route('admin.directories.lesson-packages.index'))->assertOk();

        $this->postJson(
            route('admin.lesson-packages.store'),
            [
                'name' => $unique,
                'schedule_type' => 'fixed',
                'duration_days' => 30,
                'lessons_count' => 8,
                'price' => '1500.00',
                'freeze_enabled' => 0,
                'auto_attendance_enabled' => 0,
            ],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', $unique)
            ->firstOrFail();

        $afterCreate = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'name' => $unique,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $row = collect($afterCreate['data'] ?? [])->firstWhere('id', $package->id);
        $this->assertIsArray($row);
        $this->assertSame('fixed', $row['schedule_type']);
        $this->assertSame('Фиксированный', $row['schedule_type_label']);
        $this->assertNotSame('Предоплата', $row['schedule_type_label']);
    }

    /**
     * [P2] Журнал: колонка абонемента «N/M + Предоплата», даже если название шаблона содержит «Гибкий».
     */
    public function test_journal_column_shows_prepay_hint_when_package_name_still_says_flexible(): void
    {
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
        $this->grantLessonPackagesView();

        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 4);
        $ulp->lessonPackage?->update(['name' => 'Гибкий 8 занятий']);
        $packageName = (string) $ulp->fresh()->load('lessonPackage')->lessonPackage?->name;
        $this->assertSame('Гибкий 8 занятий', $packageName);

        $page = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '09',
            'team' => $team->id,
        ]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $this->assertNotSame(500, $page->getStatusCode());
        $page->assertSee(">4/4\nПредоплата<", false)
            ->assertDontSee(">4/4\nГибкий<", false)
            ->assertSee($packageName, false)
            ->assertSee('Абонемент предоплаты: поставить занятие', false)
            ->assertDontSee('Гибкий абонемент: поставить занятие', false);
    }
}
