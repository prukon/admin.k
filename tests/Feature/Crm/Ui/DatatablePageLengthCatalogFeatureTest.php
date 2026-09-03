<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui;

use App\Models\User;
use App\Models\UserTableSetting;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Канонический список страниц, где «Показать N» пишется в user_table_settings.page_length.
 *
 * @see \Tests\Feature\Crm\Ui\BladeInlineJsSyntaxTest::test_only_opted_in_blades_enable_page_length_persist
 */
final class DatatablePageLengthCatalogFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    /**
     * @return list<array{
     *     label: string,
     *     url: string,
     *     index_route: string,
     *     save_url: string,
     *     table_key: string,
     *     view_var: string,
     *     create: string,
     *     blade: string,
     *     auth: string,
     *     permission: string
     * }>
     */
    public static function catalog(): array
    {
        return [
            [
                'label'       => 'Клиенты',
                'url'         => '/admin/users',
                'index_route' => 'admin.user1',
                'save_url'    => '/admin/users/columns-settings',
                'table_key'   => 'users_index',
                'view_var'    => 'usersPageLength',
                'create'      => "KidsCrmDataTable.create('#users-table'",
                'blade'       => 'admin/user.blade.php',
                'auth'        => 'admin',
                'permission'  => 'users.view',
            ],
            [
                'label'       => 'Группы',
                'url'         => '/admin/teams',
                'index_route' => 'admin.team.index',
                'save_url'    => '/admin/teams/columns-settings',
                'table_key'   => 'teams_index',
                'view_var'    => 'teamsPageLength',
                'create'      => "KidsCrmDataTable.create('#teams-table'",
                'blade'       => 'admin/team.blade.php',
                'auth'        => 'admin',
                'permission'  => 'groups.view',
            ],
            [
                'label'       => 'Заявки',
                'url'         => '/admin/school-leads',
                'index_route' => 'admin.school-leads',
                'save_url'    => '/admin/school-leads/columns-settings',
                'table_key'   => 'school_leads_index',
                'view_var'    => 'leadsPageLength',
                'create'      => "KidsCrmDataTable.create('#leads-table'",
                'blade'       => 'admin/school-leads/tabs/leads.blade.php',
                'auth'        => 'admin',
                'permission'  => 'schoolLeads.view',
            ],
            [
                'label'       => 'Все платежи',
                'url'         => '/admin/reports/payments',
                'index_route' => 'payments',
                'save_url'    => '/admin/reports/payments/columns-settings',
                'table_key'   => 'reports_payments',
                'view_var'    => 'paymentsPageLength',
                'create'      => "KidsCrmDataTable.create('#payments-table'",
                'blade'       => 'admin/report/payment.blade.php',
                'auth'        => 'admin',
                'permission'  => 'reports.view',
            ],
            [
                'label'       => 'Платежи по месяцам',
                'url'         => '/admin/reports/payments/monthly',
                'index_route' => 'reports.payments.monthly',
                'save_url'    => '/admin/reports/payments/monthly/columns-settings',
                'table_key'   => 'reports_payments_monthly',
                'view_var'    => 'paymentsMonthlyPageLength',
                'create'      => "KidsCrmDataTable.create('#payments-monthly-table'",
                'blade'       => 'admin/report/payment_monthly.blade.php',
                'auth'        => 'admin',
                'permission'  => 'reports.view',
            ],
            [
                'label'       => 'LTV',
                'url'         => '/admin/reports/ltv',
                'index_route' => 'reports.ltv',
                'save_url'    => '/admin/reports/ltv/columns-settings',
                'table_key'   => 'reports_ltv',
                'view_var'    => 'ltvPageLength',
                'create'      => "KidsCrmDataTable.create('#ltv-table'",
                'blade'       => 'admin/report/ltv.blade.php',
                'auth'        => 'admin',
                'permission'  => 'reports.view',
            ],
            [
                'label'       => 'Платежные запросы',
                'url'         => '/admin/reports/payment-intents',
                'index_route' => 'reports.payment-intents.index',
                'save_url'    => '/admin/reports/payment-intents/columns-settings',
                'table_key'   => 'reports_payment_intents',
                'view_var'    => 'paymentIntentsPageLength',
                'create'      => "KidsCrmDataTable.create('#payment-intents-table'",
                'blade'       => 'admin/report/payment_intents.blade.php',
                'auth'        => 'superadmin',
                'permission'  => 'reports.payment.intents.view',
            ],
            [
                'label'       => 'Фискальные чеки',
                'url'         => '/admin/reports/fiscal-receipts',
                'index_route' => 'reports.fiscal-receipts.index',
                'save_url'    => '/admin/reports/fiscal-receipts/columns-settings',
                'table_key'   => 'reports_fiscal_receipts',
                'view_var'    => 'fiscalReceiptsPageLength',
                'create'      => "KidsCrmDataTable.create('#fiscal-receipts-table'",
                'blade'       => 'admin/report/fiscal_receipts.blade.php',
                'auth'        => 'superadmin',
                'permission'  => 'reports.fiscal.receipts.view',
            ],
            [
                'label'       => 'Выплаты T‑Bank',
                'url'         => '/admin/tinkoff/payouts',
                'index_route' => 'admin.tinkoff.payouts.index',
                'save_url'    => '/admin/tinkoff/payouts/columns-settings',
                'table_key'   => 'tinkoff_payouts_index',
                'view_var'    => 'payoutsPageLength',
                'create'      => "KidsCrmDataTable.create('#payouts-table'",
                'blade'       => 'admin/partners/tabs/payouts.blade.php',
                'auth'        => 'payouts',
                'permission'  => 'tbank.payouts.manage',
            ],
        ];
    }

    /**
     * @return iterable<string, array{0: array<string, string>}>
     */
    public static function catalogProvider(): iterable
    {
        foreach (self::catalog() as $case) {
            yield $case['table_key'] => [$case];
        }
    }

    public function test_opted_in_blades_match_catalog_exactly(): void
    {
        $hits = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (! str_contains($contents, 'persistPageLength: true')) {
                continue;
            }

            $relative = str_replace(
                resource_path('views').DIRECTORY_SEPARATOR,
                '',
                $file->getPathname()
            );
            $hits[] = str_replace('\\', '/', $relative);
        }

        sort($hits);

        $expected = array_column(self::catalog(), 'blade');
        sort($expected);

        $this->assertSame($expected, $hits);
        $this->assertCount(9, $hits);
    }

    /**
     * @param  array<string, string>  $case
     */
    #[DataProvider('catalogProvider')]
    public function test_page_persists_show_by_to_own_table_key(array $case): void
    {
        $this->authenticateCase($case);

        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', $case['table_key'])
            ->delete();

        $html = $this->get(route($case['index_route']))
            ->assertOk()
            ->assertViewHas($case['view_var'], 10)
            ->getContent();

        $pos = strpos($html, $case['create']);
        $this->assertNotFalse($pos, $case['create'].' не найден на '.$case['url']);
        $chunk = substr($html, $pos, 4500);
        $this->assertStringContainsString('persistPageLength: true', $chunk);
        $this->assertMatchesRegularExpression('/pageLength:\s*10\b/', $chunk);

        $this->postJson($case['save_url'], [
            'page_length' => 50,
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertSame(
            50,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', $case['table_key'])
                ->value('page_length')
        );

        $this->get(route($case['index_route']))
            ->assertOk()
            ->assertViewHas($case['view_var'], 50);
    }

    public function test_index_documentation_lists_every_catalog_url_and_table_key(): void
    {
        $path = base_path('docs/documentation/index.html');
        $this->assertFileExists($path);
        $html = (string) file_get_contents($path);

        $this->assertStringContainsString('id="datatable-page-length-catalog"', $html);
        $this->assertStringContainsString('DatatablePageLengthCatalogFeatureTest', $html);

        foreach (self::catalog() as $case) {
            $this->assertStringContainsString($case['url'], $html, $case['label']);
            $this->assertStringContainsString('<code>'.$case['table_key'].'</code>', $html, $case['label']);
        }
    }

    public function test_reusable_ui_partials_lists_every_catalog_url_and_table_key(): void
    {
        $path = base_path('docs/documentation/reusable-ui-partials.html');
        $this->assertFileExists($path);
        $html = (string) file_get_contents($path);

        $this->assertStringContainsString('id="persist-page-length-catalog"', $html);

        foreach (self::catalog() as $case) {
            $this->assertStringContainsString($case['url'], $html, $case['label']);
            $this->assertStringContainsString('<code>'.$case['table_key'].'</code>', $html, $case['label']);
        }
    }

    /**
     * @param  array<string, string>  $case
     */
    private function authenticateCase(array $case): void
    {
        if ($case['auth'] === 'superadmin') {
            $this->asSuperadmin();

            return;
        }

        $this->asAdmin();
        if ($case['auth'] === 'payouts') {
            $this->grantPermission($this->user, 'tbank.payouts.manage');
        }
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
