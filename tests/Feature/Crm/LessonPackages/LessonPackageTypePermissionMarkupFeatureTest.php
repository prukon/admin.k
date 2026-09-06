<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка type.*: кнопка «Добавить», option/@can, фильтры, чекбоксы уведомлений,
 * JS-inject текущего типа в edit (значение не теряется).
 *
 * @see LessonPackageTypePermissionAccessFeatureTest
 */
final class LessonPackageTypePermissionMarkupFeatureTest extends CrmTestCase
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

    private function selectHtml(string $pageHtml, string $selectId): string
    {
        $matched = preg_match(
            '/<select[^>]*\bid="'.preg_quote($selectId, '/').'"[^>]*>(.*?)<\/select>/is',
            $pageHtml,
            $matches
        );
        $this->assertSame(1, $matched, "Не найден select #{$selectId}");

        return (string) ($matches[1] ?? '');
    }

    public function test_both_packages_pages_hide_add_button_and_type_options_without_any_type_permission(): void
    {
        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            $this->assertStringNotContainsString('title="Добавить абонемент"', $html);

            foreach (['filter-lesson-package-type', 'create_schedule_type', 'edit_schedule_type'] as $selectId) {
                $select = $this->selectHtml($html, $selectId);
                $this->assertStringNotContainsString('value="fixed"', $select, "#{$selectId} {$url}");
                $this->assertStringNotContainsString('value="flexible"', $select, "#{$selectId} {$url}");
                $this->assertStringNotContainsString('value="no_schedule"', $select, "#{$selectId} {$url}");
                $this->assertStringNotContainsString('value="postpay"', $select, "#{$selectId} {$url}");
            }

            $filter = $this->selectHtml($html, 'filter-lesson-package-type');
            $this->assertStringContainsString('value="">Все типы</option>', $filter);
        }
    }

    public function test_both_packages_pages_show_add_button_and_only_granted_type_on_first_open(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['flexible']);

        foreach ($this->packagesPageUrls() as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('title="Добавить абонемент"', $html);
            $this->assertStringContainsString('data-bs-target="#lessonPackageCreateModal"', $html);

            foreach (['filter-lesson-package-type', 'create_schedule_type', 'edit_schedule_type'] as $selectId) {
                $select = $this->selectHtml($html, $selectId);
                $this->assertMatchesRegularExpression(
                    '/<option value="flexible"[^>]*>\s*Предоплата\s*<\/option>/u',
                    $select,
                    "#{$selectId}"
                );
                $this->assertStringNotContainsString('value="fixed"', $select, "#{$selectId}: без права fixed option быть не должно");
                $this->assertStringNotContainsString('value="no_schedule"', $select, "#{$selectId}");
                $this->assertStringNotContainsString('value="postpay"', $select, "#{$selectId}");
            }

            $create = $this->selectHtml($html, 'create_schedule_type');
            $this->assertDoesNotMatchRegularExpression(
                '/<option value="flexible"[^>]*\bselected\b/u',
                $create,
                'HTML create не навязывает selected на «Предоплата»; дефолт выставляет JS.'
            );
        }
    }

    public function test_edit_js_injects_missing_type_option_from_labels_so_value_is_not_lost(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $path = resource_path('views/admin/lessonPackages/tabs/packages.blade.php');
        $source = (string) file_get_contents($path);
        $this->assertStringContainsString('userCanSelectAny', $source);
        $this->assertStringContainsString('@can($typeOption[\'permission\'])', $source);
        $this->assertStringContainsString('LessonPackageTypePermission::LABELS', $source);

        $html = (string) $this->get(route('admin.lesson-packages.index'))->assertOk()->getContent();

        $this->assertStringContainsString("const scheduleType = lp.schedule_type || 'fixed'", $html);
        $this->assertStringContainsString('const scheduleTypeLabels =', $html);
        $this->assertStringContainsString(
            'if (scheduleSelect && scheduleType && !scheduleSelect.querySelector(\'option[value="\' + scheduleType + \'"]\'))',
            $html
        );
        $this->assertStringContainsString('opt.value = scheduleType', $html);
        $this->assertStringContainsString('scheduleTypeLabels[scheduleType] || scheduleType', $html);
        $this->assertStringContainsString('scheduleSelect.appendChild(opt)', $html);
        $this->assertStringContainsString('scheduleSelect.value = scheduleType', $html);
        $this->assertStringNotContainsString('scheduleSelect.value = lp.schedule_type_label', $html);
        $this->assertStringContainsString("createScheduleType.value = 'fixed'", $html);
        $this->assertStringContainsString("addEventListener('shown.bs.modal'", $html);
        $this->assertStringContainsString('lesson-package-edit-btn', $html);
    }

    public function test_create_and_edit_ajax_map_schedule_type_field_errors_under_the_select(): void
    {
        $html = (string) $this->get(route('admin.directories.lesson-packages.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-error-for="create[schedule_type]"', $html);
        $this->assertStringContainsString('data-error-for="edit[schedule_type]"', $html);
        $this->assertStringContainsString('function applyValidationErrors(modalEl, errors, prefix)', $html);
        $this->assertStringContainsString("const inputName = prefix + '[' + k + ']'", $html);
        $this->assertStringContainsString('applyValidationErrors(createModalEl, p.errors, \'create\')', $html);
        $this->assertStringContainsString("e.preventDefault()", $html);
        $this->assertStringContainsString("Accept': 'application/json'", $html);
    }

    public function test_assignments_filter_shows_only_granted_non_postpay_types(): void
    {
        $this->grantPermission('setPrices.packageAssignments.view');
        $this->grantLessonPackageTypePermissions($this->user, ['no_schedule', 'postpay']);

        $html = (string) $this->get(route('admin.lesson-packages.assignments'))->assertOk()->getContent();
        $this->assertNotSame('', trim($html));

        $select = $this->selectHtml($html, 'ulp-filter-schedule-type');
        $this->assertStringContainsString('value="">Все типы</option>', $select);
        $this->assertMatchesRegularExpression(
            '/<option value="no_schedule"[^>]*>\s*Разовое занятие\s*<\/option>/u',
            $select
        );
        $this->assertStringNotContainsString('value="fixed"', $select);
        $this->assertStringNotContainsString('value="flexible"', $select);
        $this->assertStringNotContainsString('value="postpay"', $select);
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="no_schedule"[^>]*\bselected\b/u',
            $select
        );
    }

    public function test_payment_notifications_checkboxes_match_granted_allowed_types_on_first_open(): void
    {
        $this->grantPermission('setPrices.view');
        $this->grantPermission('setPrices.paymentNotifications.manage');
        $this->grantLessonPackageTypePermissions($this->user, ['postpay', 'no_schedule']);

        $html = (string) $this->get(route('admin.settingPrices.paymentNotifications'))->assertOk()->getContent();
        $this->assertNotSame('', trim($html));

        $this->assertStringContainsString('id="pn-type-postpay"', $html);
        $this->assertStringContainsString('for="pn-type-postpay">Постоплата</label>', $html);
        $this->assertStringNotContainsString('id="pn-type-fixed"', $html);
        $this->assertStringNotContainsString('id="pn-type-flexible"', $html);
        $this->assertStringNotContainsString('id="pn-type-no_schedule"', $html);
        $this->assertStringContainsString('function ensureScheduleTypeCheckbox(type)', $html);
        $this->assertStringContainsString('function openEdit(id)', $html);
        $this->assertStringContainsString('setScheduleTypes(rule.schedule_types || [])', $html);
        $this->assertStringContainsString("setScheduleTypes(['fixed', 'flexible'])", $html);
    }
}
