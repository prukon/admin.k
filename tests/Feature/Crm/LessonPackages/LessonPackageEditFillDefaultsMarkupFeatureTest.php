<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка модалок: create «Занятий» = 8, edit пустой input (fill из show JSON).
 *
 * @see LessonPackageEditFillDefaultsJsContractFeatureTest
 * @see docs/documentation/lesson-packages.html
 */
final class LessonPackageEditFillDefaultsMarkupFeatureTest extends CrmTestCase
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

    private function modalHtml(string $pageHtml, string $formId, string $untilMarker): string
    {
        $start = strpos($pageHtml, 'id="'.$formId.'"');
        $this->assertNotFalse($start, "Не найдена форма {$formId}");
        $end = strpos($pageHtml, $untilMarker, $start + 1);
        $this->assertNotFalse($end, "Не найден маркер {$untilMarker} после {$formId}");

        return substr($pageHtml, $start, $end - $start);
    }

    public function test_create_modal_shows_lessons_field_with_default_8_on_first_open(): void
    {
        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            $create = $this->modalHtml($html, 'lessonPackageCreateForm', 'id="lessonPackageEditModal"');

            $this->assertStringContainsString('id="create_lessons_wrap"', $create);
            $this->assertStringContainsString('id="create_lessons_count"', $create);
            $this->assertStringContainsString('name="create[lessons_count]"', $create);
            $this->assertStringContainsString('Занятий *', $create);
            $this->assertStringContainsString('data-error-for="create[lessons_count]"', $create);
            $this->assertMatchesRegularExpression(
                '/id="create_lessons_count"[^>]*\bvalue="8"/',
                $create,
                'При первом открытии create занятий должно быть 8.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/id="create_lessons_count"[^>]*\breadonly\b/',
                $create,
                'Для fixed/flexible занятий не readonly в статическом HTML.'
            );
        }
    }

    public function test_edit_modal_shows_empty_lessons_and_duration_inputs_for_js_to_fill_from_show(): void
    {
        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();

            $edit = $this->modalHtml($html, 'lessonPackageEditForm', 'id="lessonPackageDeleteModal"');

            $this->assertStringContainsString('id="edit_lessons_wrap"', $edit);
            $this->assertStringContainsString('id="edit_lessons_count"', $edit);
            $this->assertStringContainsString('name="edit[lessons_count]"', $edit);
            $this->assertStringContainsString('Занятий *', $edit);
            $this->assertStringContainsString('data-error-for="edit[lessons_count]"', $edit);
            $this->assertDoesNotMatchRegularExpression(
                '/id="edit_lessons_count"[^>]*\bvalue="/',
                $edit,
                'Статический HTML edit не должен зашивать занятий — значение ставит JS из show JSON.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/id="edit_duration_days"[^>]*\bvalue="/',
                $edit,
                'Статический HTML edit не должен зашивать срок — иначе 30 перебьёт show.'
            );
        }
    }

    public function test_edit_script_on_both_pages_fills_from_show_then_applies_ui_without_type_change_flag(): void
    {
        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();

            $this->assertStringContainsString('function applyEditScheduleTypeUi(fromTypeChange)', $html);
            $this->assertStringContainsString('applyEditScheduleTypeUi(true)', $html);
            $this->assertStringContainsString('else if (fromTypeChange)', $html);
            $this->assertStringContainsString('canSnapshotPrevious', $html);
            $this->assertStringContainsString('lp.lessons_count || 8', $html);
            $this->assertStringContainsString('editDuration.value = lp.duration_days || 30', $html);
            $this->assertStringContainsString('editSnapshotBeforeSingle = null;', $html);
            $this->assertStringContainsString('applyEditScheduleTypeUi();', $html);
            $this->assertStringContainsString("editLessons.value = '8'", $html);
            $this->assertStringContainsString("editDuration.value = '30'", $html);
        }
    }
}
