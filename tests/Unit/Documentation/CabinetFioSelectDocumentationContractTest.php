<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#cabinet-fio-select-index совпадает с кодом:
 * селект «ФИО» на /cabinet — только активные ученики withSystemRoleUser.
 */
final class CabinetFioSelectDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_cabinet_fio_select_students_only(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="cabinet-fio-select-index"', $html);
        $start = strpos($html, 'id="cabinet-fio-select-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-podpislon-signing-url-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/cabinet', $chunk);
        $this->assertStringContainsString('#single-select-user', $chunk);
        $this->assertStringContainsString('@can(\'users.view\')', $chunk);
        $this->assertStringContainsString('roles.name = user', $chunk);
        $this->assertStringContainsString('is_sistem = true', $chunk);
        $this->assertStringContainsString('User::withSystemRoleUser()', $chunk);
        $this->assertStringContainsString('users.is_enabled = 1', $chunk);
        $this->assertStringContainsString('cabinetSelectStudentsQuery', $chunk);
        $this->assertStringContainsString('$allUsersSelect', $chunk);
        $this->assertStringContainsString('GET /get-team-details', $chunk);
        $this->assertStringContainsString('teamName=all', $chunk);
        $this->assertStringContainsString('withoutTeam', $chunk);
        $this->assertStringContainsString('userWithoutTeam.concat(usersTeam)', $chunk);
        $this->assertStringContainsString('GET /get-user-details?userId=', $chunk);
        $this->assertStringContainsString('data-user-id', $chunk);
        $this->assertStringContainsString('{ success: false }', $chunk);
        $this->assertStringContainsString('dashboard.view', $chunk);
        $this->assertStringContainsString('не <code>users.view</code>', $chunk);
        $this->assertStringContainsString('#dashboard-active-team', $chunk);
        $this->assertStringContainsString('activeStudent', $chunk);
        $this->assertStringContainsString('просмотр чужого ученика (режим сотрудника)', $chunk);
        $this->assertStringContainsString('/get-user-details', $chunk);
        $this->assertStringContainsString('/get-team-details', $chunk);
        $this->assertStringContainsString('dashboard-cabinet#fio-select-students', $chunk);
        $this->assertStringContainsString('setting-prices-custom-payments#endpoints', $chunk);
        $this->assertStringContainsString('DashboardCabinetFioSelectAccessFeatureTest', $chunk);
        $this->assertStringContainsString('DashboardCabinetFioSelectAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('DashboardCabinetFioSelectNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('DashboardCabinetFioSelectFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('DashboardCabinetFioSelectUxFeatureTest', $chunk);
        $this->assertStringContainsString('DashboardAjaxDetailsTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('CabinetFioSelectDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#cabinet-fio-select-index', $chunk);

        $this->assertStringNotContainsString('can:users.view', $chunk);
        $this->assertStringNotContainsString('четвёртую подсказку', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $cabinet = $this->docFile('dashboard-cabinet.html');
        $membership = $this->docFile('student-team-membership.html');
        $custom = $this->docFile('setting-prices-custom-payments.html');
        $index = $this->docFile('index.html');

        $this->assertStringContainsString('/doc#cabinet-fio-select-index', $cabinet);
        $this->assertStringContainsString('id="fio-select-students"', $cabinet);
        $this->assertStringContainsString('cabinetSelectStudentsQuery', $cabinet);
        $this->assertStringContainsString('userWithoutTeam.concat(usersTeam)', $cabinet);
        $this->assertStringContainsString('data-user-id', $cabinet);
        $this->assertStringContainsString('.val(user.name)', $cabinet);
        $this->assertStringContainsString('JSON-ручки закрыты <code>dashboard.view</code>, не <code>users.view</code>', $cabinet);
        $this->assertStringContainsString('CabinetFioSelectDocumentationContractTest.php', $cabinet);
        $this->assertStringContainsString('тренер с выдачей', $cabinet);

        $this->assertStringContainsString('/doc#cabinet-fio-select-index', $membership);
        $this->assertStringContainsString('withSystemRoleUser', $membership);
        $this->assertStringContainsString('dashboard-cabinet#fio-select-students', $membership);

        $this->assertStringContainsString('/doc#cabinet-fio-select-index', $custom);
        $this->assertStringContainsString('User::withSystemRoleUser()', $custom);

        $this->assertStringContainsString('/doc#cabinet-fio-select-index', $index);
        $this->assertStringContainsString('href="/docs/documentation/dashboard-cabinet"', $index);
    }

    public function test_controller_title_mentions_fio_select_filter(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('селект ФИО (users.view) только активные ученики role=user (withSystemRoleUser)', $controller);
    }

    public function test_live_code_matches_documented_fio_select_filter(): void
    {
        $root = dirname(__DIR__, 3);

        $controller = (string) file_get_contents($root.'/app/Http/Controllers/DashboardController.php');
        $this->assertStringContainsString('function cabinetSelectStudentsQuery', $controller);
        $this->assertStringContainsString("->where('partner_id', \$partnerId)", $controller);
        $this->assertStringContainsString("->where('is_enabled', true)", $controller);
        $this->assertStringContainsString('->withSystemRoleUser()', $controller);
        $this->assertStringContainsString('studentsWithoutTeamsQuery', $controller);
        $this->assertStringContainsString('cabinetSelectStudentsQuery($partnerId)', $controller);

        $user = (string) file_get_contents($root.'/app/Models/User.php');
        $this->assertStringContainsString('function scopeWithSystemRoleUser', $user);
        $this->assertStringContainsString("where('name', 'user')->where('is_sistem', true)", $user);

        $blade = (string) file_get_contents($root.'/resources/views/dashboard.blade.php');
        $this->assertStringContainsString("@can('users.view')", $blade);
        $this->assertStringContainsString('id="single-select-user"', $blade);
        $this->assertStringContainsString('data-user-id="{{ $user->id }}"', $blade);
        $this->assertStringContainsString('userWithoutTeam.concat(usersTeam)', $blade);
        $this->assertStringContainsString(".val(user.name)", $blade);
        $this->assertStringContainsString("selectedOption.getAttribute('data-user-id')", $blade);
        $this->assertStringContainsString("url: '/get-user-details'", $blade);
        $this->assertStringContainsString("url: '/get-team-details'", $blade);
        $this->assertStringContainsString('cannot(\'users.view\')', $blade);
        $this->assertStringContainsString('dashboard_team_switcher', $blade);

        $routes = (string) file_get_contents($root.'/routes/web.php');
        $dashStart = strpos($routes, "Route::middleware(['can:dashboard.view'])->group(function () {");
        $this->assertNotFalse($dashStart);
        $dashChunk = substr($routes, $dashStart, 700);
        $this->assertStringContainsString("/get-user-details", $dashChunk);
        $this->assertStringContainsString("/get-team-details", $dashChunk);
        $this->assertStringContainsString("/cabinet", $dashChunk);
        $this->assertStringNotContainsString('users.view', $dashChunk);

        $hints = (string) file_get_contents($root.'/config/permission_capability_hints.php');
        $this->assertStringContainsString("'Пункт бокового меню «Консоль»'", $hints);
        $this->assertStringContainsString("'Страница /cabinet'", $hints);
        $this->assertStringContainsString("'Запросы /get-user-details и /get-team-details'", $hints);
        $this->assertStringContainsString("'На консоли: просмотр чужого ученика (режим сотрудника)'", $hints);
        $this->assertStringNotContainsString('withSystemRoleUser', $hints);
        $this->assertStringNotContainsString('селект «ФИО»', $hints);

        $customPayments = (string) file_get_contents($root.'/app/Http/Controllers/Admin/SettingPricesController.php');
        $this->assertStringContainsString('function customPaymentsUsersSearch', $customPayments);
        $searchStart = strpos($customPayments, 'function customPaymentsUsersSearch');
        $this->assertNotFalse($searchStart);
        $searchChunk = substr($customPayments, $searchStart, 900);
        $this->assertStringContainsString("->where('is_enabled', 1)", $searchChunk);
        $this->assertStringContainsString('->withSystemRoleUser()', $searchChunk);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
