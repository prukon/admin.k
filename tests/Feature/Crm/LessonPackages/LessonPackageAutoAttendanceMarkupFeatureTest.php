<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка модалок шаблона абонемента: чекбокс «Автосписание» и @can('scheduleSlots.view').
 *
 * @see LessonPackageAutoAttendanceAccessFeatureTest
 * @see docs/documentation/lesson-packages.html
 */
final class LessonPackageAutoAttendanceMarkupFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
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

    public function test_create_modal_shows_unchecked_auto_attendance_checkbox_when_manager_has_schedule_slots_view(): void
    {
        $this->grantPermission('scheduleSlots.view');

        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            $create = $this->modalHtml($html, 'lessonPackageCreateForm', 'id="lessonPackageEditModal"');

            $this->assertStringContainsString('id="create_freeze_section"', $create);
            $this->assertStringContainsString('id="create_freeze_enabled"', $create);
            $this->assertStringContainsString('name="create[freeze_enabled]"', $create);
            $this->assertStringContainsString('Разрешена заморозка', $create);
            $this->assertStringContainsString('id="create_duration_wrap"', $create);
            $this->assertStringContainsString('id="create_duration_days"', $create);
            $this->assertStringContainsString('name="create[duration_days]"', $create);
            $this->assertStringContainsString('Срок действия (дни) *', $create);
            $this->assertStringContainsString('id="create_auto_attendance_section"', $create);
            $this->assertStringContainsString('id="create_auto_attendance_enabled"', $create);
            $this->assertStringContainsString('name="create[auto_attendance_enabled]"', $create);
            $this->assertStringContainsString('for="create_auto_attendance_enabled"', $create);
            $this->assertStringContainsString('Автосписание', $create);
            $this->assertStringContainsString('data-error-for="create[auto_attendance_enabled]"', $create);
            $this->assertDoesNotMatchRegularExpression(
                '/id="create_auto_attendance_enabled"[^>]*\bchecked\b/',
                $create,
                'При первом открытии create чекбокс должен быть выключен (нет checked).'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/id="create_auto_attendance_enabled"[^>]*\bdisabled\b/',
                $create
            );
            $this->assertLessThan(
                (int) strpos($create, 'id="create_auto_attendance_section"'),
                (int) strpos($create, 'id="create_freeze_section"'),
                'Автосписание в create должно идти после блока заморозки.'
            );
        }
    }

    public function test_edit_modal_shows_auto_attendance_checkbox_for_js_to_fill_from_show_payload(): void
    {
        $this->grantPermission('scheduleSlots.view');

        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();

            $edit = $this->modalHtml($html, 'lessonPackageEditForm', 'id="lessonPackageDeleteModal"');

            $this->assertStringContainsString('id="edit_freeze_section"', $edit);
            $this->assertStringContainsString('id="edit_freeze_enabled"', $edit);
            $this->assertStringContainsString('name="edit[freeze_enabled]"', $edit);
            $this->assertStringContainsString('id="edit_duration_wrap"', $edit);
            $this->assertStringContainsString('id="edit_duration_days"', $edit);
            $this->assertStringContainsString('name="edit[duration_days]"', $edit);
            $this->assertStringContainsString('Срок действия (дни) *', $edit);
            $this->assertStringContainsString('id="edit_auto_attendance_section"', $edit);
            $this->assertStringContainsString('id="edit_auto_attendance_enabled"', $edit);
            $this->assertStringContainsString('name="edit[auto_attendance_enabled]"', $edit);
            $this->assertStringContainsString('data-error-for="edit[auto_attendance_enabled]"', $edit);
            $this->assertDoesNotMatchRegularExpression(
                '/id="edit_auto_attendance_enabled"[^>]*\bchecked\b/',
                $edit,
                'Статический HTML edit не должен зашивать checked — значение ставит JS из show JSON.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/id="edit_auto_attendance_enabled"[^>]*\bdisabled\b/',
                $edit
            );
        }
    }

    public function test_both_modals_hide_auto_attendance_checkbox_without_schedule_slots_view(): void
    {
        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            $create = $this->modalHtml($html, 'lessonPackageCreateForm', 'id="lessonPackageEditModal"');
            $edit = $this->modalHtml($html, 'lessonPackageEditForm', 'id="lessonPackageDeleteModal"');

            $this->assertStringNotContainsString('id="create_auto_attendance_enabled"', $create);
            $this->assertStringNotContainsString('name="create[auto_attendance_enabled]"', $create);
            $this->assertStringNotContainsString('id="edit_auto_attendance_enabled"', $edit);
            $this->assertStringNotContainsString('name="edit[auto_attendance_enabled]"', $edit);
            $this->assertStringNotContainsString('id="create_freeze_enabled"', $create);
            $this->assertStringNotContainsString('name="create[freeze_enabled]"', $create);
            $this->assertStringNotContainsString('id="edit_freeze_enabled"', $edit);
            $this->assertStringNotContainsString('name="edit[freeze_enabled]"', $edit);
            $this->assertStringNotContainsString('id="create_duration_days"', $create);
            $this->assertStringNotContainsString('name="create[duration_days]"', $create);
            $this->assertStringNotContainsString('id="edit_duration_days"', $edit);
            $this->assertStringNotContainsString('name="edit[duration_days]"', $edit);
        }
    }
}
