<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-lesson-package-bind-index совпадает с правом, снимком и плейсхолдерами.
 */
final class ContractLessonPackageBindDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_contract_lesson_package_bind(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-lesson-package-bind-index"', $html);
        $start = strpos($html, 'id="contract-lesson-package-bind-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="lesson-package-contract-fields-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('contracts.lessonPackage.bind', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('2026_09_19_220000_add_contracts_lesson_package_bind_permission.php', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('lesson_packages', $chunk);
        $this->assertStringContainsString('package_snapshot', $chunk);
        $this->assertStringContainsString('lesson_package_id', $chunk);
        $this->assertStringContainsString('(установлен)', $chunk);
        $this->assertStringContainsString('user-packages', $chunk);
        $this->assertStringContainsString('ContractLessonPackageBinder', $chunk);
        $this->assertStringContainsString('package_price', $chunk);
        $this->assertStringContainsString('руб.', $chunk);
        $this->assertStringContainsString('ContractLessonPackageBindFeatureTest', $chunk);
        $this->assertStringContainsString('ContractLessonPackageBindUiFeatureTest', $chunk);
        $this->assertStringContainsString('ContractsLessonPackageBindPermissionCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('contracts#contract-lesson-package-bind', $chunk);
        $this->assertStringContainsString('contract-templates#package-variables', $chunk);
        $this->assertStringContainsString('lesson-packages#contract-fields', $chunk);
        $this->assertStringContainsString('/doc#contract-lesson-package-bind-index', $html);
    }

    public function test_pages_and_live_code_match_bind_permission(): void
    {
        $contracts = $this->docFile('contracts.html');
        $this->assertStringContainsString('id="contract-lesson-package-bind"', $contracts);
        $this->assertStringContainsString('contracts.lessonPackage.bind', $contracts);
        $this->assertStringContainsString('/client-contracts/user-packages', $contracts);
        $this->assertStringContainsString('package_snapshot', $contracts);
        $this->assertStringContainsString('ContractLessonPackageBindFeatureTest', $contracts);

        $templates = $this->docFile('contract-templates.html');
        $this->assertStringContainsString('id="package-variables"', $templates);
        $this->assertStringContainsString('{{package_name}}', $templates);
        $this->assertStringContainsString('{{package_price}}', $templates);
        $this->assertStringContainsString('{{package_lesson_price}}', $templates);

        $packages = $this->docFile('lesson-packages.html');
        $this->assertStringContainsString('contracts.package_snapshot', $packages);
        $this->assertStringContainsString('contract-lesson-package-bind-index', $packages);

        $partners = $this->docFile('partners-permissions.html');
        $this->assertStringContainsString('contracts.lessonPackage.bind', $partners);

        $groups = $this->docFile('settings-permission-groups.html');
        $this->assertStringContainsString('contracts.lessonPackage.bind', $groups);
        $this->assertStringContainsString('2026_09_19_220000_add_contracts_lesson_package_bind_permission.php', $groups);

        $leads = $this->docFile('school-leads-widget.html');
        $this->assertStringContainsString('lesson_package_id', $leads);
        $this->assertStringContainsString('contracts.lessonPackage.bind', $leads);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('contracts.lessonPackage.bind', $controller);

        $seeder = (string) file_get_contents(dirname(__DIR__, 3).'/database/seeders/PermissionSeeder.php');
        $this->assertStringContainsString(
            "'name' => 'contracts.lessonPackage.bind',   'description' => 'Договоры: выбор абонемента при создании'",
            $seeder
        );
        $this->assertMatchesRegularExpression(
            "/'name' => 'contracts\\.lessonPackage\\.bind'.{0,160}'is_visible' => 0/s",
            $seeder
        );

        $auth = (string) file_get_contents(dirname(__DIR__, 3).'/app/Providers/AuthServiceProvider.php');
        $this->assertStringContainsString("Gate::define('contracts.lessonPackage.bind'", $auth);

        $baseRoles = (string) file_get_contents(dirname(__DIR__, 3).'/config/role_base_permissions.php');
        $this->assertStringNotContainsString("'contracts.lessonPackage.bind'", $baseRoles);
        $this->assertStringNotContainsString('contracts.lessonPackage.bind', $baseRoles);

        $binder = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Contracts/ContractLessonPackageBinder.php');
        $this->assertStringContainsString("ASSIGNED_SUFFIX = ' (установлен)'", $binder);
        $this->assertStringContainsString("Money::formatRub(\$cents).' руб.'", $binder);

        $modal = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/contracts/partials/create-modal.blade.php');
        $this->assertStringContainsString("can('contracts.lessonPackage.bind')", $modal);
        $this->assertStringContainsString('id="lesson_package_id"', $modal);

        $show = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/contracts/show.blade.php');
        $this->assertStringContainsString("can('contracts.lessonPackage.bind')", $show);
        $this->assertStringContainsString('packageSnapshotName()', $show);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
