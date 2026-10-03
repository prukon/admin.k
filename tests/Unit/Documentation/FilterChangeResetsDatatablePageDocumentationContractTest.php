<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#datatable-filter-resets-page-index совпадает с правилом:
 * смена фильтра открывает первую страницу, правка строки — нет.
 */
final class FilterChangeResetsDatatablePageDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_filter_change_resets_page(): void
    {
        $html = $this->docFile('index.html');
        $this->assertStringContainsString('id="datatable-filter-resets-page-index"', $html);

        $start = strpos($html, 'id="datatable-filter-resets-page-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, '<h2', $start + 10);
        $this->assertNotFalse($end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('dtApi.reload()', $chunk);
        $this->assertStringContainsString('без <code>keepPage</code>', $chunk);
        $this->assertStringContainsString('resetPage: true', $chunk);
        $this->assertStringContainsString('start = 0', $chunk);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true })', $chunk);
        $this->assertStringContainsString('ajax.reload(null, false)', $chunk);
        $this->assertStringContainsString('KidsCrmDataTable.reload', $chunk);
        $this->assertStringContainsString('resources/js/kids-datatable.js', $chunk);
        $this->assertStringContainsString('FilterChangeResetsDatatablePageFeatureTest', $chunk);
        $this->assertStringContainsString('/doc#datatable-filter-resets-page-index', $html);

        foreach ([
            '/admin/users',
            '/admin/trainers',
            '/admin/administrators',
            '/admin/roles/{name}',
            '/admin/school-leads',
            '/client-contracts',
            '/admin/partners',
            '/admin/partner-leads',
            '/admin/tinkoff/payouts',
            '/admin/legal-entities',
            '/admin/locations',
            '/admin/sport-types',
            '/admin/districts',
            '/admin/teams',
            '/admin/lesson-packages/occurrence-statuses',
        ] as $url) {
            $this->assertStringContainsString($url, $chunk, $url);
        }
    }

    public function test_section_pages_point_at_the_announcement(): void
    {
        $reusable = $this->docFile('reusable-ui-partials.html');
        $this->assertStringContainsString('id="kidscrm-datatable"', $reusable);
        $this->assertStringContainsString('/doc#datatable-filter-resets-page-index', $reusable);
        $this->assertStringContainsString('Смена фильтра вызывает вариант без <code>keepPage</code>', $reusable);
        $this->assertStringContainsString('Reload после фильтров — <code>dtApi.reload()</code> (первая страница)', $reusable);

        $reports = $this->docFile('reports-admin.html');
        $this->assertStringContainsString('dtApi.reload()</code> (первая страница пагинации', $reports);
        $this->assertStringContainsString('dtApi.reload()</code> без <code>keepPage</code>', $reports);

        foreach ([
            'admin-users.html',
            'admin-trainers.html',
            'admin-role-staff.html',
            'contracts.html',
            'school-leads-widget.html',
        ] as $name) {
            $this->assertStringContainsString(
                '/doc#datatable-filter-resets-page-index',
                $this->docFile($name),
                $name
            );
        }
    }

    public function test_live_reload_helper_matches_the_announcement(): void
    {
        $root = dirname(__DIR__, 3);
        $js = (string) file_get_contents($root.'/resources/js/kids-datatable.js');
        $users = (string) file_get_contents($root.'/resources/views/admin/user.blade.php');

        $this->assertStringContainsString('if (options.keepPage)', $js);
        $this->assertStringContainsString('table.ajax.reload(null, false);', $js);
        $this->assertStringContainsString('table.ajax.reload();', $js);
        $this->assertStringContainsString('reloadUsersTable({ resetPage: true })', $users);
        $this->assertStringContainsString('reloadUsersTable();', $users);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
