<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#lesson-package-auto-attendance-index совпадает с кодом автосписания.
 */
final class LessonPackageAutoAttendanceDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_auto_attendance_permission(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="lesson-package-auto-attendance-index"', $html);
        $start = strpos($html, 'id="lesson-package-auto-attendance-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-datatable-search-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('lesson_packages.auto_attendance_enabled', $chunk);
        $this->assertStringContainsString('только в шаблоне', $chunk);
        $this->assertStringContainsString('scheduleSlots.view', $chunk);
        $this->assertStringContainsString('@can(\'scheduleSlots.view\')', $chunk);
        $this->assertStringContainsString('00:05', $chunk);
        $this->assertStringContainsString('Europe/Moscow', $chunk);
        $this->assertStringContainsString('AutoMarkYesterdayLessonOccurrencesJob', $chunk);
        $this->assertStringContainsString('LessonPackageAutoAttendancePermission', $chunk);
        $this->assertStringContainsString('Недостаточно прав для включения автосписания.', $chunk);
        $this->assertStringContainsString('normalizePayload', $chunk);
        $this->assertStringContainsString('не должен выключить', $chunk);
        $this->assertStringContainsString('остаётся <code>200</code>', $chunk);
        $this->assertStringContainsString('lessonPackages.view', $chunk);
        $this->assertStringContainsString('no_schedule', $chunk);
        $this->assertStringContainsString('postpay', $chunk);
        $this->assertStringContainsString('422', $chunk);
        $this->assertStringContainsString('не тихое обнуление', $chunk);
        $this->assertStringContainsString('Job право пользователя не проверяет', $chunk);
        $this->assertStringContainsString('permission_capability_hints.php', $chunk);
        $this->assertStringContainsString('lesson-packages#auto-attendance', $chunk);
        $this->assertStringContainsString('LessonPackageAutoAttendanceAccessFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageAutoAttendanceAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageAutoAttendanceMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageAutoAttendanceWorkflowFeatureTest', $chunk);
        $this->assertStringContainsString('role_base_permissions.php', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('/schedule', $chunk);

        $this->assertStringContainsString('/doc#lesson-package-auto-attendance-index', $html);
    }

    public function test_lesson_packages_page_documents_permission_gate(): void
    {
        $html = $this->docFile('lesson-packages.html');

        $this->assertStringContainsString('id="auto-attendance"', $html);
        $this->assertStringContainsString('id="freeze-permission"', $html);
        $this->assertStringContainsString('/doc#lesson-package-auto-attendance-index', $html);
        $this->assertStringContainsString('/doc#lesson-package-freeze-permission-index', $html);
        $this->assertStringContainsString('@can(\'scheduleSlots.view\')', $html);
        $this->assertStringContainsString('LessonPackageAutoAttendancePermission', $html);
        $this->assertStringContainsString('LessonPackageFreezePermission', $html);
        $this->assertStringContainsString('Недостаточно прав для включения автосписания.', $html);
        $this->assertStringContainsString('Недостаточно прав для включения заморозки.', $html);
        $this->assertStringContainsString('остаётся <code>200</code>', $html);
        $this->assertStringContainsString('normalizePayload', $html);
        $this->assertStringContainsString('LessonPackageAutoAttendanceAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageAutoAttendanceMarkupFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageAutoAttendanceWorkflowFeatureTest', $html);
        $this->assertStringContainsString('AutoMarkYesterdayLessonOccurrencesJob', $html);
    }

    public function test_postpay_page_documents_422_on_auto_attendance(): void
    {
        $html = $this->docFile('postpay.html');

        $this->assertStringContainsString('auto_attendance_enabled=1', $html);
        $this->assertStringContainsString('422', $html);
        $this->assertStringContainsString('не тихое обнуление', $html);
        $this->assertStringContainsString('/doc#lesson-package-auto-attendance-index', $html);
    }

    public function test_permission_groups_and_calendar_link_announcement(): void
    {
        $groups = $this->docFile('settings-permission-groups.html');
        $this->assertStringContainsString('scheduleSlots.view', $groups);
        $this->assertStringContainsString('Автосписание', $groups);
        $this->assertStringContainsString('Разрешена заморозка', $groups);
        $this->assertStringContainsString('Срок действия (дни)', $groups);
        $this->assertStringContainsString('/doc#lesson-package-auto-attendance-index', $groups);
        $this->assertStringContainsString('/doc#lesson-package-freeze-permission-index', $groups);

        $calendar = $this->docFile('school-schedule-calendar.html');
        $this->assertStringContainsString('scheduleSlots.view', $calendar);
        $this->assertStringContainsString('/doc#lesson-package-auto-attendance-index', $calendar);
        $this->assertStringContainsString('LessonPackageAutoAttendanceAjaxContractFeatureTest', $calendar);
        $this->assertStringContainsString('LessonPackageAutoAttendanceMarkupFeatureTest', $calendar);
        $this->assertStringContainsString('LessonPackageAutoAttendanceWorkflowFeatureTest', $calendar);
        $this->assertStringContainsString('Job право пользователя не проверяет', $calendar);
    }

    public function test_capability_hint_and_controller_mention_checkbox(): void
    {
        $hints = (string) file_get_contents(dirname(__DIR__, 3).'/config/permission_capability_hints.php');
        $this->assertStringContainsString("'scheduleSlots.view'", $hints);
        $this->assertStringContainsString('Чекбокс «Автосписание» в модалках шаблона абонемента', $hints);
        $this->assertStringContainsString('Чекбокс «Разрешена заморозка» в модалках шаблона абонемента', $hints);
        $this->assertStringContainsString('Поле «Срок действия (дни)» в модалках шаблона абонемента', $hints);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('автосписание, заморозка и срок действия (поля при scheduleSlots.view)', $controller);
    }

    public function test_helper_deny_message_matches_docs(): void
    {
        $helper = (string) file_get_contents(dirname(__DIR__, 3).'/app/Support/LessonPackageAutoAttendancePermission.php');
        $this->assertStringContainsString("NAME = 'scheduleSlots.view'", $helper);
        $this->assertStringContainsString('Недостаточно прав для включения автосписания.', $helper);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
