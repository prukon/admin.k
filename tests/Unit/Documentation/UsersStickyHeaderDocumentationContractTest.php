<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#users-sticky-header-index совпадает с вкладкой «Клиенты».
 */
final class UsersStickyHeaderDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_users_sticky_header_and_hscroll(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="users-sticky-header-index"', $html);
        $start = strpos($html, 'id="users-sticky-header-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="account-documents-family-link-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/users', $chunk);
        $this->assertStringContainsString('KidsCrmDataTable.create(\'#users-table\')', $chunk);
        $this->assertStringContainsString('header: true', $chunk);
        $this->assertStringContainsString('footer: false', $chunk);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $chunk);
        $this->assertStringContainsString('kids-dt-scroll-x', $chunk);
        $this->assertStringContainsString('datatables-fixedheader', $chunk);
        $this->assertStringContainsString('<code>scrollY</code>', $chunk);
        $this->assertStringContainsString('это не', $chunk);
        $this->assertStringContainsString('scrollLeft', $chunk);
        $this->assertStringContainsString('не через', $chunk);
        $this->assertStringContainsString('margin-left', $chunk);
        $this->assertStringContainsString('admin-users-table.css', $chunk);
        $this->assertStringContainsString('Vite', $chunk);
        $this->assertStringContainsString('resources/css/admin-users-table.css', $chunk);
        $this->assertStringContainsString('admin-users#users-sticky-header', $chunk);
        $this->assertStringContainsString('AdminUsersPageFeatureTest', $chunk);
        $this->assertStringContainsString('UsersSectionTabsFeatureTest', $chunk);
        $this->assertStringContainsString('UsersStickyHeaderDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#users-sticky-header-index', $html);
    }

    public function test_related_docs_and_blade_match_sticky_header_contract(): void
    {
        $users = $this->docFile('admin-users.html');
        $ui = $this->docFile('reusable-ui-partials.html');
        $section = $this->docFile('admin-users-section.html');
        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/user.blade.php');
        $css = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/admin-users-table.css');
        $userCss = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/user.css');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="users-sticky-header"', $users);
        $this->assertStringContainsString('/doc#users-sticky-header-index', $users);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $users);
        $this->assertStringContainsString('header: true, footer: false', $users);
        $this->assertStringContainsString('bindUsersStickyHScroll', $users);
        $this->assertStringContainsString('getBoundingClientRect', $users);
        $this->assertStringContainsString('overflow: hidden', $users);
        $this->assertStringContainsString('scrollLeft', $users);
        $this->assertStringContainsString('не <code>margin-left</code>', $users);
        $this->assertStringContainsString('box-sizing: border-box', $users);
        $this->assertStringContainsString('resources/css/admin-users-table.css', $users);
        $this->assertStringContainsString('vite.config.js', $users);
        $this->assertStringContainsString('@vite', $users);
        $this->assertStringNotContainsString('public/css/admin-users-table.css', $users);

        $this->assertStringContainsString('admin-users#users-sticky-header', $ui);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $ui);
        $this->assertStringContainsString('admin-users-table.css', $ui);
        $this->assertStringNotContainsString('public/css/admin-users-table.css', $ui);

        $this->assertStringContainsString('users-sticky-header', $section);
        $this->assertStringContainsString('/doc#users-sticky-header-index', $section);

        $this->assertStringContainsString('dataTables.fixedHeader.min.js', $blade);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $blade);
        $this->assertStringContainsString('header: true', $blade);
        $this->assertStringContainsString('footer: false', $blade);
        $this->assertStringContainsString('bindUsersStickyHScroll', $blade);
        $this->assertStringContainsString('dtfh-floatingparenthead', $blade);
        $this->assertStringContainsString('getBoundingClientRect()', $blade);
        $this->assertStringContainsString("setProperty('overflow'", $blade);
        $this->assertStringContainsString('parent.scrollLeft', $blade);
        $this->assertStringContainsString('matchUsersFloatingHeaderWidths', $blade);
        $this->assertStringContainsString('boxSizing', $blade);
        $this->assertStringContainsString('maxWidth', $blade);
        $this->assertStringNotContainsString("margin-left', (-", $blade);
        $this->assertStringNotContainsString('scrollY:', $blade);

        $this->assertStringContainsString('.dtfh-floatingparenthead', $css);
        $this->assertStringContainsString('overflow: hidden', $css);
        $this->assertStringContainsString("@vite(['resources/css/admin-list-toolbar.css', 'resources/css/user.css', 'resources/css/admin-users-table.css'])", $blade);
        $this->assertStringNotContainsString("asset('css/admin-users-table.css')", $blade);
        $this->assertStringNotContainsString("@import './admin-users-table.css'", $userCss);
        $this->assertFileDoesNotExist(dirname(__DIR__, 3).'/public/css/admin-users-table.css');
        $this->assertStringContainsString('position: sticky', $css);
        $this->assertStringContainsString('overflow-x: scroll', $css);
        $vite = (string) file_get_contents(dirname(__DIR__, 3).'/vite.config.js');
        $this->assertStringContainsString("'resources/css/admin-users-table.css'", $vite);

        $this->assertStringContainsString('закрепление thead и горизонтального скролла', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
