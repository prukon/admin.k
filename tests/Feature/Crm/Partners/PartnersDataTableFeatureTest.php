<?php

namespace Tests\Feature\Crm\Partners;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * DataTables /admin/partners: фильтры, поиск, сортировка, пагинация, разметка страницы.
 */
final class PartnersDataTableFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPartnerView();
    }

    public function test_index_renders_datatables_toolbar_and_filters(): void
    {
        $this->get(route('admin.partner.index'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertSee('partnersSectionTabs', false)
            ->assertSee('role="tab">Партнеры</a>', false)
            ->assertSee('id="partners-table"', false)
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('partnersReportFiltersCollapse', false)
            ->assertSee('partnersColumnsDropdown', false)
            ->assertSee('filter-title', false)
            ->assertSee('filter-status', false)
            ->assertSee('serverSide: true', false)
            ->assertSee('pageLength: 10', false)
            ->assertSee('reloadPartnersTable', false)
            ->assertSee('>№<', false)
            ->assertSee('option value="active" selected', false);
    }

    public function test_data_returns_expected_row_structure(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Struct partner',
            'organization_name' => 'ООО Struct',
            'tax_id' => '7707083893',
            'email' => 'struct_' . Str::lower(Str::random(6)) . '@example.test',
            'phone' => '+79991234567',
            'order_by' => 5,
            'is_enabled' => false,
        ]);

        $json = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'inactive',
        ]))
            ->assertOk()
            ->json();

        $row = collect($json['data'])->firstWhere('id', $partner->id);
        $this->assertNotNull($row);
        $this->assertSame([
            'id',
            'order_by',
            'title',
            'organization_name',
            'tax_id',
            'email',
            'phone',
            'status_label',
            'is_enabled',
        ], array_keys($row));
        $this->assertSame('Неактивен', $row['status_label']);
        $this->assertSame(0, $row['is_enabled']);
        $this->assertSame('ООО Struct', $row['organization_name']);
    }

    public function test_data_filters_by_status_active(): void
    {
        Partner::factory()->create([
            'title' => 'Active filter smoke',
            'is_enabled' => true,
        ]);
        Partner::factory()->create([
            'title' => 'Inactive filter smoke',
            'is_enabled' => false,
        ]);

        $response = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
        ]));

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Active filter smoke', $titles);
        $this->assertNotContains('Inactive filter smoke', $titles);
    }

    public function test_data_filters_by_status_inactive(): void
    {
        Partner::factory()->create([
            'title' => 'Active inactive-filter',
            'is_enabled' => true,
        ]);
        Partner::factory()->create([
            'title' => 'Inactive inactive-filter',
            'is_enabled' => false,
        ]);

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'inactive',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.title', 'Inactive inactive-filter')
            ->assertJsonPath('data.0.status_label', 'Неактивен');
    }

    public function test_data_search_by_organization_name(): void
    {
        Partner::factory()->create([
            'title' => 'Org search A',
            'organization_name' => 'УникальнаяОргXYZ',
            'is_enabled' => true,
        ]);
        Partner::factory()->create([
            'title' => 'Org search B',
            'organization_name' => 'Другая организация',
            'is_enabled' => true,
        ]);

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => 'УникальнаяОргXYZ',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.organization_name', 'УникальнаяОргXYZ');
    }

    public function test_data_search_by_tax_id(): void
    {
        Partner::factory()->create([
            'title' => 'Tax search',
            'tax_id' => '9988776655',
            'is_enabled' => true,
        ]);
        Partner::factory()->create([
            'title' => 'Other tax',
            'tax_id' => '1111111111',
            'is_enabled' => true,
        ]);

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => '9988776655',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.tax_id', '9988776655');
    }

    public function test_data_search_by_email_and_phone(): void
    {
        Partner::factory()->create([
            'title' => 'Contact search',
            'email' => 'unique_mail_' . Str::lower(Str::random(6)) . '@example.test',
            'phone' => '+79998887766',
            'is_enabled' => true,
        ]);
        Partner::factory()->create([
            'title' => 'Other contact',
            'email' => 'other_' . Str::lower(Str::random(6)) . '@example.test',
            'phone' => '+79991112233',
            'is_enabled' => true,
        ]);

        $email = Partner::query()->where('title', 'Contact search')->value('email');

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => $email,
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.title', 'Contact search');

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => '+79998887766',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.phone', '+79998887766');
    }

    public function test_data_search_filters_by_numeric_id(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'By id search',
            'is_enabled' => true,
        ]);

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'search' => ['value' => (string) $partner->id],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id', $partner->id);
    }

    public function test_data_panel_title_takes_precedence_over_datatables_search(): void
    {
        Partner::factory()->create([
            'title' => 'Panel Alpha Partner',
            'is_enabled' => true,
        ]);
        Partner::factory()->create([
            'title' => 'Search Beta Partner',
            'is_enabled' => true,
        ]);

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => 'Panel',
            'search' => ['value' => 'Search Beta'],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.title', 'Panel Alpha Partner');
    }

    public function test_data_filters_combined_title_and_status(): void
    {
        Partner::factory()->create([
            'title' => 'Combo Active Partner',
            'is_enabled' => true,
        ]);
        Partner::factory()->create([
            'title' => 'Combo Inactive Partner',
            'is_enabled' => false,
        ]);
        Partner::factory()->create([
            'title' => 'Other Active Partner',
            'is_enabled' => true,
        ]);

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'title' => 'Combo',
            'status' => 'inactive',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.title', 'Combo Inactive Partner');
    }

    public function test_data_pagination_returns_second_page(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            Partner::factory()->create([
                'title' => 'Paginate partner ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'order_by' => $i,
                'is_enabled' => true,
            ]);
        }

        $page1 = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 5,
            'status' => 'active',
            'title' => 'Paginate partner',
        ]))->assertOk()->json();

        $page2 = $this->getJson(route('admin.partner.data', [
            'draw' => 2,
            'start' => 5,
            'length' => 5,
            'status' => 'active',
            'title' => 'Paginate partner',
        ]))->assertOk()->json();

        $this->assertCount(5, $page1['data']);
        $this->assertCount(5, $page2['data']);
        $this->assertNotSame($page1['data'][0]['id'], $page2['data'][0]['id']);
    }

    public function test_data_sort_by_title_desc(): void
    {
        Partner::factory()->create(['title' => 'AAA Sort', 'is_enabled' => true, 'order_by' => 1]);
        Partner::factory()->create(['title' => 'ZZZ Sort', 'is_enabled' => true, 'order_by' => 2]);

        $response = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'title' => 'Sort',
            'order' => [['column' => 2, 'dir' => 'desc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'order_by'],
                ['name' => 'title'],
                ['name' => 'organization_name'],
                ['name' => 'tax_id'],
                ['name' => 'email'],
                ['name' => 'phone'],
                ['name' => 'status_label'],
                ['name' => 'actions'],
            ],
        ]));

        $response->assertOk();
        $titles = array_column($response->json('data'), 'title');
        $this->assertSame(['ZZZ Sort', 'AAA Sort'], $titles);
    }

    public function test_data_sort_by_status_label_desc(): void
    {
        Partner::factory()->create(['title' => 'Sort Active X', 'is_enabled' => true]);
        Partner::factory()->create(['title' => 'Sort Inactive X', 'is_enabled' => false]);

        $response = $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'title' => 'Sort',
            'order' => [['column' => 7, 'dir' => 'desc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'order_by'],
                ['name' => 'title'],
                ['name' => 'organization_name'],
                ['name' => 'tax_id'],
                ['name' => 'email'],
                ['name' => 'phone'],
                ['name' => 'status_label'],
                ['name' => 'actions'],
            ],
        ]));

        $response->assertOk();
        $labels = array_column($response->json('data'), 'status_label');
        $this->assertContains('Активен', $labels);
        $this->assertContains('Неактивен', $labels);
        $this->assertSame('Активен', $labels[0]);
    }

    public function test_data_all_filter_query_params_return_200(): void
    {
        Partner::factory()->create([
            'title' => 'Filter 200 smoke',
            'is_enabled' => true,
        ]);

        $queries = [
            ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'active'],
            ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'inactive'],
            ['draw' => 1, 'start' => 0, 'length' => 10, 'title' => 'Filter'],
            ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => 'Filter']],
            ['draw' => 1, 'start' => 0, 'length' => 10, 'title' => 'Filter', 'search' => ['value' => 'Other'], 'status' => 'active'],
        ];

        foreach ($queries as $params) {
            $this->getJson(route('admin.partner.data', $params))->assertOk();
        }
    }

    public function test_data_endpoint_with_full_column_layout_returns_200(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'Layout smoke partner',
            'organization_name' => 'Layout Org',
            'is_enabled' => true,
        ]);

        $query = http_build_query([
            'order' => [['column' => 2, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'order_by'],
                ['name' => 'title'],
                ['name' => 'organization_name'],
                ['name' => 'tax_id'],
                ['name' => 'email'],
                ['name' => 'phone'],
                ['name' => 'status_label'],
                ['name' => 'actions'],
            ],
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'title' => 'Layout',
            'status' => 'active',
        ]);

        $json = $this->get(route('admin.partner.data') . '?' . $query)
            ->assertOk()
            ->json();

        $row = collect($json['data'])->firstWhere('id', $partner->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('organization_name', $row);
        $this->assertArrayHasKey('status_label', $row);
    }

    public function test_data_validates_invalid_status_returns_422(): void
    {
        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'status' => 'unknown',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
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
