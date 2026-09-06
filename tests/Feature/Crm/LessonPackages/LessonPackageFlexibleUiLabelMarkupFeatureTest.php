<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка: тип flexible в селектах и кнопках подписан «Предоплата», код value остаётся flexible.
 *
 * @see LessonPackageFlexibleUiLabelAccessFeatureTest
 * @see docs/documentation/lesson-packages.html
 */
final class LessonPackageFlexibleUiLabelMarkupFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);
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

    private function assertTypeSelectShowsPrepayNotFlexible(string $selectHtml, string $selectId): void
    {
        $this->assertMatchesRegularExpression(
            '/<option value="flexible"[^>]*>\s*Предоплата\s*<\/option>/u',
            $selectHtml,
            "#{$selectId}: value=flexible должен быть подписан «Предоплата»."
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option[^>]*>\s*Гибкий\s*<\/option>/u',
            $selectHtml,
            "#{$selectId}: в опциях типа не должно быть подписи «Гибкий»."
        );
        $this->assertMatchesRegularExpression(
            '/<option value="fixed"[^>]*>\s*Фиксированный\s*<\/option>/u',
            $selectHtml
        );
        $this->assertMatchesRegularExpression(
            '/<option value="no_schedule"[^>]*>\s*Разовое занятие\s*<\/option>/u',
            $selectHtml
        );
        $this->assertLessThan(
            (int) strpos($selectHtml, 'value="flexible"'),
            (int) strpos($selectHtml, 'value="fixed"'),
            "#{$selectId}: Фиксированный должен идти до Предоплаты."
        );
    }

    public function test_both_packages_pages_show_prepay_type_options_on_first_open(): void
    {
        foreach ($this->packagesPageUrls() as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html));

            foreach (['filter-lesson-package-type', 'create_schedule_type', 'edit_schedule_type'] as $selectId) {
                $this->assertTypeSelectShowsPrepayNotFlexible($this->selectHtml($html, $selectId), $selectId);
            }

            $create = $this->selectHtml($html, 'create_schedule_type');
            $this->assertDoesNotMatchRegularExpression(
                '/<option value="flexible"[^>]*\bselected\b/u',
                $create,
                'При первом открытии create не должен быть выбран тип «Предоплата».'
            );
            $filter = $this->selectHtml($html, 'filter-lesson-package-type');
            $this->assertStringContainsString('value="">Все типы</option>', $filter);
            $this->assertDoesNotMatchRegularExpression(
                '/<option value="flexible"[^>]*\bselected\b/u',
                $filter,
                'Фильтр по умолчанию — все типы, не «Предоплата».'
            );
            foreach (['filter-lesson-package-type', 'create_schedule_type', 'edit_schedule_type'] as $selectId) {
                $this->assertStringNotContainsString(
                    'value="postpay"',
                    $this->selectHtml($html, $selectId),
                    "#{$selectId}: без права lessonPackages.type.postpay опции Постоплата быть не должно."
                );
            }
        }
    }

    public function test_reopening_create_modal_resets_type_to_fixed_and_keeps_prepay_option_text(): void
    {
        $page = $this->get(route('admin.directories.lesson-packages.index'));
        $page->assertOk();
        $html = (string) $page->getContent();

        $this->assertStringContainsString("createScheduleType.value = 'fixed'", $html);
        $this->assertStringContainsString("const scheduleType = lp.schedule_type || 'fixed'", $html);
        $this->assertStringContainsString('scheduleSelect.value = scheduleType', $html);
        $this->assertStringNotContainsString("scheduleSelect.value = lp.schedule_type_label", $html);
        $this->assertStringNotContainsString("createScheduleType.value = 'flexible'", $html);
        $this->assertStringNotContainsString("createScheduleType.value = 'Предоплата'", $html);
        $this->assertStringContainsString("data: 'schedule_type_label'", $html);
        $this->assertStringContainsString('d.schedule_type = params.schedule_type', $html);
        $this->assertStringContainsString("$('#filter-lesson-package-type').val()", $html);
    }

    public function test_postpay_option_stays_postpay_and_is_not_relabeled_prepay_when_manager_has_type_permission(): void
    {
        $this->grantPermission('lessonPackages.type.postpay');

        $html = (string) $this->get(route('admin.lesson-packages.index'))->assertOk()->getContent();
        $create = $this->selectHtml($html, 'create_schedule_type');
        $this->assertMatchesRegularExpression(
            '/<option value="postpay">\s*Постоплата\s*<\/option>/u',
            $create
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="postpay">\s*Предоплата\s*<\/option>/u',
            $create
        );
    }

    public function test_assignments_filter_shows_prepay_for_flexible_code(): void
    {
        $this->grantPermission('setPrices.packageAssignments.view');

        $page = $this->get(route('admin.lesson-packages.assignments'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));

        $select = $this->selectHtml($html, 'ulp-filter-schedule-type');
        $this->assertTypeSelectShowsPrepayNotFlexible($select, 'ulp-filter-schedule-type');
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="flexible"[^>]*\bselected\b/u',
            $select
        );
    }

    public function test_payment_notifications_page_labels_flexible_checkbox_prepay(): void
    {
        $this->grantPermission('setPrices.view');
        $this->grantPermission('setPrices.paymentNotifications.manage');

        $page = $this->get(route('admin.settingPrices.paymentNotifications'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));

        $this->assertStringContainsString('id="pn-type-flexible"', $html);
        $this->assertMatchesRegularExpression(
            '/<(?:input)[^>]*(?:id="pn-type-flexible"[^>]*value="flexible"|value="flexible"[^>]*id="pn-type-flexible")/u',
            $html
        );
        $this->assertStringContainsString('<label class="form-check-label" for="pn-type-flexible">Предоплата</label>', $html);
        $this->assertStringNotContainsString('for="pn-type-flexible">Гибкий</label>', $html);
        $this->assertStringContainsString("setScheduleTypes(['fixed', 'flexible'])", $html);
        $this->assertStringContainsString("flexible: 'Предоплата'", $html);
        $this->assertStringNotContainsString("flexible: 'Гибкий'", $html);
        $this->assertStringContainsString("fixed: 'Фиксированный'", $html);
        $this->assertStringContainsString("postpay: 'Постоплата'", $html);
    }

    public function test_school_schedule_bind_button_shows_prepay_subscription_label(): void
    {
        $page = $this->get(route('admin.lesson-packages.school-schedule'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));

        $this->assertStringContainsString('id="schoolCalOpenFlexible"', $html);
        $this->assertStringContainsString('Привязать абонемент предоплаты', $html);
        $this->assertStringNotContainsString('Привязать гибкий абонемент', $html);
        $this->assertStringContainsString(
            "const schoolCalFlexibleButtonDefaultLabel = 'Привязать абонемент предоплаты'",
            $html
        );
        $this->assertStringContainsString('function applyFlexibleBindButtonState(flex)', $html);
        $this->assertStringContainsString('function resetSlotModalUserPicker()', $html);
        $this->assertStringContainsString('btn.textContent = schoolCalFlexibleButtonDefaultLabel', $html);
        $this->assertStringContainsString('flexBtn.textContent = schoolCalFlexibleButtonDefaultLabel', $html);
        $this->assertStringContainsString('Привязать фиксированный абонемент', $html);
    }

    public function test_journal_blade_fallbacks_use_prepay_wording_not_flexible_type_name(): void
    {
        $path = resource_path('views/admin/schedule/journal.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("'Абонемент предоплаты'", $content);
        $this->assertStringContainsString('Абонемент предоплаты: поставить занятие', $content);
        $this->assertStringNotContainsString("'Гибкий абонемент'", $content);
        $this->assertStringNotContainsString('Гибкий абонемент: поставить занятие', $content);
        $this->assertStringNotContainsString('>Гибкий</option>', $content);
    }
}
