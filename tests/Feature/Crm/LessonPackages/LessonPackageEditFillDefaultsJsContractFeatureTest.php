<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Реальный UX-баг: после fill из show JS не должен затирать 12 занятий / 120 дней дефолтами 8/30.
 * Падает на коде ДО фикса (unconditional else → 8/30), проходит после applyEditScheduleTypeUi(fromTypeChange).
 *
 * @see LessonPackageEditFillDefaultsMarkupFeatureTest
 * @see docs/documentation/lesson-packages.html
 */
final class LessonPackageEditFillDefaultsJsContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantLessonPackageTypePermissions();
        $this->grantPermission('scheduleSlots.view');
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

    /**
     * @return list<string>
     */
    private function packagesPageUrls(): array
    {
        return [
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ];
    }

    public function test_opening_edit_for_twelve_lesson_prepaid_does_not_replace_values_with_eight_and_thirty(): void
    {
        $fn = null;
        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            $extracted = $this->extractApplyEditScheduleTypeUi($html);
            if ($fn === null) {
                $fn = $extracted;
            } else {
                $this->assertSame(
                    $fn,
                    $extracted,
                    'Дубль страницы справочников должен отдавать ту же applyEditScheduleTypeUi, иначе регрессия в одном из путей.'
                );
            }
        }

        $this->assertIsString($fn);
        $this->assertStringContainsString('else if (fromTypeChange)', $fn);
        $this->assertStringContainsString('canSnapshotPrevious', $fn);

        $payload = $this->runApplyEditHarness($fn);
        $this->assertSame('12', (string) $payload['open12']['lessons'], 'Открытие «Изменить» затёрло 12 занятий дефолтом 8.');
        $this->assertSame('120', (string) $payload['open12']['duration'], 'Открытие «Изменить» затёрло срок 120 дефолтом 30.');
        $this->assertFalse((bool) $payload['open12']['durationReadonly']);
        $this->assertFalse((bool) $payload['open12']['lessonsReadonly']);
    }

    public function test_opening_edit_for_four_lesson_fixed_keeps_server_values_not_create_defaults(): void
    {
        $fn = $this->extractApplyEditScheduleTypeUiFromDirectoriesPage();
        $payload = $this->runApplyEditHarness($fn);

        $this->assertSame('4', (string) $payload['open4']['lessons']);
        $this->assertSame('45', (string) $payload['open4']['duration']);
    }

    public function test_opening_edit_for_eight_lesson_package_does_not_force_another_default(): void
    {
        $fn = $this->extractApplyEditScheduleTypeUiFromDirectoriesPage();
        $payload = $this->runApplyEditHarness($fn);

        $this->assertSame('8', (string) $payload['open8']['lessons']);
        $this->assertSame('30', (string) $payload['open8']['duration']);
    }

    public function test_switching_type_from_one_time_without_snapshot_applies_eight_and_thirty(): void
    {
        $fn = $this->extractApplyEditScheduleTypeUiFromDirectoriesPage();
        $payload = $this->runApplyEditHarness($fn);

        $this->assertSame('1', (string) $payload['openNoSchedule']['lessons']);
        $this->assertSame('1', (string) $payload['openNoSchedule']['duration']);
        $this->assertSame('8', (string) $payload['noScheduleToFixed']['lessons']);
        $this->assertSame('30', (string) $payload['noScheduleToFixed']['duration']);
    }

    public function test_switching_type_from_postpay_without_snapshot_applies_eight_and_thirty(): void
    {
        $fn = $this->extractApplyEditScheduleTypeUiFromDirectoriesPage();
        $payload = $this->runApplyEditHarness($fn);

        $this->assertSame('1', (string) $payload['openPostpay']['lessons']);
        $this->assertSame('31', (string) $payload['openPostpay']['duration']);
        $this->assertSame('8', (string) $payload['postpayToFixed']['lessons']);
        $this->assertSame('30', (string) $payload['postpayToFixed']['duration']);
    }

    public function test_switching_prepaid_to_one_time_and_back_restores_twelve_not_eight(): void
    {
        $fn = $this->extractApplyEditScheduleTypeUiFromDirectoriesPage();
        $payload = $this->runApplyEditHarness($fn);

        $this->assertSame('1', (string) $payload['twelveToNoSchedule']['lessons']);
        $this->assertSame('1', (string) $payload['twelveToNoSchedule']['duration']);
        $this->assertSame('12', (string) $payload['twelveRoundTrip']['lessons']);
        $this->assertSame('120', (string) $payload['twelveRoundTrip']['duration']);
    }

    public function test_create_modal_reset_still_starts_from_eight_lessons_not_previous_edit(): void
    {
        foreach ($this->packagesPageUrls() as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString("createLessons.value = '8'", $html);
            $this->assertStringContainsString("createDuration.value = '30'", $html);
            $this->assertStringContainsString('createFormEl.reset()', $html);
            $this->assertStringContainsString("createScheduleType.value = 'fixed'", $html);
        }
    }

    private function extractApplyEditScheduleTypeUiFromDirectoriesPage(): string
    {
        $html = (string) $this->get(route('admin.directories.lesson-packages.index'))
            ->assertOk()
            ->getContent();

        return $this->extractApplyEditScheduleTypeUi($html);
    }

    private function extractApplyEditScheduleTypeUi(string $source): string
    {
        $needle = 'function applyEditScheduleTypeUi(fromTypeChange)';
        $start = strpos($source, $needle);
        $this->assertNotFalse($start, 'На странице нет applyEditScheduleTypeUi(fromTypeChange).');

        $brace = strpos($source, '{', $start);
        $this->assertNotFalse($brace);

        $depth = 0;
        $len = strlen($source);
        for ($i = $brace; $i < $len; $i++) {
            $ch = $source[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start + 1);
                }
            }
        }

        $this->fail('Не удалось вырезать тело applyEditScheduleTypeUi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function runApplyEditHarness(string $functionSource): array
    {
        $harness = <<<'JS'
function el(value) {
  return {
    value: value === undefined || value === null ? '' : String(value),
    readOnly: false,
    checked: false,
    style: { display: '' },
  };
}

let editScheduleType = el('fixed');
let editDuration = el('');
let editLessons = el('');
let editDurationWrap = { style: { display: '' } };
let editLessonsWrap = { style: { display: '' } };
let editPriceLabel = { textContent: '' };
let editFreezeSection = { style: { display: '' } };
let editAutoAttendanceSection = { style: { display: '' } };
let editFreezeEnabled = { checked: false };
let editAutoAttendanceEnabled = { checked: false };
let editSnapshotBeforeSingle = null;
function editToggleFreezeDays() {}

function resetDom() {
  editScheduleType = el('fixed');
  editDuration = el('');
  editLessons = el('');
  editDurationWrap = { style: { display: '' } };
  editLessonsWrap = { style: { display: '' } };
  editPriceLabel = { textContent: '' };
  editFreezeSection = { style: { display: '' } };
  editAutoAttendanceSection = { style: { display: '' } };
  editFreezeEnabled = { checked: false };
  editAutoAttendanceEnabled = { checked: false };
  editSnapshotBeforeSingle = null;
}

function fillFromShow(lp) {
  const scheduleType = lp.schedule_type || 'fixed';
  editScheduleType.value = scheduleType;
  editDuration.value = lp.duration_days || 30;
  editLessons.value = lp.lessons_count || 8;
  editSnapshotBeforeSingle = null;
  applyEditScheduleTypeUi();
}

function values() {
  return {
    type: editScheduleType.value,
    duration: String(editDuration.value),
    lessons: String(editLessons.value),
    durationReadonly: !!editDuration.readOnly,
    lessonsReadonly: !!editLessons.readOnly,
  };
}

JS;

        $scenarios = <<<'JS'

const results = {};

resetDom();
fillFromShow({ schedule_type: 'flexible', duration_days: 120, lessons_count: 12 });
results.open12 = values();

resetDom();
fillFromShow({ schedule_type: 'fixed', duration_days: 45, lessons_count: 4 });
results.open4 = values();

resetDom();
fillFromShow({ schedule_type: 'flexible', duration_days: 30, lessons_count: 8 });
results.open8 = values();

resetDom();
fillFromShow({ schedule_type: 'no_schedule', duration_days: 1, lessons_count: 1 });
results.openNoSchedule = values();
editScheduleType.value = 'fixed';
applyEditScheduleTypeUi(true);
results.noScheduleToFixed = values();

resetDom();
fillFromShow({ schedule_type: 'postpay', duration_days: 31, lessons_count: 1 });
results.openPostpay = values();
editScheduleType.value = 'fixed';
applyEditScheduleTypeUi(true);
results.postpayToFixed = values();

resetDom();
fillFromShow({ schedule_type: 'flexible', duration_days: 120, lessons_count: 12 });
editScheduleType.value = 'no_schedule';
applyEditScheduleTypeUi(true);
results.twelveToNoSchedule = values();
editScheduleType.value = 'flexible';
applyEditScheduleTypeUi(true);
results.twelveRoundTrip = values();

process.stdout.write(JSON.stringify(results));
JS;

        $tmp = sys_get_temp_dir().'/lesson-package-edit-fill-'.uniqid('', true).'.cjs';
        file_put_contents($tmp, $harness."\n".$functionSource."\n".$scenarios);

        try {
            $output = [];
            $exitCode = 0;
            exec('node '.escapeshellarg($tmp).' 2>&1', $output, $exitCode);
            $this->assertSame(0, $exitCode, implode("\n", $output));

            $payload = json_decode(implode("\n", $output), true);
            $this->assertIsArray($payload, implode("\n", $output));

            return $payload;
        } finally {
            @unlink($tmp);
        }
    }
}
