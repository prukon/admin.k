<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#lesson-package-edit-fill-defaults-index совпадает с JS fill edit шаблона.
 */
final class LessonPackageEditFillDefaultsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_edit_fill_defaults(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="lesson-package-edit-fill-defaults-index"', $html);
        $start = strpos($html, 'id="lesson-package-edit-fill-defaults-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="account-two-factor-permission-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('lessons_count', $chunk);
        $this->assertStringContainsString('applyEditScheduleTypeUi()', $chunk);
        $this->assertStringContainsString('applyEditScheduleTypeUi(true)', $chunk);
        $this->assertStringContainsString('GET /admin/lesson-packages/{id}', $chunk);
        $this->assertStringContainsString('canSnapshotPrevious', $chunk);
        $this->assertStringContainsString('/admin/directories/lesson-packages', $chunk);
        $this->assertStringContainsString('lesson-packages#edit-fill-defaults', $chunk);
        $this->assertStringContainsString('LessonPackageEditFillDefaultsAccessFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageEditFillDefaultsMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageEditFillDefaultsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageEditFillDefaultsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageEditFillDefaultsJsContractFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageEditFillDefaultsWorkflowFeatureTest', $chunk);
        $this->assertStringContainsString('test_lesson_packages_edit_fill_defaults_inline_script_is_valid_javascript', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('LessonPackageEditFillDefaultsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('Create не меняли', $chunk);
        $this->assertStringContainsString('shown.bs.modal', $chunk);
        $this->assertStringContainsString('служебные', $chunk);
        $this->assertStringContainsString('lesson-package-duration-permission-index', $chunk);
        $this->assertStringContainsString('фикс↔предоплата', $chunk);
        $this->assertStringContainsString('1 занятие / 31 день', $chunk);
        $this->assertStringContainsString('/doc#lesson-package-edit-fill-defaults-index', $html);
    }

    public function test_lesson_packages_page_documents_edit_fill_defaults(): void
    {
        $html = $this->docFile('lesson-packages.html');

        $this->assertStringContainsString('id="edit-fill-defaults"', $html);
        $this->assertStringContainsString('/doc#lesson-package-edit-fill-defaults-index', $html);
        $this->assertStringContainsString('applyEditScheduleTypeUi()', $html);
        $this->assertStringContainsString('applyEditScheduleTypeUi(true)', $html);
        $this->assertStringContainsString('canSnapshotPrevious', $html);
        $this->assertStringContainsString('lessons_count', $html);
        $this->assertStringContainsString('LessonPackageEditFillDefaultsJsContractFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageEditFillDefaultsAccessFeatureTest', $html);
        $this->assertStringContainsString('test_lesson_packages_edit_fill_defaults_inline_script_is_valid_javascript', $html);
        $this->assertStringContainsString('duration_days', $html);
        $this->assertStringContainsString('Create не меняли', $html);
        $this->assertStringContainsString('shown.bs.modal', $html);
        $this->assertStringContainsString('§duration-permission', $html);
        $this->assertStringContainsString('lp.lessons_count || 8', $html);
        $this->assertStringContainsString('фикс↔предоплата', $html);
    }

    public function test_packages_blade_keeps_server_values_on_edit_open(): void
    {
        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/lessonPackages/tabs/packages.blade.php');
        $this->assertStringContainsString('function applyEditScheduleTypeUi(fromTypeChange)', $blade);
        $this->assertStringContainsString('applyEditScheduleTypeUi(true)', $blade);
        $this->assertStringContainsString('applyEditScheduleTypeUi();', $blade);
        $this->assertStringContainsString('else if (fromTypeChange)', $blade);
        $this->assertStringContainsString('canSnapshotPrevious', $blade);
        $this->assertStringContainsString("editLessons.value = '8'", $blade);
        $this->assertStringContainsString("editDuration.value = '30'", $blade);
        $this->assertStringContainsString('lp.lessons_count || 8', $blade);
        $this->assertStringContainsString('lp.duration_days || 30', $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
