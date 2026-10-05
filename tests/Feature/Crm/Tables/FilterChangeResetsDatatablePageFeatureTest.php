<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Tables;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Смена фильтра открывает первую страницу DataTables.
 * Правка строки в той же выборке страницу не сбрасывает.
 */
final class FilterChangeResetsDatatablePageFeatureTest extends TestCase
{
    public function test_kids_datatable_reload_without_keep_page_resets_paging(): void
    {
        $js = (string) file_get_contents(base_path('resources/js/kids-datatable.js'));
        $start = strpos($js, 'reload: function (options)');
        $this->assertNotFalse($start);
        $end = strpos($js, 'applyVisibleColumns: applyVisibleColumns', $start);
        $this->assertNotFalse($end);
        $chunk = substr($js, $start, $end - $start);

        $this->assertStringContainsString('if (options.keepPage)', $chunk);
        $this->assertStringContainsString('table.ajax.reload(null, false);', $chunk);
        $this->assertStringContainsString('table.ajax.reload();', $chunk);
        $keepPos = strpos($chunk, 'table.ajax.reload(null, false);');
        $resetPos = strpos($chunk, 'table.ajax.reload();');
        $this->assertNotFalse($keepPos);
        $this->assertNotFalse($resetPos);
        $this->assertLessThan($resetPos, $keepPos);
    }

    /**
     * @param  array{file: string, start: string, end: string, signal: string, page: string}  $slice
     */
    #[DataProvider('slicesProvider')]
    public function test_filter_slice_resets_page_and_row_edit_keeps_it(array $slice): void
    {
        $content = (string) file_get_contents(base_path($slice['file']));
        $chunk = $this->between($content, $slice['start'], $slice['end'], $slice['file']);

        $this->assertStringContainsString($slice['signal'], $chunk, $slice['file'].' '.$slice['start']);

        if ($slice['page'] === 'reset') {
            $this->assertStringNotContainsString('keepPage', $chunk, $slice['file'].' '.$slice['start']);

            return;
        }

        $this->assertSame('keep', $slice['page']);
        $this->assertStringNotContainsString('resetPage', $chunk, $slice['file'].' '.$slice['start']);
    }

