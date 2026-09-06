<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Permissions\Concerns\SchoolScheduleHiddenDefaultPermissionsTestHelpers;

/**
 * Разметка и правила «если X, то по умолчанию Y» для скрытой группы «Расписание школы».
 * Каждый UI-триггер открытия/пересборки и негатив «не X → Y не навязывается».
 *
 * @see SchoolScheduleHiddenDefaultPermissionsAccessFeatureTest
 * @see /docs/documentation/settings-permission-groups.html
 */
final class SchoolScheduleHiddenDefaultPermissionsUxFeatureTest extends CrmTestCase
{
    use SchoolScheduleHiddenDefaultPermissionsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
    }

    public function test_new_partner_admin_sidebar_has_no_school_schedule_item_on_first_open_and_reopen(): void
    {
        $first = $this->sidebarChunk(
            $this->get(route('admin.user1'))->assertOk()->getContent()
        );
        $second = $this->sidebarChunk(
            $this->get(route('admin.user1'))->assertOk()->getContent()
        );

        foreach ([$first, $second] as $sidebar) {
            $this->assertStringNotContainsString('<p>Расписание школы</p>', $sidebar);
            $this->assertStringNotContainsString('/admin/lesson-packages/school-schedule', $sidebar);
        }
    }

    public function test_packages_tabs_hide_table_and_assignments_but_keep_calendar_tab(): void
    {
        $first = $this->get(route('admin.lesson-packages.index'))->assertOk()->getContent();
        $second = $this->get(route('admin.lesson-packages.index'))->assertOk()->getContent();

        foreach ([$first, $second] as $html) {
            $tabs = $this->lessonPackagesTabsChunk($html);
            $this->assertStringContainsString('>Расписание школы</a>', $tabs);
            $this->assertStringContainsString('>Абонементы</a>', $tabs);
            $this->assertStringNotContainsString('>Таблица занятий</a>', $tabs);
            $this->assertStringNotContainsString('>Назначение абонементов</a>', $tabs);
        }

        $dirs = $this->get(route('admin.directories.lesson-packages.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('>Таблица занятий</a>', $dirs);
        $this->assertStringNotContainsString('>Назначение абонементов</a>', $dirs);
        $this->assertStringContainsString('id="lessonPackageCreateForm"', $dirs);
    }

    public function test_packages_create_modal_hides_freeze_duration_auto_and_keeps_field_order(): void
    {
        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $create = $this->packagesCreateModalHtml($html);

            $this->assertStringNotContainsString('id="create_duration_days"', $create);
            $this->assertStringNotContainsString('id="create_freeze_enabled"', $create);
            $this->assertStringNotContainsString('id="create_auto_attendance_enabled"', $create);
            $this->assertStringNotContainsString('name="create[freeze_enabled]"', $create);
            $this->assertStringContainsString('id="create_schedule_type"', $create);
            $this->assertStringContainsString('id="create_lessons_count"', $create);

            $typePos = strpos($create, 'id="create_schedule_type"');
            $lessonsPos = strpos($create, 'id="create_lessons_count"');
            $pricePos = strpos($create, 'id="create_price_label"');
            $this->assertNotFalse($typePos);
            $this->assertNotFalse($lessonsPos);
            $this->assertNotFalse($pricePos);
            $this->assertTrue($typePos < $lessonsPos);
            $this->assertTrue($lessonsPos < $pricePos);
        }
    }

    public function test_calendar_first_open_and_reopen_hide_export_and_slot_manage_but_keep_location_filter(): void
    {
        $first = $this->get(route('admin.lesson-packages.school-schedule'))->assertOk()->getContent();
        $second = $this->get(route('admin.lesson-packages.school-schedule'))->assertOk()->getContent();

        foreach ([$first, $second] as $html) {
            $this->assertStringContainsString('id="schoolCalLocation"', $html);
            $this->assertStringContainsString('id="schoolCalGrid"', $html);
            $this->assertStringContainsString('exportXlsx: null', $html);
            $this->assertStringContainsString('if (!routes.exportXlsx)', $html);
            $this->assertStringNotContainsString('id="schoolCalExportBtn"', $html);
            $this->assertStringNotContainsString('id="schoolCalExportModal"', $html);
            $this->assertStringNotContainsString('school-cal__grid--manage-slots', $html);
            $this->assertStringNotContainsString('Изменить занятие', $html);
            $this->assertStringNotContainsString('id="slotCreateForm"', $html);
            $this->assertStringNotContainsString('id="slotCreateModal"', $html);
            $this->assertStringNotContainsString('function schoolCalOpenSlotCreateModal', $html);
            $this->assertStringNotContainsString('function openSlotCreateModalWithDefaults', $html);
            $this->assertStringNotContainsString('window.openSlotCreateModalWithDefaults', $html);
        }
    }

    public function test_matrix_html_does_not_list_hidden_school_schedule_permission_names_for_partner_admin(): void
    {
        $html = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertNotSame('', trim($html));

        foreach ($this->schoolSchedulePermissionNames() as $name) {
            $this->assertStringNotContainsString(
                $name,
                $html,
                "Админ школы не должен видеть строку права {$name} в матрице"
            );
        }
        $this->assertStringNotContainsString('data-group-slug="schoolSchedule"', $html);
    }

    public function test_granting_schedule_slots_view_shows_sidebar_and_package_extras_but_not_export_or_table(): void
    {
        $this->grantNamedPermissions(['scheduleSlots.view']);

        $sidebar = $this->sidebarChunk(
            $this->get(route('admin.user1'))->assertOk()->getContent()
        );
        $this->assertStringContainsString('<p>Расписание школы</p>', $sidebar);
        $this->assertStringContainsString('/admin/lesson-packages/school-schedule', $sidebar);

        $packages = $this->get(route('admin.lesson-packages.index'))->assertOk()->getContent();
        $tabs = $this->lessonPackagesTabsChunk($packages);
        $this->assertStringNotContainsString('>Таблица занятий</a>', $tabs);
        $this->assertStringNotContainsString('>Назначение абонементов</a>', $tabs);

        $create = $this->packagesCreateModalHtml($packages);
        $this->assertStringContainsString('id="create_duration_days"', $create);
        $this->assertMatchesRegularExpression(
            '/id="create_duration_days"[^>]*value="30"/',
            $create
        );
        $this->assertStringContainsString('id="create_freeze_enabled"', $create);
        $this->assertDoesNotMatchRegularExpression(
            '/id="create_freeze_enabled"[^>]*\bchecked\b/',
            $create
        );
        $this->assertStringContainsString('id="create_auto_attendance_enabled"', $create);
        $this->assertDoesNotMatchRegularExpression(
            '/id="create_auto_attendance_enabled"[^>]*\bchecked\b/',
            $create
        );

        $typePos = strpos($create, 'id="create_schedule_type"');
        $durationPos = strpos($create, 'id="create_duration_days"');
        $lessonsPos = strpos($create, 'id="create_lessons_count"');
        $freezePos = strpos($create, 'id="create_freeze_enabled"');
        $autoPos = strpos($create, 'id="create_auto_attendance_enabled"');
        $this->assertTrue($typePos < $durationPos);
        $this->assertTrue($durationPos < $lessonsPos);
        $this->assertTrue($lessonsPos < $freezePos);
        $this->assertTrue($freezePos < $autoPos);

        $calendar = $this->get(route('admin.lesson-packages.school-schedule'))->assertOk()->getContent();
        $this->assertStringContainsString('exportXlsx: null', $calendar);
        $this->assertStringNotContainsString('id="schoolCalExportBtn"', $calendar);
        $this->assertStringNotContainsString('Изменить занятие', $calendar);
        $this->assertStringNotContainsString('id="slotCreateForm"', $calendar);
    }

    public function test_granting_export_shows_button_and_xlsx_url_without_forcing_manage_ui(): void
    {
        $this->grantNamedPermissions(['lessonPackages.export']);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))->assertOk()->getContent();

        $this->assertStringContainsString('id="schoolCalExportBtn"', $html);
        $this->assertStringContainsString('id="schoolCalExportModal"', $html);
        $this->assertStringContainsString('initSchoolCalExport', $html);
        $this->assertTrue(
            str_contains($html, 'school-schedule/export') || str_contains($html, 'school-schedule\/export'),
            'JS routes.exportXlsx должен содержать URL выгрузки, а не null'
        );
        $this->assertStringNotContainsString('exportXlsx: null', $html);
        $this->assertStringNotContainsString('Изменить занятие', $html);
        $this->assertStringNotContainsString('id="slotCreateForm"', $html);
        $this->assertStringNotContainsString('<p>Расписание школы</p>', $this->sidebarChunk($html));
    }

    public function test_granting_manage_shows_both_slot_open_paths_without_forcing_export(): void
    {
        $this->grantNamedPermissions(['scheduleSlots.manage']);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))->assertOk()->getContent();

        $this->assertStringContainsString('Изменить занятие', $html);
        $this->assertStringContainsString('id="slotCreateForm"', $html);
        $this->assertStringContainsString('id="slotCreateModal"', $html);
        $this->assertStringContainsString('school-cal__grid--manage-slots', $html);
        $this->assertStringContainsString('function schoolCalOpenSlotCreateModal', $html);
        $this->assertStringContainsString('function openSlotCreateModalWithDefaults', $html);
        $this->assertStringContainsString('window.openSlotCreateModalWithDefaults', $html);
        $this->assertStringContainsString('exportXlsx: null', $html);
        $this->assertStringNotContainsString('id="schoolCalExportBtn"', $html);
    }

    public function test_granting_table_or_assignments_shows_only_that_tab(): void
    {
        $this->grantNamedPermissions(['scheduleSlots.table']);
        $this->seedSchoolScheduleHiddenDefaultContext();

        $tablePage = $this->get(route('admin.lesson-packages.index'))->assertOk()->getContent();
        $tableTabs = $this->lessonPackagesTabsChunk($tablePage);
        $this->assertStringContainsString('>Таблица занятий</a>', $tableTabs);
        $this->assertStringNotContainsString('>Назначение абонементов</a>', $tableTabs);

        $tableHtml = $this->get(route('admin.lesson-packages.team-schedule-slots'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="slotCreateForm"', $tableHtml);
        $this->assertStringNotContainsString('js-slot-edit', $tableHtml);
        $this->assertStringNotContainsString('>Редактировать</button>', $tableHtml);

        $this->grantNamedPermissions(['setPrices.packageAssignments.view']);
        $assignPage = $this->get(route('admin.lesson-packages.index'))->assertOk()->getContent();
        $assignTabs = $this->lessonPackagesTabsChunk($assignPage);
        $this->assertStringContainsString('>Таблица занятий</a>', $assignTabs);
        $this->assertStringContainsString('>Назначение абонементов</a>', $assignTabs);
    }

    public function test_existing_permission_role_row_still_shows_sidebar_while_permission_stays_hidden(): void
    {
        DB::table('permission_role')->insert([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('scheduleSlots.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            0,
            (int) DB::table('permissions')->where('name', 'scheduleSlots.view')->value('is_visible')
        );

        $sidebar = $this->sidebarChunk(
            $this->get(route('admin.user1'))->assertOk()->getContent()
        );
        $this->assertStringContainsString('<p>Расписание школы</p>', $sidebar);
    }

    public function test_superadmin_sees_sidebar_export_freeze_and_matrix_group_without_pivot_rows(): void
    {
        $this->asSuperadmin();

        foreach ($this->schoolSchedulePermissionNames() as $name) {
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $this->partner->id)
                    ->where('role_id', $this->user->role_id)
                    ->where('permission_id', $this->permissionId($name))
                    ->exists(),
                "superadmin не должен иметь pivot {$name} — срабатывает Gate::before"
            );
        }

        $sidebar = $this->sidebarChunk(
            $this->get(route('admin.user1'))->assertOk()->getContent()
        );
        $this->assertStringContainsString('<p>Расписание школы</p>', $sidebar);

        $packages = $this->get(route('admin.lesson-packages.index'))->assertOk()->getContent();
        $tabs = $this->lessonPackagesTabsChunk($packages);
        $this->assertStringContainsString('>Таблица занятий</a>', $tabs);
        $this->assertStringContainsString('>Назначение абонементов</a>', $tabs);
        $create = $this->packagesCreateModalHtml($packages);
        $this->assertStringContainsString('id="create_freeze_enabled"', $create);

        $calendar = $this->get(route('admin.lesson-packages.school-schedule'))->assertOk()->getContent();
        $this->assertStringContainsString('id="schoolCalExportBtn"', $calendar);
        $this->assertStringContainsString('Изменить занятие', $calendar);
        $this->assertStringContainsString('id="slotCreateForm"', $calendar);

        $rules = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertStringContainsString('Расписание школы', $rules);
        foreach ($this->schoolSchedulePermissionNames() as $name) {
            $this->assertStringContainsString($name, $rules);
        }
    }

    public function test_superadmin_grant_then_partner_admin_sees_sidebar(): void
    {
        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');
        $this->assertGreaterThan(0, $adminRoleId);

        $this->asSuperadmin();
        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id' => $adminRoleId,
            'permission_id' => $this->permissionId('scheduleSlots.view'),
            'value' => 'true',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->asAdmin();
        $sidebar = $this->sidebarChunk(
            $this->get(route('admin.user1'))->assertOk()->getContent()
        );
        $this->assertStringContainsString('<p>Расписание школы</p>', $sidebar);
    }

}
