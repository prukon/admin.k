<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#lesson-package-duration-permission-index совпадает с кодом срока действия.
 */
final class LessonPackageDurationDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_duration_permission(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="lesson-package-duration-permission-index"', $html);
        $start = strpos($html, 'id="lesson-package-duration-permission-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="lesson-package-freeze-permission-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('lesson_packages.duration_days', $chunk);
        $this->assertStringContainsString('scheduleSlots.view', $chunk);
        $this->assertStringContainsString('@can(\'scheduleSlots.view\')', $chunk);
        $this->assertStringContainsString('#create_duration_wrap', $chunk);
        $this->assertStringContainsString('#edit_duration_wrap', $chunk);
        $this->assertStringContainsString('LessonPackageDurationPermission', $chunk);
        $this->assertStringContainsString('30', $chunk);
        $this->assertStringContainsString('normalizePayload', $chunk);
        $this->assertStringContainsString('остаётся <code>200</code>', $chunk);
        $this->assertStringContainsString('lessonPackages.view', $chunk);
        $this->assertStringContainsString('no_schedule', $chunk);
        $this->assertStringContainsString('postpay', $chunk);
        $this->assertStringContainsString('permission_capability_hints.php', $chunk);
        $this->assertStringContainsString('lesson-packages#duration-permission', $chunk);
        $this->assertStringContainsString('LessonPackageDurationPermissionFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageDurationAccessFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageDurationAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageDurationNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageDurationMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageDurationWorkflowFeatureTest', $chunk);
        $this->assertStringContainsString('классического', $chunk);
        $this->assertStringContainsString('billing_month', $chunk);
        $this->assertStringContainsString('period-rule', $chunk);
        $this->assertStringContainsString('Укажите длительность в днях.', $chunk);
        $this->assertStringContainsString('Занятий', $chunk);
        $this->assertStringContainsString('role_base_permissions.php', $chunk);
        $this->assertStringContainsString('DEFAULT_CREATE_DAYS = 30', $chunk);

        $this->assertStringContainsString('/doc#lesson-package-duration-permission-index', $html);
    }

    public function test_lesson_packages_page_documents_duration_gate(): void
    {
        $html = $this->docFile('lesson-packages.html');

        $this->assertStringContainsString('id="duration-permission"', $html);
        $this->assertStringContainsString('/doc#lesson-package-duration-permission-index', $html);
        $this->assertStringContainsString('LessonPackageDurationPermission', $html);
        $this->assertStringContainsString('DEFAULT_CREATE_DAYS = 30', $html);
        $this->assertStringContainsString('prepareForValidation', $html);
        $this->assertStringContainsString('normalizePayload', $html);
        $this->assertStringContainsString('@can(\'scheduleSlots.view\')', $html);
        $this->assertStringContainsString('LessonPackageDurationPermissionFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageDurationAccessFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageDurationAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageDurationNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageDurationMarkupFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageDurationWorkflowFeatureTest', $html);
        $this->assertStringContainsString('if (editDuration)', $html);
        $this->assertStringContainsString('Укажите длительность в днях.', $html);
        $this->assertStringContainsString('period-rule', $html);
        $this->assertStringContainsString('auto-prolong', $html);
        $this->assertStringContainsString('lessons_count', $html);
    }

    public function test_capability_hint_and_helper_match_docs(): void
    {
        $hints = (string) file_get_contents(dirname(__DIR__, 3).'/config/permission_capability_hints.php');
        $this->assertStringContainsString("'scheduleSlots.view'", $hints);
        $this->assertStringContainsString('Поле «Срок действия (дни)» в модалках шаблона абонемента', $hints);

        $helper = (string) file_get_contents(dirname(__DIR__, 3).'/app/Support/LessonPackageDurationPermission.php');
        $this->assertStringContainsString("NAME = 'scheduleSlots.view'", $helper);
        $this->assertStringContainsString('DEFAULT_CREATE_DAYS = 30', $helper);
        $this->assertStringContainsString('POSTPAY_DAYS = 31', $helper);
        $this->assertStringContainsString('NO_SCHEDULE_DAYS = 1', $helper);

        $request = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/Admin/StoreLessonPackageRequest.php');
        $this->assertStringContainsString('LessonPackageDurationPermission', $request);
        $this->assertStringContainsString('resolvedDurationDays', $request);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/lessonPackages/tabs/packages.blade.php');
        $this->assertStringContainsString('id="create_duration_wrap"', $blade);
        $this->assertStringContainsString('id="edit_duration_wrap"', $blade);
        $this->assertStringContainsString('@can(\'scheduleSlots.view\')', $blade);
    }

    public function test_permission_groups_link_duration_section(): void
    {
        $groups = $this->docFile('settings-permission-groups.html');
        $this->assertStringContainsString('scheduleSlots.view', $groups);
        $this->assertStringContainsString('Срок действия (дни)', $groups);
        $this->assertStringContainsString('lesson-packages#duration-permission', $groups);
        $this->assertStringContainsString('/doc#lesson-package-duration-permission-index', $groups);
    }

    public function test_related_pages_do_not_contradict_duration_permission(): void
    {
        $calendar = $this->docFile('school-schedule-calendar.html');
        $this->assertStringContainsString('/doc#lesson-package-duration-permission-index', $calendar);
        $this->assertStringContainsString('duration_days', $calendar);
        $this->assertStringContainsString('ends_at = starts_at + duration_days', $calendar);

        $postpay = $this->docFile('postpay.html');
        $this->assertStringContainsString('duration_days = 31', $postpay);
        $this->assertStringContainsString('/doc#lesson-package-duration-permission-index', $postpay);
        $this->assertStringContainsString('независимо от', $postpay);

        $prices = $this->docFile('setting-prices-monthly-users.html');
        $this->assertStringContainsString('duration_days', $prices);
        $this->assertStringContainsString('не</b> задаёт период', $prices);
        $this->assertStringContainsString('/doc#lesson-package-duration-permission-index', $prices);

        $index = $this->docFile('index.html');
        $this->assertStringContainsString('/doc#lesson-package-duration-permission-index', $index);
        $autoStart = strpos($index, 'id="lesson-package-auto-attendance-index"');
        $this->assertNotFalse($autoStart);
        $autoEnd = strpos($index, 'id="reports-datatable-search-index"');
        $this->assertNotFalse($autoEnd);
        $autoChunk = substr($index, $autoStart, $autoEnd - $autoStart);
        $this->assertStringContainsString('Срок действия (дни)', $autoChunk);
        $this->assertStringContainsString('lesson-package-duration-permission-index', $autoChunk);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
