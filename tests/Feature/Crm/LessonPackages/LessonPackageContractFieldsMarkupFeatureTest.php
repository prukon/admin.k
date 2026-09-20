<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка модалок и колонок: поля «Для договора» на шаблоне абонемента.
 *
 * @see LessonPackageContractFieldsAjaxContractFeatureTest
 * @see docs/documentation/lesson-packages.html
 */
final class LessonPackageContractFieldsMarkupFeatureTest extends CrmTestCase
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

    public function test_create_and_edit_modals_show_optional_contract_fields_on_both_screens(): void
    {
        $this->grantPermission('contracts.lessonPackage.bind');

        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            $create = $this->modalHtml($html, 'lessonPackageCreateForm', 'id="lessonPackageEditModal"');
            $this->assertStringContainsString('id="create_contract_fields_section"', $create);
            $this->assertStringContainsString('Для договора', $create);
            $this->assertStringContainsString('id="create_lessons_per_week"', $create);
            $this->assertStringContainsString('name="create[lessons_per_week]"', $create);
            $this->assertStringContainsString('data-error-for="create[lessons_per_week]"', $create);
            $this->assertStringNotContainsString('create_lessons_per_month', $create);
            $this->assertStringNotContainsString('name="create[lessons_per_month]"', $create);
            $this->assertStringContainsString('id="create_lesson_duration_minutes"', $create);
            $this->assertStringContainsString('name="create[lesson_duration_minutes]"', $create);
            $this->assertStringContainsString('data-error-for="create[lesson_duration_minutes]"', $create);
            $this->assertStringContainsString('id="create_lesson_price"', $create);
            $this->assertStringContainsString('name="create[lesson_price]"', $create);
            $this->assertStringContainsString('data-error-for="create[lesson_price]"', $create);
            $this->assertStringNotContainsString('name="create[lessons_per_week]" required', $create);
            $this->assertStringNotContainsString('name="create[lesson_price]" required', $create);
            $this->assertLessThan(
                (int) strpos($create, 'id="create_contract_fields_section"'),
                (int) strpos($create, 'id="create_price_label"'),
                'Блок «Для договора» в create должен идти после стоимости абонемента.'
            );

            $edit = $this->modalHtml($html, 'lessonPackageEditForm', 'id="lessonPackageDeleteModal"');
            $this->assertStringContainsString('id="edit_contract_fields_section"', $edit);
            $this->assertStringContainsString('Для договора', $edit);
            $this->assertStringContainsString('id="edit_lessons_per_week"', $edit);
            $this->assertStringContainsString('name="edit[lessons_per_week]"', $edit);
            $this->assertStringContainsString('data-error-for="edit[lessons_per_week]"', $edit);
            $this->assertStringNotContainsString('edit_lessons_per_month', $edit);
            $this->assertStringNotContainsString('name="edit[lessons_per_month]"', $edit);
            $this->assertStringContainsString('id="edit_lesson_duration_minutes"', $edit);
            $this->assertStringContainsString('name="edit[lesson_duration_minutes]"', $edit);
            $this->assertStringContainsString('data-error-for="edit[lesson_duration_minutes]"', $edit);
            $this->assertStringContainsString('id="edit_lesson_price"', $edit);
            $this->assertStringContainsString('name="edit[lesson_price]"', $edit);
            $this->assertStringContainsString('data-error-for="edit[lesson_price]"', $edit);
            $this->assertDoesNotMatchRegularExpression(
                '/name="edit\[lessons_per_week\]"[^>]*\bvalue="/',
                $edit,
                'Статический HTML edit не должен зашивать занятия в неделю — значение ставит JS из show JSON.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/name="edit\[lesson_price\]"[^>]*\bvalue="/',
                $edit,
                'Статический HTML edit не должен зашивать стоимость занятия — JS ставит из show JSON.'
            );

            $this->assertStringContainsString('id="colLessonPackageLessonsPerWeek"', $html);
            $this->assertStringContainsString('data-column-key="lessons_per_week"', $html);
            $this->assertStringNotContainsString('id="colLessonPackageLessonsPerMonth"', $html);
            $this->assertStringNotContainsString('data-column-key="lessons_per_month"', $html);
            $this->assertStringContainsString('id="colLessonPackageLessonDuration"', $html);
            $this->assertStringContainsString('data-column-key="lesson_duration_minutes"', $html);
            $this->assertStringContainsString('id="colLessonPackageLessonPrice"', $html);
            $this->assertStringContainsString('data-column-key="lesson_price_label"', $html);
            $this->assertStringContainsString('>Занятий в неделю<', $html);
            $this->assertStringNotContainsString('>Занятий в месяц<', $html);
            $this->assertStringContainsString('>Длительность (мин)<', $html);
            $this->assertStringContainsString('>Стоимость занятия<', $html);
        }
    }

    public function test_both_modals_hide_contract_fields_and_table_columns_without_bind_permission(): void
    {
        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            $create = $this->modalHtml($html, 'lessonPackageCreateForm', 'id="lessonPackageEditModal"');
            $edit = $this->modalHtml($html, 'lessonPackageEditForm', 'id="lessonPackageDeleteModal"');

            $this->assertStringNotContainsString('id="create_contract_fields_section"', $create);
            $this->assertStringNotContainsString('name="create[lessons_per_week]"', $create);
            $this->assertStringNotContainsString('name="create[lesson_price]"', $create);
            $this->assertStringNotContainsString('id="edit_contract_fields_section"', $edit);
            $this->assertStringNotContainsString('name="edit[lessons_per_week]"', $edit);
            $this->assertStringNotContainsString('name="edit[lesson_price]"', $edit);
            $this->assertStringContainsString('id="create_lessons_count"', $create);
            $this->assertStringNotContainsString('id="colLessonPackageLessonsPerWeek"', $html);
            $this->assertStringNotContainsString('data-column-key="lesson_price_label"', $html);
            $this->assertStringNotContainsString('>Занятий в неделю<', $html);
            $this->assertStringNotContainsString('>Стоимость занятия<', $html);
        }
    }
}
