<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#lesson-packages-type-permissions-index совпадает с каталогом type.*.
 */
final class LessonPackageTypePermissionsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_lesson_package_type_permissions(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="lesson-packages-type-permissions-index"', $html);
        $start = strpos($html, 'id="lesson-packages-type-permissions-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="school-schedule-permission-group-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('lessonPackages.type.fixed', $chunk);
        $this->assertStringContainsString('lessonPackages.type.flexible', $chunk);
        $this->assertStringContainsString('lessonPackages.type.no_schedule', $chunk);
        $this->assertStringContainsString('lessonPackages.type.postpay', $chunk);
        $this->assertStringContainsString('2026_09_06_040500_add_lesson_packages_type_permissions.php', $chunk);
        $this->assertStringContainsString('никому не выдаёт', $chunk);
        $this->assertStringContainsString('role_base_permissions.php', $chunk);
        $this->assertStringContainsString('422', $chunk);
        $this->assertStringContainsString('schedule_type', $chunk);
        $this->assertStringContainsString('Добавить абонемент', $chunk);
        $this->assertStringContainsString('userCanSelectAny', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('restrictQueryToAllowedTypes', $chunk);
        $this->assertStringContainsString('lesson_package_id', $chunk);
        $this->assertStringContainsString('usersPrice.{i}.lesson_package_id', $chunk);
        $this->assertStringContainsString('createScheduleType.value', $chunk);
        $this->assertStringContainsString('ensureScheduleTypeCheckbox', $chunk);
        $this->assertStringContainsString("setScheduleTypes(['fixed', 'flexible'])", $chunk);
        $this->assertStringContainsString('type_denied', $chunk);
        $this->assertStringContainsString('postpay_denied', $chunk);
        $this->assertStringContainsString('Уведомления об оплате', $chunk);
        $this->assertStringContainsString('schedule_types', $chunk);
        $this->assertStringContainsString('setPrices.cabinetPackages', $chunk);
        $this->assertStringContainsString('permission_capability_hints.php', $chunk);
        $this->assertStringContainsString('id="lesson-packages-type-postpay-index"', $html);
        $this->assertStringContainsString('LessonPackageTypePermissionAccessFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageTypePermissionAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageTypePermissionNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageTypePermissionMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageTypePermissionWorkflowFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackagesTypePermissionsCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('lesson-packages-type-permissions', $chunk);
        $this->assertStringContainsString('/doc#lesson-packages-type-permissions-index', $html);
    }

    public function test_type_permissions_page_documents_catalog_and_surfaces(): void
    {
        $html = $this->docFile('lesson-packages-type-permissions.html');

        $this->assertStringContainsString('lessonPackages.type.fixed', $html);
        $this->assertStringContainsString('lessonPackages.type.flexible', $html);
        $this->assertStringContainsString('lessonPackages.type.no_schedule', $html);
        $this->assertStringContainsString('LessonPackageTypePermission', $html);
        $this->assertStringContainsString('is_visible', $html);
        $this->assertStringContainsString('sort_order', $html);
        $this->assertStringContainsString('Недостаточно прав для выбора типа', $html);
        $this->assertStringContainsString('userCanSelectAny', $html);
        $this->assertStringContainsString('StorePaymentNotificationRuleRequest', $html);
        $this->assertStringContainsString('restrictQueryToAllowedTypes', $html);
        $this->assertStringContainsString('assignments.blade.php', $html);
        $this->assertStringContainsString('usersPrice.{i}.lesson_package_id', $html);
        $this->assertStringContainsString('ensureScheduleTypeCheckbox', $html);
        $this->assertStringContainsString('Gate::before', $html);
        $this->assertStringContainsString('denyPackageMessage', $html);
        $this->assertStringContainsString('LessonPackageTypePermissionAccessFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageTypePermissionAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageTypePermissionNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageTypePermissionMarkupFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageTypePermissionWorkflowFeatureTest', $html);
        $this->assertStringContainsString('test_lesson_packages_type_permissions_inline_script_contracts', $html);
        $this->assertStringContainsString('/doc#lesson-packages-type-permissions-index', $html);
        $this->assertStringContainsString('lesson-packages-type-postpay', $html);
    }

    public function test_related_docs_point_to_type_permissions(): void
    {
        $groups = $this->docFile('settings-permission-groups.html');
        $this->assertStringContainsString('lessonPackages.type.fixed', $groups);
        $this->assertStringContainsString('2026_09_06_040500_add_lesson_packages_type_permissions.php', $groups);
        $this->assertStringContainsString('/doc#lesson-packages-type-permissions-index', $groups);

        $partners = $this->docFile('partners-permissions.html');
        $this->assertStringContainsString('lessonPackages.type.fixed', $partners);
        $this->assertStringContainsString('test_new_partner_does_not_assign_lesson_packages_type_permissions_to_base_roles', $partners);

        $packages = $this->docFile('lesson-packages.html');
        $this->assertStringContainsString('lesson-packages-type-permissions', $packages);
        $this->assertStringContainsString('/doc#lesson-packages-type-permissions-index', $packages);
        $this->assertStringContainsString('соответствующем <code>lessonPackages.type.*</code>', $packages);
        $this->assertStringContainsString('restrictQueryToAllowedTypes', $packages);
        $this->assertStringContainsString('userCanSelectAny', $packages);

        $notify = $this->docFile('setting-prices-payment-notifications.html');
        $this->assertStringContainsString('lessonPackages.type.fixed', $notify);
        $this->assertStringContainsString('/doc#lesson-packages-type-permissions-index', $notify);
        $this->assertStringContainsString('ensureScheduleTypeCheckbox', $notify);

        $prices = $this->docFile('setting-prices-monthly-users.html');
        $this->assertStringContainsString('lessonPackages.type.fixed', $prices);
        $this->assertStringContainsString('type_denied', $prices);
        $this->assertStringContainsString('/doc#lesson-packages-type-permissions-index', $prices);

        $assignments = $this->docFile('set-prices-package-assignments.html');
        $this->assertStringContainsString('lessonPackages.type.fixed', $assignments);
        $this->assertStringContainsString('/doc#lesson-packages-type-permissions-index', $assignments);

        $postpay = $this->docFile('postpay.html');
        $this->assertStringContainsString('lesson-packages-type-permissions', $postpay);
        $this->assertStringContainsString('lessonPackages.type.*', $postpay);

        $postpayCard = $this->docFile('lesson-packages-type-postpay.html');
        $this->assertStringContainsString('/doc#lesson-packages-type-permissions-index', $postpayCard);
        $this->assertStringContainsString('sort_order: 39', $postpayCard);
    }

    public function test_documentation_controller_lists_type_permissions_page(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'lesson-packages-type-permissions'", $controller);
        $this->assertStringContainsString('lessonPackages.type', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
