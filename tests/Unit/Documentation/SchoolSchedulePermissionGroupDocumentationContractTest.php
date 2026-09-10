<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#school-schedule-permission-group-index совпадает с каталогом групп.
 */
final class SchoolSchedulePermissionGroupDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_school_schedule_permission_group(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="school-schedule-permission-group-index"', $html);
        $start = strpos($html, 'id="school-schedule-permission-group-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="flexible-ui-label-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('schoolSchedule', $chunk);
        $this->assertStringContainsString('Расписание школы', $chunk);
        $this->assertStringContainsString('scheduleSlots.view', $chunk);
        $this->assertStringContainsString('scheduleSlots.manage', $chunk);
        $this->assertStringContainsString('scheduleSlots.table', $chunk);
        $this->assertStringContainsString('lessonPackages.export', $chunk);
        $this->assertStringContainsString('setPrices.packageAssignments.view', $chunk);
        $this->assertStringContainsString('lessonPackages.manualPaid.manage', $chunk);
        $this->assertStringContainsString('2026_09_06_032500_add_school_schedule_permission_group.php', $chunk);
        $this->assertStringContainsString('2026_09_06_035300_hide_school_schedule_permissions.php', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('Новым партнёрам', $chunk);
        $this->assertStringContainsString('role_base_permissions.php', $chunk);
        $this->assertStringContainsString('permission_role', $chunk);
        $this->assertStringContainsString('SchoolSchedulePermissionGroupCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerBasePermissionsTest', $chunk);
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissionsAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissionsUxFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissionsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissionsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('test_school_schedule_hidden_permissions_gate_export_and_both_slot_create_open_paths', $chunk);
        $this->assertStringContainsString('settings-permission-groups', $chunk);
        $this->assertStringContainsString('sidebar.blade.php', $chunk);
        $this->assertStringContainsString('exportXlsx: null', $chunk);
        $this->assertStringContainsString('school-schedule\\/export', $chunk);
        $this->assertStringContainsString('/admin/directories/lesson-packages', $chunk);
        $this->assertStringContainsString('schoolCalOpenSlotCreateModal', $chunk);
        $this->assertStringContainsString('openSlotCreateModalWithDefaults', $chunk);
        $this->assertStringContainsString('Кастомная роль', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('lessonPackages.view', $chunk);
        $this->assertStringContainsString('Таблица занятий', $chunk);
        $this->assertStringContainsString('Назначение абонементов', $chunk);
        $this->assertStringContainsString('Статусы занятий', $chunk);
        $this->assertStringContainsString('users-search', $chunk);
        $this->assertStringContainsString('SchoolSchedulePageFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('settings-roles-custom', $chunk);
        $this->assertStringContainsString('set-prices-package-assignments', $chunk);
        $this->assertStringContainsString('school-schedule-calendar', $chunk);
        $this->assertStringContainsString('lesson-package-auto-attendance-index', $chunk);
        $this->assertStringContainsString('lesson-package-freeze-permission-index', $chunk);
        $this->assertStringContainsString('lesson-package-duration-permission-index', $chunk);
        $this->assertStringContainsString('колонки «Срок действия (дни)»', $chunk);
        $this->assertStringContainsString('колонках таблицы', $chunk);
    }

    public function test_permission_groups_page_documents_school_schedule_group(): void
    {
        $html = $this->docFile('settings-permission-groups.html');

        $this->assertStringContainsString('<code>schoolSchedule</code>', $html);
        $this->assertStringContainsString('Расписание школы', $html);
        $this->assertStringContainsString('17 групп', $html);
        $this->assertStringContainsString('2026_09_06_032500_add_school_schedule_permission_group.php', $html);
        $this->assertStringContainsString('2026_09_06_035300_hide_school_schedule_permissions.php', $html);
        $this->assertStringContainsString('SchoolSchedulePermissionGroupCatalogFeatureTest', $html);
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissionsAccessFeatureTest', $html);
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissionsUxFeatureTest', $html);
        $this->assertStringContainsString('setPrices.packageAssignments.view', $html);
        $this->assertStringContainsString('is_visible=0', $html);
        $this->assertStringContainsString('/doc#school-schedule-permission-group-index', $html);
    }

    public function test_package_assignments_page_points_to_school_schedule_group(): void
    {
        $html = $this->docFile('set-prices-package-assignments.html');

        $this->assertStringContainsString('<code>schoolSchedule</code>', $html);
        $this->assertStringContainsString('Расписание школы', $html);
        $this->assertStringContainsString('sort_order: 50', $html);
        $this->assertStringContainsString('/doc#school-schedule-permission-group-index', $html);
        $this->assertStringContainsString('не</b> отображается', $html);
    }

    public function test_documentation_controller_mentions_school_schedule_group(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'settings-permission-groups'", $controller);
        $this->assertStringContainsString('Расписание школы', $controller);
        $this->assertStringContainsString('schoolSchedule', $controller);
    }

    public function test_custom_roles_and_calendar_docs_mention_hidden_default_tests(): void
    {
        $roles = $this->docFile('settings-roles-custom.html');
        $this->assertStringContainsString('schoolSchedule', $roles);
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissionsAjaxContractFeatureTest', $roles);
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissionsNonAjaxSafetyNetFeatureTest', $roles);

        $calendar = $this->docFile('school-schedule-calendar.html');
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissionsAccessFeatureTest', $calendar);
        $this->assertStringContainsString('test_school_schedule_hidden_permissions_gate_export_and_both_slot_create_open_paths', $calendar);
        $this->assertStringContainsString('@can(\'scheduleSlots.view\')', $calendar);
        $this->assertStringContainsString('sidebar.blade.php', $calendar);
        $this->assertStringContainsString('exportXlsx: null', $calendar);
        $this->assertStringContainsString('school-schedule\\/export', $calendar);
        $this->assertStringContainsString('Это <b>не</b> export', $calendar);
        $this->assertStringNotContainsString('200 для всех endpoint\'ов раздела при <code>lessonPackages.view</code>', $calendar);

        $partners = $this->docFile('partners-permissions.html');
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissionsAccessFeatureTest', $partners);
        $this->assertStringContainsString('SchoolScheduleHiddenDefaultPermissions', $partners);
        $this->assertStringContainsString('автосписание', $partners);
    }

    public function test_related_announcements_link_school_schedule_group(): void
    {
        $html = $this->docFile('index.html');

        $durationStart = strpos($html, 'id="lesson-package-duration-permission-index"');
        $this->assertNotFalse($durationStart);
        $durationEnd = strpos($html, 'id="lesson-package-freeze-permission-index"');
        $this->assertNotFalse($durationEnd);
        $durationChunk = substr($html, $durationStart, $durationEnd - $durationStart);
        $this->assertStringContainsString('/doc#school-schedule-permission-group-index', $durationChunk);

        $freezeStart = strpos($html, 'id="lesson-package-freeze-permission-index"');
        $this->assertNotFalse($freezeStart);
        $freezeEnd = strpos($html, 'id="setting-prices-month-prolong-index"');
        $this->assertNotFalse($freezeEnd);
        $freezeChunk = substr($html, $freezeStart, $freezeEnd - $freezeStart);
        $this->assertStringContainsString('/doc#school-schedule-permission-group-index', $freezeChunk);

        $autoStart = strpos($html, 'id="lesson-package-auto-attendance-index"');
        $this->assertNotFalse($autoStart);
        $autoEnd = strpos($html, 'id="reports-datatable-search-index"');
        $this->assertNotFalse($autoEnd);
        $autoChunk = substr($html, $autoStart, $autoEnd - $autoStart);
        $this->assertStringContainsString('/doc#school-schedule-permission-group-index', $autoChunk);

        $packages = $this->docFile('lesson-packages.html');
        $this->assertStringContainsString('/doc#school-schedule-permission-group-index', $packages);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
