<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Partners;

use App\Models\Partner;
use App\Models\Payment;
use App\Models\TinkoffPayout;
use App\Models\User;
use App\Support\PartnerListMetrics;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Итоги списка /admin/partners: JSON totals по фильтрам, tfoot, UX «не сумма страницы».
 *
 * @see /docs/documentation/account-partner-organization.html §3.1.1
 */
final class PartnersListTotalsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-13 21:00:00', 'Europe/Moscow'));

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPartnerView();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_cannot_open_partners_totals_page_or_data(): void
    {
        Auth::logout();

        $this->get(route('admin.partner.index'))->assertRedirect();

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertUnauthorized();

        $htmlData = $this->get(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));
        $this->assertContains($htmlData->getStatusCode(), [302, 401]);
        $this->assertNotSame(500, $htmlData->getStatusCode());
        $this->assertNotSame(200, $htmlData->getStatusCode(), 'Гость не должен получать totals JSON как 200');
    }

    public function test_manager_without_partner_view_gets_403_on_totals_data(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.partner.index'))->assertForbidden();
        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
        ]))->assertForbidden();
    }

    public function test_admin_with_partner_view_gets_totals_json_for_all_metric_keys(): void
    {
        $json = $this->getJson(route('admin.partner.data', [
            'draw' => 3,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
                'totals' => PartnerListMetrics::JSON_KEYS,
            ])
            ->json();

        $this->assertSame(3, $json['draw']);
        foreach (PartnerListMetrics::JSON_KEYS as $key) {
            $this->assertArrayHasKey($key, $json['totals']);
            $this->assertIsNumeric($json['totals'][$key]);
        }
    }

    public function test_non_ajax_get_data_still_returns_totals_json_not_empty_html(): void
    {
        $response = $this->get(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $json = $response->json();
        $this->assertIsArray($json['totals'] ?? null);
        foreach (PartnerListMetrics::JSON_KEYS as $key) {
            $this->assertArrayHasKey($key, $json['totals']);
        }
    }

    public function test_post_patch_delete_on_totals_data_endpoint_do_not_return_500(): void
    {
        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $response = $this->json($method, route('admin.partner.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]);

            $this->assertNotSame(
                500,
                $response->getStatusCode(),
                $method.' /admin/partners/data не должен падать 500'
            );
            $this->assertContains($response->getStatusCode(), [404, 405, 419]);
        }
    }

    public function test_invalid_status_returns_422_with_status_field_error_not_totals(): void
    {
        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'status' => 'unknown',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Недопустимое значение фильтра статуса.')
            ->assertJsonMissingPath('totals');
    }

    public function test_too_long_search_returns_422_with_title_field_error(): void
    {
        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'title' => str_repeat('а', 256),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonPath('errors.title.0', 'Поиск по названию не должен превышать 255 символов.');
    }

    public function test_invalid_page_length_returns_422_with_length_field_error(): void
    {
        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'length' => 0,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['length']);
    }

    public function test_non_ajax_invalid_status_does_not_return_empty_200(): void
    {
        $response = $this->from(route('admin.partner.index'))
            ->get(route('admin.partner.data', [
                'draw' => 1,
                'status' => 'unknown',
            ]));

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 422]);
    }

    public function test_user_without_organization_cannot_load_totals_json(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor);
        $this->flushSession();

        $response = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $this->assertContains($response->getStatusCode(), [302, 401]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertGuest();
    }

    public function test_index_first_open_selects_active_filter_so_totals_match_default_ui(): void
    {
        $html = $this->get(route('admin.partner.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<select id="filter-status"[^>]*>[\s\S]*<option value="active" selected>/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="" selected>/',
            $html
        );
        $this->assertStringContainsString("const defaultFilterStatus = 'active';", $html);
        $this->assertStringContainsString('d.status = params.status', $html);
    }

    public function test_api_without_status_does_not_force_active_default_unlike_ui(): void
    {
        $prefix = 'TotalsNoDefault ' . uniqid('', true);
        $active = Partner::factory()->create([
            'title' => $prefix . ' On',
            'is_enabled' => true,
        ]);
        $inactive = Partner::factory()->create([
            'title' => $prefix . ' Off',
            'is_enabled' => false,
        ]);

        $this->seedTurnover($active, 100000);
        $this->seedTurnover($inactive, 400000);

        $json = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'title' => $prefix,
        ]))->assertOk()->json();

        $this->assertEquals(5000, $json['totals']['turnover_all']);
        $this->assertSame(2, $json['recordsFiltered']);
    }

    public function test_totals_follow_status_filter_and_ignore_current_page_rows(): void
    {
        $prefix = 'TotalsPageUx ' . uniqid('', true);
        $first = Partner::factory()->create([
            'title' => $prefix . ' A',
            'is_enabled' => true,
            'order_by' => 1,
        ]);
        $second = Partner::factory()->create([
            'title' => $prefix . ' B',
            'is_enabled' => true,
            'order_by' => 2,
        ]);

        $this->seedTurnover($first, 100000);
        $this->seedTurnover($second, 250000);

        $pageOne = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 1,
            'status' => 'active',
            'title' => $prefix,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => $this->dataTableColumnsLayout(),
        ]))->assertOk()->json();

        $pageTwo = $this->getJson(route('admin.partner.data', [
            'draw' => 2,
            'start' => 1,
            'length' => 1,
            'status' => 'active',
            'title' => $prefix,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => $this->dataTableColumnsLayout(),
        ]))->assertOk()->json();

        $this->assertSame(2, $pageOne['recordsFiltered']);
        $this->assertCount(1, $pageOne['data']);
        $this->assertCount(1, $pageTwo['data']);
        $this->assertNotSame($pageOne['data'][0]['id'], $pageTwo['data'][0]['id']);
        $this->assertEquals(3500, $pageOne['totals']['turnover_all']);
        $this->assertEquals($pageOne['totals']['turnover_all'], $pageTwo['totals']['turnover_all']);
        $this->assertNotEquals(
            $pageOne['data'][0]['turnover_all'],
            $pageOne['totals']['turnover_all'],
            'Итог страницы из одной строки не должен совпадать с итогом выборки — иначе футер врёт'
        );
    }

    public function test_totals_include_platform_commission_of_all_filtered_partners(): void
    {
        $prefix = 'TotalsFee ' . uniqid('', true);
        $first = Partner::factory()->create([
            'title' => $prefix . ' A',
            'is_enabled' => true,
        ]);
        $second = Partner::factory()->create([
            'title' => $prefix . ' B',
            'is_enabled' => true,
        ]);

        $studentA = User::factory()->create([
            'partner_id' => $first->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
        ]);
        $studentB = User::factory()->create([
            'partner_id' => $second->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
        ]);
        $this->createPlatformFeePayout($first, $studentA, 1100, '2026-08-10 12:00:00');
        $this->createPlatformFeePayout($second, $studentB, 4400, '2026-08-10 12:00:00');

        $json = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 1,
            'status' => 'active',
            'title' => $prefix,
        ]))->assertOk()->json();

        $this->assertCount(1, $json['data']);
        $this->assertEquals(55, $json['totals']['platform_commission_all']);
        $this->assertEquals(55, $json['totals']['platform_commission_month_0']);
        $this->assertNotEquals(
            $json['data'][0]['platform_commission_all'],
            $json['totals']['platform_commission_all']
        );
    }

    public function test_empty_search_yields_zero_totals_not_missing_keys(): void
    {
        $json = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
            'title' => 'NoSuchPartnerTotals-' . uniqid('', true),
        ]))->assertOk()->json();

        $this->assertSame(0, $json['recordsFiltered']);
        $this->assertSame([], $json['data']);
        foreach (PartnerListMetrics::JSON_KEYS as $key) {
            $this->assertArrayHasKey($key, $json['totals']);
            $this->assertEquals(0, $json['totals'][$key]);
        }
    }

    public function test_soft_deleted_partner_is_not_counted_in_totals(): void
    {
        $prefix = 'TotalsDeleted ' . uniqid('', true);
        $partner = Partner::factory()->create([
            'title' => $prefix . ' Club',
            'is_enabled' => true,
        ]);
        $this->seedTurnover($partner, 800000);
        $partner->delete();

        $json = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => $prefix,
        ]))->assertOk()->json();

        $this->assertSame(0, $json['recordsFiltered']);
        $this->assertEquals(0, $json['totals']['turnover_all']);
    }

    public function test_index_renders_empty_tfoot_matching_thead_and_fixed_footer_assets(): void
    {
        $html = $this->get(route('admin.partner.index'))->assertOk()->getContent();

        $tablePos = strpos($html, 'id="partners-table"');
        $this->assertNotFalse($tablePos);
        $tableHtml = substr($html, $tablePos, 9000);

        preg_match('/<thead>(.*?)<\/thead>/s', $tableHtml, $theadMatch);
        preg_match('/<tfoot>(.*?)<\/tfoot>/s', $tableHtml, $tfootMatch);
        $this->assertNotEmpty($theadMatch[1] ?? null);
        $this->assertNotEmpty($tfootMatch[1] ?? null);
        preg_match_all('/<th\b/i', $theadMatch[1], $theadTh);
        preg_match_all('/<th\b/i', $tfootMatch[1], $tfootTh);
        $this->assertCount(17, $theadTh[0]);
        $this->assertCount(17, $tfootTh[0]);
        $this->assertStringNotContainsString('Итого', $tfootMatch[1]);

        $this->assertStringContainsString('footerCallback', $html);
        $this->assertStringContainsString('fillPartnersTotalsFooter(this.api())', $html);
        $this->assertStringContainsString('header: false', $html);
        $this->assertStringContainsString('footer: true', $html);
        $this->assertStringContainsString('dataTables.fixedHeader.min.js', $html);

        $pluginPos = strpos($html, 'dataTables.fixedHeader.min.js');
        $createPos = strpos($html, "KidsCrmDataTable.create('#partners-table'");
        $this->assertNotFalse($pluginPos);
        $this->assertNotFalse($createPos);
        $this->assertLessThan($createPos, $pluginPos);
    }

    public function test_filter_triggers_reload_table_so_totals_refresh_without_full_page_reload(): void
    {
        $html = $this->get(route('admin.partner.index'))->assertOk()->getContent();

        $applyPos = strpos($html, "$('#filter-apply').on('click'");
        $this->assertNotFalse($applyPos);
        $this->assertStringContainsString('reloadPartnersTable', substr($html, $applyPos, 220));

        $submitPos = strpos($html, "$('#partners-report-filters').on('submit'");
        $this->assertNotFalse($submitPos);
        $submitChunk = substr($html, $submitPos, 280);
        $this->assertStringContainsString('e.preventDefault()', $submitChunk);
        $this->assertStringContainsString('reloadPartnersTable', $submitChunk);

        $resetPos = strpos($html, "$('#filter-reset').on('click'");
        $this->assertNotFalse($resetPos);
        $resetChunk = substr($html, $resetPos, 400);
        $this->assertStringContainsString('defaultFilterStatus', $resetChunk);
        $this->assertStringContainsString('reloadPartnersTable', $resetChunk);
        $this->assertStringNotContainsString("$('#filter-status').val('');", $resetChunk);
    }

    public function test_footer_js_paints_server_totals_not_visible_page_rows(): void
    {
        $footers = $this->runPartnersTotalsFooterJs(hideTitle: false);

        $this->assertSame('1136', $footers['active_users_count']);
        $this->assertSame('9999', $footers['turnover_all']);
        $this->assertSame('55', $footers['platform_commission_all']);
        $this->assertStringContainsString('Итого', $footers['title'] ?? '');
        $this->assertSame('', $footers['order_by'] ?? '');
        $this->assertNotSame(
            '30',
            $footers['turnover_all'],
            'Футер не должен суммировать видимые строки страницы (10+20)'
        );
        $this->assertNotSame('3', $footers['active_users_count']);
    }

    public function test_footer_js_puts_itogo_on_fallback_column_when_title_is_hidden(): void
    {
        $footers = $this->runPartnersTotalsFooterJs(hideTitle: true);

        $this->assertSame('', $footers['title'] ?? '');
        $this->assertStringContainsString('Итого', $footers['order_by'] ?? '');
        $this->assertSame('9999', $footers['turnover_all']);
        $this->assertStringNotContainsString('Итого', $footers['turnover_all']);
    }

    public function test_footer_js_does_not_write_itogo_into_money_columns_when_title_visible(): void
    {
        $footers = $this->runPartnersTotalsFooterJs(hideTitle: false);

        foreach (PartnerListMetrics::JSON_KEYS as $key) {
            $this->assertStringNotContainsString(
                'Итого',
                (string) ($footers[$key] ?? ''),
                "Подпись «Итого» не должна попадать в числовую колонку {$key}"
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function runPartnersTotalsFooterJs(bool $hideTitle): array
    {
        $extracted = $this->extractPartnersTotalsFooterJs();
        $srcFile = sys_get_temp_dir().'/partners-totals-src-'.uniqid('', true).'.cjs';
        $harnessFile = sys_get_temp_dir().'/partners-totals-harness-'.uniqid('', true).'.cjs';
        file_put_contents($srcFile, $extracted);

        $hideTitleJs = $hideTitle ? 'true' : 'false';
        $metricKeysJson = json_encode(PartnerListMetrics::JSON_KEYS, JSON_THROW_ON_ERROR);

        $harness = <<<JS
const fs = require('fs');
const vm = require('vm');
const src = fs.readFileSync(process.argv[2], 'utf8');
const hideTitle = {$hideTitleJs};
const metricKeys = {$metricKeysJson};

const pageRows = [
  { active_users_count: 1, signed_contracts_count: 1, turnover_all: 10, platform_commission_all: 4,
    turnover_month_0: 10, platform_commission_month_0: 4, turnover_month_1: 0, platform_commission_month_1: 0,
    turnover_month_2: 0, platform_commission_month_2: 0 },
  { active_users_count: 2, signed_contracts_count: 1, turnover_all: 20, platform_commission_all: 6,
    turnover_month_0: 20, platform_commission_month_0: 6, turnover_month_1: 0, platform_commission_month_1: 0,
    turnover_month_2: 0, platform_commission_month_2: 0 }
];
const totals = {
  active_users_count: 1136,
  signed_contracts_count: 0,
  turnover_all: 9999,
  platform_commission_all: 55,
  turnover_month_0: 0,
  platform_commission_month_0: 0,
  turnover_month_1: 9999,
  platform_commission_month_1: 0,
  turnover_month_2: 0,
  platform_commission_month_2: 0
};

const columns = [
  { sName: 'rownum', visible: true },
  { sName: 'order_by', visible: true },
  { sName: 'title', visible: !hideTitle },
  { sName: 'email', visible: true },
  { sName: 'phone', visible: true },
  { sName: 'status_label', visible: true },
  { sName: 'active_users_count', visible: true },
  { sName: 'signed_contracts_count', visible: true },
  { sName: 'turnover_all', visible: true },
  { sName: 'platform_commission_all', visible: true },
  { sName: 'turnover_month_0', visible: true },
  { sName: 'platform_commission_month_0', visible: true },
  { sName: 'turnover_month_1', visible: true },
  { sName: 'platform_commission_month_1', visible: true },
  { sName: 'turnover_month_2', visible: true },
  { sName: 'platform_commission_month_2', visible: true },
  { sName: 'actions', visible: true }
];

const footers = {};

function makeCol(idx) {
  const name = columns[idx].sName;
  return {
    index: () => idx,
    visible: () => columns[idx].visible,
    footer: () => ({ __key: name }),
    data: () => pageRows.map((row) => row[name])
  };
}

const api = {
  ajax: { json: () => ({ data: pageRows, totals: totals }) },
  settings: () => [{ aoColumns: columns.map((c) => ({ sName: c.sName, mRender: null })) }],
  columns: () => ({
    every: function (cb) {
      columns.forEach((_, idx) => cb.call(makeCol(idx)));
    }
  }),
  column: (selector) => {
    const name = String(selector).replace(':name', '');
    const idx = columns.findIndex((c) => c.sName === name);
    return makeCol(idx < 0 ? 0 : idx);
  },
  rows: () => ({ data: () => pageRows })
};

function \$(el) {
  const key = el && el.__key ? el.__key : null;
  return {
    html: function (html) {
      if (key) {
        footers[key] = html;
      }
      return this;
    }
  };
}

const context = { api: api, \$: \$, console, footers: footers, result: null };
vm.createContext(context);
vm.runInContext(src + '\\nfillPartnersTotalsFooter(api); result = footers;', context);
process.stdout.write(JSON.stringify(context.result));
JS;
        file_put_contents($harnessFile, $harness);

        try {
            $output = [];
            $exitCode = 0;
            exec(
                'node '.escapeshellarg($harnessFile).' '.escapeshellarg($srcFile).' 2>&1',
                $output,
                $exitCode
            );
            $this->assertSame(0, $exitCode, implode("\n", $output));
            $decoded = json_decode(implode("\n", $output), true);
            $this->assertIsArray($decoded, implode("\n", $output));

            return $decoded;
        } finally {
            @unlink($srcFile);
            @unlink($harnessFile);
        }
    }

    private function extractPartnersTotalsFooterJs(): string
    {
        $content = (string) file_get_contents(resource_path('views/admin/partners/tabs/partners.blade.php'));
        $start = strpos($content, 'const partnersTotalsKeys = [');
        $this->assertNotFalse($start);
        $fnStart = strpos($content, 'function fillPartnersTotalsFooter(api)', $start);
        $this->assertNotFalse($fnStart);
        $open = strpos($content, '{', $fnStart);
        $this->assertNotFalse($open);

        $depth = 0;
        $len = strlen($content);
        for ($i = $open; $i < $len; $i++) {
            $ch = $content[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $start, $i + 1 - $start);
                }
            }
        }

        $this->fail('Не закрылась function fillPartnersTotalsFooter');
    }

    private function seedTurnover(Partner $partner, int $summCents): void
    {
        $student = User::factory()->create([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
        ]);
        Payment::factory()->forUser($student)->create([
            'summ_cents' => $summCents,
            'operation_date' => '2026-08-10 12:00:00',
        ]);
    }

    private function createPlatformFeePayout(
        Partner $partner,
        User $student,
        int $platformFeeCents,
        string $operationDate,
    ): TinkoffPayout {
        $dealId = 'totals-deal-' . uniqid('', true);
        Payment::factory()->forUser($student)->create([
            'summ_cents' => 100000,
            'operation_date' => $operationDate,
            'deal_id' => $dealId,
        ]);

        return TinkoffPayout::create([
            'payment_id' => null,
            'partner_id' => $partner->id,
            'deal_id' => $dealId,
            'amount' => 1,
            'is_final' => true,
            'status' => 'COMPLETED',
            'platform_fee' => $platformFeeCents,
            'source' => 'manual',
        ]);
    }

    /**
     * @return list<array{name: string}>
     */
    private function dataTableColumnsLayout(): array
    {
        return [
            ['name' => 'rownum'],
            ['name' => 'order_by'],
            ['name' => 'title'],
            ['name' => 'email'],
            ['name' => 'phone'],
            ['name' => 'status_label'],
            ['name' => PartnerListMetrics::COLUMN_ACTIVE_USERS],
            ['name' => PartnerListMetrics::COLUMN_SIGNED_CONTRACTS],
            ['name' => PartnerListMetrics::COLUMN_TURNOVER_ALL],
            ['name' => PartnerListMetrics::COLUMN_PLATFORM_COMMISSION_ALL],
            ['name' => PartnerListMetrics::COLUMN_TURNOVER_MONTH_0],
            ['name' => PartnerListMetrics::COLUMN_PLATFORM_COMMISSION_MONTH_0],
            ['name' => PartnerListMetrics::COLUMN_TURNOVER_MONTH_1],
            ['name' => PartnerListMetrics::COLUMN_PLATFORM_COMMISSION_MONTH_1],
            ['name' => PartnerListMetrics::COLUMN_TURNOVER_MONTH_2],
            ['name' => PartnerListMetrics::COLUMN_PLATFORM_COMMISSION_MONTH_2],
            ['name' => 'actions'],
        ];
    }

    private function grantPartnerView(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('partner.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
