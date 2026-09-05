<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка модалок: поле «Срок действия (дни)», дефолт 30, @can(scheduleSlots.view).
 *
 * @see LessonPackageDurationAccessFeatureTest
 * @see docs/documentation/lesson-packages.html
 */
final class LessonPackageDurationMarkupFeatureTest extends CrmTestCase
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

    public function test_create_modal_shows_duration_field_with_default_30_when_manager_has_schedule_slots_view(): void
    {
        $this->grantPermission('scheduleSlots.view');

        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            $create = $this->modalHtml($html, 'lessonPackageCreateForm', 'id="lessonPackageEditModal"');

            $this->assertStringContainsString('id="create_duration_wrap"', $create);
            $this->assertStringContainsString('id="create_duration_days"', $create);
            $this->assertStringContainsString('name="create[duration_days]"', $create);
            $this->assertStringContainsString('Срок действия (дни) *', $create);
            $this->assertStringContainsString('data-error-for="create[duration_days]"', $create);
            $this->assertMatchesRegularExpression(
                '/id="create_duration_days"[^>]*\bvalue="30"/',
                $create,
                'При первом открытии create срок должен быть 30.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/id="create_duration_days"[^>]*\breadonly\b/',
                $create,
                'Для fixed/flexible срок не readonly в статическом HTML.'
            );
            $this->assertLessThan(
                (int) strpos($create, 'id="create_duration_wrap"'),
                (int) strpos($create, 'id="create_schedule_type"'),
                'Срок в create должен идти после типа.'
            );
            $this->assertLessThan(
                (int) strpos($create, 'id="create_lessons_wrap"'),
                (int) strpos($create, 'id="create_duration_wrap"'),
                'Срок в create должен идти до поля «Занятий».'
            );
        }
    }

    public function test_edit_modal_shows_empty_duration_input_for_js_to_fill_from_show_payload(): void
    {
        $this->grantPermission('scheduleSlots.view');

        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();

            $edit = $this->modalHtml($html, 'lessonPackageEditForm', 'id="lessonPackageDeleteModal"');

            $this->assertStringContainsString('id="edit_duration_wrap"', $edit);
            $this->assertStringContainsString('id="edit_duration_days"', $edit);
            $this->assertStringContainsString('name="edit[duration_days]"', $edit);
            $this->assertStringContainsString('Срок действия (дни) *', $edit);
            $this->assertStringContainsString('data-error-for="edit[duration_days]"', $edit);
            $this->assertDoesNotMatchRegularExpression(
                '/id="edit_duration_days"[^>]*\bvalue="/',
                $edit,
                'Статический HTML edit не должен зашивать срок — значение ставит JS из show JSON.'
            );
        }
    }

    public function test_both_modals_hide_duration_inputs_without_schedule_slots_view_but_keep_lessons_and_table_column(): void
    {
        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            $create = $this->modalHtml($html, 'lessonPackageCreateForm', 'id="lessonPackageEditModal"');
            $edit = $this->modalHtml($html, 'lessonPackageEditForm', 'id="lessonPackageDeleteModal"');

            $this->assertStringNotContainsString('id="create_duration_days"', $create);
            $this->assertStringNotContainsString('name="create[duration_days]"', $create);
            $this->assertStringNotContainsString('id="edit_duration_days"', $edit);
            $this->assertStringNotContainsString('name="edit[duration_days]"', $edit);
            $this->assertStringContainsString('id="create_lessons_count"', $create);
            $this->assertStringContainsString('id="edit_lessons_count"', $edit);
            $this->assertStringContainsString('id="colLessonPackageDuration"', $html);
            $this->assertStringContainsString('data-column-key="duration_days"', $html);
        }
    }
}