    /**
     * @return iterable<string, array{0: array{file: string, start: string, end: string, signal: string, page: string}}>
     */
    public static function slicesProvider(): iterable
    {
        $reset = static fn (string $file, string $start, string $end, string $signal): array => [
            'file' => $file,
            'start' => $start,
            'end' => $end,
            'signal' => $signal,
            'page' => 'reset',
        ];
        $keep = static fn (string $file, string $start, string $end, string $signal): array => [
            'file' => $file,
            'start' => $start,
            'end' => $end,
            'signal' => $signal,
            'page' => 'keep',
        ];

        yield 'users apply' => [$reset(
            'resources/views/admin/user.blade.php',
            'function applyUsersFilters()',
            "$('#filter-apply').on('click'",
            'reloadUsersTable({ resetPage: true })'
        )];
        yield 'users reset' => [$reset(
            'resources/views/admin/user.blade.php',
            "$('#filter-reset').on('click'",
            "$('#filter-name').on('keyup'",
            'reloadUsersTable({ resetPage: true })'
        )];
        yield 'users enter' => [$reset(
            'resources/views/admin/user.blade.php',
            "$('#filter-name').on('keyup'",
            "$('#usersReportFiltersCollapse')",
            'applyUsersFilters()'
        )];
        yield 'users import keeps page' => [$keep(
            'resources/views/admin/user.blade.php',
            'if (typeof reloadUsersTable === \'function\')',
            'function showErrors',
            'reloadUsersTable();'
        )];

        yield 'contracts reload' => [$reset(
            'resources/views/contracts/index.blade.php',
            'function reloadContractsTable()',
            'function applyContractsFilters()',
            'dtApi.reload();'
        )];
        yield 'contracts apply' => [$reset(
            'resources/views/contracts/index.blade.php',
            'function applyContractsFilters()',
            "$('#filter-apply').on('click'",
            'reloadContractsTable();'
        )];
        yield 'contracts reset' => [$reset(
            'resources/views/contracts/index.blade.php',
            "$('#filter-reset').on('click'",
            "$('#filter-search').on('keyup'",
            'reloadContractsTable();'
        )];

        yield 'trainers reload' => [$reset(
            'resources/views/admin/trainers/index.blade.php',
            'function reloadTrainersTable()',
            'window.__reloadTrainersTable',
            'dtApi.reload();'
        )];
        yield 'trainers edit keeps page' => [$keep(
            'resources/views/admin/trainers/index.blade.php',
            'window.__reloadTrainersTable = function ()',
            'function applyTrainersFilters()',
            'dtApi.reload({ keepPage: true });'
        )];

        yield 'role staff apply' => [$reset(
            'resources/views/admin/role_staff/index.blade.php',
            'function applyRoleStaffFilters()',
            "$('#role-staff-filter-apply').on('click'",
            'reloadTable({ resetPage: true })'
        )];
        yield 'role staff reset' => [$reset(
            'resources/views/admin/role_staff/index.blade.php',
            "$('#role-staff-filter-reset').on('click'",
            "$('#role-staff-filter-name').on('keyup'",
            'reloadTable({ resetPage: true })'
        )];
        yield 'role staff create keeps page' => [$keep(
            'resources/views/admin/role_staff/index.blade.php',
            "document.getElementById('roleStaffCreateSubmit')",
            'Пользователь создан',
            'reloadTable();'
        )];

        yield 'school leads apply' => [$reset(
            'resources/views/admin/school-leads/tabs/leads.blade.php',
            "\$filtersForm.on('submit'",
            "$('#schoolLeadsFiltersResetBtn')",
            'dtApi.reload();'
        )];
        yield 'school leads reset' => [$reset(
            'resources/views/admin/school-leads/tabs/leads.blade.php',
            "$('#schoolLeadsFiltersResetBtn').on('click'",
            'var editLeadModalEl',
            'dtApi.reload();'
        )];
        yield 'school leads status keeps page' => [$keep(
            'resources/views/admin/school-leads/tabs/leads.blade.php',
            'function saveLeadStatusInline',
            'Ошибка обновления статуса.',
            'dtApi.reload({ keepPage: true });'
        )];

        yield 'partners filter reload' => [$reset(
            'resources/views/admin/partners/tabs/partners.blade.php',
            'function reloadPartnersTable()',
            "$('#filter-apply').on('click'",
            'dtApi.reload();'
        )];
        yield 'partners edit keeps page' => [$keep(
            'resources/views/admin/partners/tabs/partners.blade.php',
            'window.reloadPartnersTable = function ()',
            'function reloadPartnersTable()',
            'dtApi.reload({ keepPage: true });'
        )];

        yield 'partner leads apply' => [$reset(
            'resources/views/admin/partners/tabs/leads.blade.php',
            "\$filtersForm.on('submit'",
            "$('#partnerLeadsFiltersResetBtn')",
            'dtApi.reload();'
        )];
        yield 'partner leads reset' => [$reset(
            'resources/views/admin/partners/tabs/leads.blade.php',
            "$('#partnerLeadsFiltersResetBtn').on('click'",
            "$('#leads-table').on('click', '.edit-lead'",
            'dtApi.reload();'
        )];
        yield 'partner leads edit keeps page' => [$keep(
            'resources/views/admin/partners/tabs/leads.blade.php',
            "$('#saveLeadBtn').on('click'",
            'Ошибка сохранения.',
            'dtApi.reload({ keepPage: true });'
        )];

        yield 'payouts apply' => [$reset(
            'resources/views/admin/partners/tabs/payouts.blade.php',
            "\$filtersForm.on('submit'",
            "$('#filter-reset').on('click'",
            'dtApi.reload();'
        )];
        yield 'payouts reset' => [$reset(
            'resources/views/admin/partners/tabs/payouts.blade.php',
            "$('#filter-reset').on('click'",
            '@endpush',
            'dtApi.reload();'
        )];

        yield 'legal entities filters' => [$reset(
            'resources/views/admin/legal-entities/index.blade.php',
            'function reloadTableFromFilters()',
            "$('#filter-apply').on('click'",
            'reloadTable({ resetPage: true });'
        )];
        yield 'legal entities create keeps page' => [$keep(
            'resources/views/admin/legal-entities/index.blade.php',
            "document.getElementById('legalEntityCreateSubmit')",
            'Юр. лицо создано',
            'reloadTable();'
        )];

        yield 'locations filters' => [$reset(
            'resources/views/admin/locations/index.blade.php',
            "$('#filter-apply').on('click'",
            "$('#locationsReportFiltersCollapse')",
            'reloadLocationsTable({ resetPage: true });'
        )];
        yield 'locations create keeps page' => [$keep(
            'resources/views/admin/locations/index.blade.php',
            "document.getElementById('locationCreateSubmit')",
            'Объект создан',
            'reloadLocationsTable();'
        )];

        yield 'sport types filters' => [$reset(
            'resources/views/admin/sport-types/index.blade.php',
            'function reloadSportTypesTableFromFilters()',
            "$('#filter-apply').on('click'",
            'reloadSportTypesTable({ resetPage: true });'
        )];
        yield 'sport types create keeps page' => [$keep(
            'resources/views/admin/sport-types/index.blade.php',
            "document.getElementById('sportTypeCreateSubmit')",
            "$('#sport-types-table').on('click'",
            'reloadSportTypesTable();'
        )];

        yield 'districts filters' => [$reset(
            'resources/views/admin/districts/index.blade.php',
            'function reloadDistrictsTableFromFilters()',
            "$('#filter-apply').on('click'",
            'reloadDistrictsTable({ resetPage: true });'
        )];
        yield 'districts create keeps page' => [$keep(
            'resources/views/admin/districts/index.blade.php',
            "document.getElementById('districtCreateSubmit')",
            "$('#districts-table').on('click'",
            'reloadDistrictsTable();'
        )];

        yield 'packages filters' => [$reset(
            'resources/views/admin/lessonPackages/tabs/packages.blade.php',
            "$('#filter-lesson-package-apply').on('click'",
            'const createModalEl',
            'reloadPackagesTable({ resetPage: true });'
        )];
        yield 'packages create keeps page' => [$keep(
            'resources/views/admin/lessonPackages/tabs/packages.blade.php',
            'createFormEl?.addEventListener(\'submit\'',
            'catch (err)',
            'reloadPackagesTable();'
        )];

        yield 'occurrence statuses filters' => [$reset(
            'resources/views/admin/shared/occurrence_statuses_crud.blade.php',
            'function reloadLosTableFromFilters()',
            "$('#los-filter-apply').on('click'",
            'reloadLosTable({ resetPage: true });'
        )];
        yield 'occurrence statuses create keeps page' => [$keep(
            'resources/views/admin/shared/occurrence_statuses_crud.blade.php',
            "route('admin.lesson-packages.occurrence-statuses.store')",
            "$('#los-statuses-table').on('click', '.los-edit-btn'",
            'reloadLosTable();'
        )];

        yield 'debts apply' => [$reset(
            'resources/views/admin/report/debt.blade.php',
            "\$debtFiltersForm.on('submit'",
            "$('#debtReportFiltersResetBtn')",
            'dtApi.reload();'
        )];
        yield 'ltv apply' => [$reset(
            'resources/views/admin/report/ltv.blade.php',
            "\$ltvFiltersForm.on('submit'",
            "$('#ltvReportFiltersResetBtn')",
            'dtApi.reload();'
        )];
        yield 'ltv teams apply' => [$reset(
            'resources/views/admin/report/ltv_teams.blade.php',
            "\$ltvFiltersForm.on('submit'",
            "$('#ltvTeamsReportFiltersResetBtn')",
            'dtApi.reload();'
        )];
        yield 'ltv locations apply' => [$reset(
            'resources/views/admin/report/ltv_locations.blade.php',
            "\$ltvFiltersForm.on('submit'",
            "$('#ltvLocationsReportFiltersResetBtn')",
            'dtApi.reload();'
        )];
        yield 'ltv admins apply' => [$reset(
            'resources/views/admin/report/ltv_admins.blade.php',
            "\$ltvFiltersForm.on('submit'",
            "$('#ltvAdminsReportFiltersResetBtn')",
            'dtApi.reload();'
        )];
        yield 'payments monthly apply' => [$reset(
            'resources/views/admin/report/payment_monthly.blade.php',
            "\$payMonthlyFiltersForm.on('submit'",
            "$('#paymentsMonthlyFiltersResetBtn')",
            'dtApi.reload();'
        )];
        yield 'assignments apply' => [$reset(
            'resources/views/admin/lessonPackages/tabs/assignments.blade.php',
            "\$ulpFiltersForm.on('submit'",
            "$('#ulpAssignmentsFiltersResetBtn')",
            'dtApi.reload();'
        )];
        yield 'assignments edit keeps page' => [$keep(
            'resources/views/admin/lessonPackages/tabs/assignments.blade.php',
            'payload.public_pay_url_rotated',
            'function setSmsAlert',
            'dtApi.reload({ keepPage: true });'
        )];

        yield 'tbank commissions apply' => [$reset(
            'resources/views/admin/setting/tbankCommissions.blade.php',
            "\$form.on('submit'",
            "$('#tbank-commissions-filters-reset')",
            'dtApi.reload();'
        )];
        yield 'tbank commissions edit keeps page' => [$keep(
            'resources/views/admin/setting/tbankCommissions.blade.php',
            'dtApi.reload({keepPage: true});',
            'function renderTbankCommissionPercentCell',
            'dtApi.reload({keepPage: true});'
        )];

        yield 'settings logs apply' => [$reset(
            'resources/views/admin/setting/logs.blade.php',
            "$('#settingsLogsFiltersApply').on('click'",
            "$('#settingsLogsFiltersReset')",
            'dtApi.reload();'
        )];
        yield 'settings logs reset' => [$reset(
            'resources/views/admin/setting/logs.blade.php',
            "$('#settingsLogsFiltersReset').on('click'",
            "\$filtersForm.on('keydown'",
            'dtApi.reload();'
        )];

        yield 'teams filters' => [$reset(
            'resources/views/admin/team.blade.php',
            'function reloadTeamsTable()',
            "$('#filter-apply').on('click'",
            'dtApi.reload();'
        )];
        yield 'payments report apply' => [$reset(
            'resources/views/admin/report/payment.blade.php',
            "\$payFiltersForm.on('submit'",
            "$('#paymentsReportFiltersResetBtn')",
            'dtApi.reload();'
        )];
        yield 'payments report refund keeps page' => [$keep(
            'resources/views/admin/report/payment.blade.php',
            "$('#refundSubmitBtn').on('click'",
            'Ошибка при создании возврата',
            'dtApi.reload({ keepPage: true });'
        )];
    }

    public function test_whole_files_without_row_edit_do_not_keep_page_on_filter_reload(): void
    {
        foreach ([
            'resources/views/admin/report/debt.blade.php',
            'resources/views/admin/report/ltv.blade.php',
            'resources/views/admin/report/ltv_teams.blade.php',
            'resources/views/admin/report/ltv_locations.blade.php',
            'resources/views/admin/report/payment_monthly.blade.php',
            'resources/views/admin/partners/tabs/payouts.blade.php',
            'resources/views/admin/setting/logs.blade.php',
            'resources/views/contracts/index.blade.php',
            'resources/views/admin/team.blade.php',
        ] as $file) {
            $content = (string) file_get_contents(base_path($file));
            $this->assertStringNotContainsString('keepPage', $content, $file);
            $this->assertStringContainsString('dtApi.reload();', $content, $file);
        }
    }

    private function between(string $content, string $start, string $end, string $file): string
    {
        $from = strpos($content, $start);
        $this->assertNotFalse($from, $file.' missing '.$start);
        $to = strpos($content, $end, $from + strlen($start));
        $this->assertNotFalse($to, $file.' missing end '.$end.' after '.$start);

        return substr($content, $from, $to - $from);
    }
}
