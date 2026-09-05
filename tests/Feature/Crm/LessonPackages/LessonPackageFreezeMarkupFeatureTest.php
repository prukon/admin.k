<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка модалок: чекбокс «Разрешена заморозка», дефолт дней 7, @can(scheduleSlots.view).
 *
 * @see LessonPackageFreezeAccessFeatureTest
 * @see docs/documentation/lesson-packages.html
 */
final class LessonPackageFreezeMarkupFeatureTest extends CrmTestCase
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

    public function test_create_modal_shows_unchecked_freeze_with_default_seven_days_when_manager_has_schedule_slots_view(): void
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
            $this->assertStringContainsString('data-error-for="create[freeze_enabled]"', $create);
            $this->assertStringContainsString('id="create_freeze_days_wrap"', $create);
            $this->assertStringContainsString('id="create_freeze_days"', $create);
            $this->assertStringContainsString('name="create[freeze_days]"', $create);
            $this->assertStringContainsString('Дней заморозки', $create);
            $this->assertStringContainsString('data-error-for="create[freeze_days]"', $create);
            $this->assertDoesNotMatchRegularExpression(
                '/id="create_freeze_enabled"[^>]*\bchecked\b/',
                $create,
                'При первом открытии create чекбокс заморозки должен быть выключен.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/id="create_freeze_enabled"[^>]*\bdisabled\b/',
                $create
            );
            $this->assertMatchesRegularExpression(
                '/id="create_freeze_days_wrap"[^>]*\bd-none\b/',
                $create,
                'Поле дней в create скрыто, пока чекбокс не включён.'
            );
            $this->assertMatchesRegularExpression(
                '/id="create_freeze_days"[^>]*\bvalue="7"/',
                $create,
                'Дефолт дней заморозки в create — 7.'
            );
            $this->assertLessThan(
                (int) strpos($create, 'id="create_auto_attendance_section"'),
                (int) strpos($create, 'id="create_freeze_section"'),
                'Заморозка в create должна идти до автосписания.'
            );
        }
    }

    public function test_edit_modal_shows_empty_freeze_fields_for_js_to_fill_from_show_payload(): void
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
            $this->assertStringContainsString('data-error-for="edit[freeze_enabled]"', $edit);
            $this->assertStringContainsString('id="edit_freeze_days_wrap"', $edit);
            $this->assertStringContainsString('name="edit[freeze_days]"', $edit);
            $this->assertStringContainsString('data-error-for="edit[freeze_days]"', $edit);
            $this->assertDoesNotMatchRegularExpression(
                '/id="edit_freeze_enabled"[^>]*\bchecked\b/',
                $edit,
                'Статический HTML edit не должен зашивать checked — значение ставит JS из show JSON.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/name="edit\[freeze_days\]"[^>]*\bvalue="/',
                $edit,
                'Статический HTML edit не должен зашивать дни — JS ставит freeze_days || 7.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/id="edit_freeze_enabled"[^>]*\bdisabled\b/',
                $edit
            );
        }
    }

    public function test_both_modals_hide_freeze_inputs_without_schedule_slots_view_but_keep_table_column(): void
    {
        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            $create = $this->modalHtml($html, 'lessonPackageCreateForm', 'id="lessonPackageEditModal"');
            $edit = $this->modalHtml($html, 'lessonPackageEditForm', 'id="lessonPackageDeleteModal"');

            $this->assertStringNotContainsString('id="create_freeze_enabled"', $create);
            $this->assertStringNotContainsString('name="create[freeze_enabled]"', $create);
            $this->assertStringNotContainsString('name="create[freeze_days]"', $create);
            $this->assertStringNotContainsString('id="edit_freeze_enabled"', $edit);
            $this->assertStringNotContainsString('name="edit[freeze_enabled]"', $edit);
            $this->assertStringNotContainsString('name="edit[freeze_days]"', $edit);
            $this->assertStringContainsString('id="create_lessons_count"', $create);
            $this->assertStringContainsString('id="colLessonPackageFreeze"', $html);
            $this->assertStringContainsString('data-column-key="freeze_label"', $html);
        }
    }
}
