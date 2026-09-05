<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#lesson-package-freeze-permission-index совпадает с кодом заморозки шаблона.
 */
final class LessonPackageFreezeDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_freeze_permission(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="lesson-package-freeze-permission-index"', $html);
        $start = strpos($html, 'id="lesson-package-freeze-permission-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-month-prolong-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('lesson_packages.freeze_enabled', $chunk);
        $this->assertStringContainsString('freeze_days', $chunk);
        $this->assertStringContainsString('только в карточке абонемента', $chunk);
        $this->assertStringContainsString('не читают', $chunk);
        $this->assertStringContainsString('scheduleSlots.view', $chunk);
        $this->assertStringContainsString('@can(\'scheduleSlots.view\')', $chunk);
        $this->assertStringContainsString('#create_freeze_enabled', $chunk);
        $this->assertStringContainsString('#edit_freeze_enabled', $chunk);
        $this->assertStringContainsString('LessonPackageFreezePermission', $chunk);
        $this->assertStringContainsString('Недостаточно прав для включения заморозки.', $chunk);
        $this->assertStringContainsString('normalizePayload', $chunk);
        $this->assertStringContainsString('сохраняются', $chunk);
        $this->assertStringContainsString('остаётся <code>200</code>', $chunk);
        $this->assertStringContainsString('lessonPackages.view', $chunk);
        $this->assertStringContainsString('no_schedule', $chunk);
        $this->assertStringContainsString('postpay', $chunk);
        $this->assertStringContainsString('тихо', $chunk);
        $this->assertStringContainsString('422', $chunk);
        $this->assertStringContainsString('permission_capability_hints.php', $chunk);
        $this->assertStringContainsString('lesson-packages#freeze-permission', $chunk);
        $this->assertStringContainsString('LessonPackageFreezePermissionFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageFreezeAccessFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageFreezeAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageFreezeNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageFreezeMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageFreezeWorkflowFeatureTest', $chunk);
        $this->assertStringContainsString('test_lesson_packages_freeze_permission_inline_script_is_valid_javascript', $chunk);
        $this->assertStringContainsString('role_base_permissions.php', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('freeze_label', $chunk);

        $this->assertStringContainsString('/doc#lesson-package-freeze-permission-index', $html);
    }

    public function test_lesson_packages_page_documents_freeze_gate(): void
    {
        $html = $this->docFile('lesson-packages.html');

        $this->assertStringContainsString('id="freeze-permission"', $html);
        $this->assertStringContainsString('/doc#lesson-package-freeze-permission-index', $html);
        $this->assertStringContainsString('LessonPackageFreezePermission', $html);
        $this->assertStringContainsString('Недостаточно прав для включения заморозки.', $html);
        $this->assertStringContainsString('normalizePayload', $html);
        $this->assertStringContainsString('prepareForValidation', $html);
        $this->assertStringContainsString('тихо', $html);
        $this->assertStringContainsString('Для разового занятия заморозка недоступна.', $html);
        $this->assertStringContainsString('LessonPackageFreezePermissionFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageFreezeAccessFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageFreezeAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageFreezeNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageFreezeMarkupFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageFreezeWorkflowFeatureTest', $html);
        $this->assertStringContainsString('if (editFreezeEnabled)', $html);
        $this->assertStringContainsString('if (editFreezeDaysInput)', $html);
        $this->assertStringContainsString('не читают', $html);
    }

    public function test_postpay_and_calendar_document_freeze_is_not_runtime(): void
    {
        $postpay = $this->docFile('postpay.html');
        $this->assertStringContainsString('freeze_enabled=false', $postpay);
        $this->assertStringContainsString('prepareForValidation', $postpay);
        $this->assertStringContainsString('/doc#lesson-package-freeze-permission-index', $postpay);

        $calendar = $this->docFile('school-schedule-calendar.html');
        $this->assertStringContainsString('не читает', $calendar);
        $this->assertStringContainsString('/doc#lesson-package-freeze-permission-index', $calendar);
        $this->assertStringContainsString('freeze_enabled', $calendar);
    }

    public function test_capability_hint_and_helper_match_docs(): void
    {
        $hints = (string) file_get_contents(dirname(__DIR__, 3).'/config/permission_capability_hints.php');
        $this->assertStringContainsString("'scheduleSlots.view'", $hints);
        $this->assertStringContainsString('Чекбокс «Разрешена заморозка» в модалках шаблона абонемента', $hints);

        $helper = (string) file_get_contents(dirname(__DIR__, 3).'/app/Support/LessonPackageFreezePermission.php');
        $this->assertStringContainsString("NAME = 'scheduleSlots.view'", $helper);
        $this->assertStringContainsString('Недостаточно прав для включения заморозки.', $helper);

        $request = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/Admin/StoreLessonPackageRequest.php');
        $this->assertStringContainsString('LessonPackageFreezePermission', $request);
        $this->assertStringContainsString("\$merge['freeze_enabled'] = false", $request);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/lessonPackages/tabs/packages.blade.php');
        $this->assertStringContainsString('if (editFreezeEnabled)', $blade);
        $this->assertStringContainsString('editFreezeDaysInput.value = lp.freeze_days || 7', $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
