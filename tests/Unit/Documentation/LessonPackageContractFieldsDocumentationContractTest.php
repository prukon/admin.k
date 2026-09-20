<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#lesson-package-contract-fields-index совпадает с полями шаблона для договора.
 */
final class LessonPackageContractFieldsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_contract_fields(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="lesson-package-contract-fields-index"', $html);
        $start = strpos($html, 'id="lesson-package-contract-fields-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="user-student-passport-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('lesson_packages.lessons_per_week', $chunk);
        $this->assertStringContainsString('lessons_per_month', $chunk);
        $this->assertStringContainsString('lesson_duration_minutes', $chunk);
        $this->assertStringContainsString('lesson_price_cents', $chunk);
        $this->assertStringContainsString('Для договора', $chunk);
        $this->assertStringContainsString('#create_contract_fields_section', $chunk);
        $this->assertStringContainsString('#edit_contract_fields_section', $chunk);
        $this->assertStringContainsString('colLessonPackageLessonsPerWeek', $chunk);
        $this->assertStringContainsString('lesson_price_label', $chunk);
        $this->assertStringContainsString('не читают', $chunk);
        $this->assertStringContainsString('normalizePayload', $chunk);
        $this->assertStringContainsString('fillOptionalNumber', $chunk);
        $this->assertStringContainsString('lesson-packages#contract-fields', $chunk);
        $this->assertStringContainsString('contracts.lessonPackage.bind', $chunk);
        $this->assertStringContainsString('canBindLessonPackage', $chunk);
        $this->assertStringContainsString('LessonPackageContractFieldsPermission', $chunk);
        $this->assertStringContainsString('не 422', $chunk);
        $this->assertStringContainsString('LessonPackageContractFieldsMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageContractFieldsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageContractFieldsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageContractFieldsWorkflowFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageContractFieldsAccessFeatureTest', $chunk);
        $this->assertStringContainsString('test_lesson_packages_contract_fields_inline_script_is_valid_javascript', $chunk);
        $this->assertStringContainsString('/doc#lesson-package-contract-fields-index', $html);
    }

    public function test_lesson_packages_page_and_live_code_match_contract_fields(): void
    {
        $html = $this->docFile('lesson-packages.html');
        $this->assertStringContainsString('id="contract-fields"', $html);
        $this->assertStringContainsString('/doc#lesson-package-contract-fields-index', $html);
        $this->assertStringContainsString('lessons_per_week', $html);
        $this->assertStringContainsString('lesson_price_cents', $html);
        $this->assertStringContainsString('create_contract_fields_section', $html);
        $this->assertStringContainsString('LessonPackageContractFieldsMarkupFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageContractFieldsAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageContractFieldsNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageContractFieldsWorkflowFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageContractFieldsAccessFeatureTest', $html);
        $this->assertStringContainsString('fillOptionalNumber', $html);
        $this->assertStringContainsString('не читают', $html);
        $this->assertStringContainsString('contracts.lessonPackage.bind', $html);
        $this->assertStringContainsString('canBindLessonPackage', $html);
        $this->assertStringContainsString('LessonPackageContractFieldsPermission', $html);

        $controllerTitles = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('поля «Для договора»', $controllerTitles);
        $this->assertStringContainsString('UI при contracts.lessonPackage.bind', $controllerTitles);

        $request = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/Admin/StoreLessonPackageRequest.php');
        $this->assertStringContainsString("'lessons_per_week'", $request);
        $this->assertStringContainsString("'lesson_price'", $request);
        $this->assertStringContainsString('Количество занятий в неделю должно быть больше нуля.', $request);
        $this->assertStringContainsString('LessonPackageContractFieldsPermission', $request);

        $helper = (string) file_get_contents(dirname(__DIR__, 3).'/app/Support/LessonPackageContractFieldsPermission.php');
        $this->assertStringContainsString('userCanManage', $helper);
        $this->assertStringContainsString('resolvedInt', $helper);

        $hints = (string) file_get_contents(dirname(__DIR__, 3).'/config/permission_capability_hints.php');
        $this->assertStringContainsString("блок «Для договора» и колонки недели/месяца/минут/цены занятия", $hints);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/lessonPackages/tabs/packages.blade.php');
        $this->assertStringContainsString('id="create_contract_fields_section"', $blade);
        $this->assertStringContainsString('id="edit_contract_fields_section"', $blade);
        $this->assertStringContainsString('id="colLessonPackageLessonsPerWeek"', $blade);
        $this->assertStringContainsString('@can(\'contracts.lessonPackage.bind\')', $blade);
        $this->assertStringContainsString('const canBindLessonPackage', $blade);
        $this->assertStringContainsString('when: canBindLessonPackage', $blade);
        $this->assertStringContainsString('function fillOptionalNumber(input, value)', $blade);
        $this->assertStringContainsString("lessons_per_week: (formData.get(prefix + '[lessons_per_week]') || '').toString()", $blade);
        $this->assertStringContainsString("lesson_price: (formData.get(prefix + '[lesson_price]') || '').toString()", $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
