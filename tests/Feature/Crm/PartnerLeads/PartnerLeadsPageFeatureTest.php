<?php

namespace Tests\Feature\Crm\PartnerLeads;

use App\Models\PartnerLead;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UI и API вкладки «Лиды» (/admin/partner-leads): toolbar, статистика, фильтры, колонки.
 */
final class PartnerLeadsPageFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
        $this->grantPartnerLeadsView((int) $this->user->role_id);
    }

    public function test_index_passes_lead_stats_and_renders_toolbar(): void
    {
        PartnerLead::create([
            'name'   => 'Н1',
            'phone'  => '+7 900 111-11-11',
            'status' => 'new',
        ]);
        PartnerLead::create([
            'name'   => 'Н2',
            'phone'  => '+7 900 222-22-22',
            'status' => 'new',
        ]);
        PartnerLead::create([
            'name'   => 'П1',
            'phone'  => '+7 900 333-33-33',
            'status' => 'processing',
        ]);
        PartnerLead::create([
            'name'   => 'Продажа',
            'phone'  => '+7 900 444-44-44',
            'status' => 'sale',
        ]);

        $this->get(route('admin.partner-leads'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertViewHas('activeTab', 'leads')
            ->assertViewHas('leadStats', [
                'total'      => 4,
                'new'        => 2,
                'processing' => 1,
            ])
            ->assertSee('id="partnerLeadsReportToolbar"', false)
            ->assertSee('id="partnerLeadsFiltersCollapse"', false)
            ->assertSee('id="partnerLeadsFiltersToggle"', false)
            ->assertSee('partner-leads-stat-new', false)
            ->assertSee('partner-leads-stat-processing', false)
            ->assertSee('partner-leads-stat-total', false)
            ->assertSee('id="partner-leads-filters"', false)
            ->assertSee('id="plFilterStatusNew"', false)
            ->assertSee('id="plFilterStatusProcessing"', false)
            ->assertSee('id="columnsDropdownPartnerLeads"', false)
            ->assertSee('id="leads-table"', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('partner-leads-column-toggle', false);
    }

    public function test_datatable_stats_are_global_and_ignore_table_filters(): void
    {
        PartnerLead::create([
            'name'   => 'Новый лид',
            'phone'  => '+7 900 111-11-11',
            'status' => 'new',
        ]);
        PartnerLead::create([
            'name'   => 'Ещё новый',
            'phone'  => '+7 900 222-22-22',
            'status' => 'new',
        ]);
        PartnerLead::create([
            'name'   => 'В обработке',
            'phone'  => '+7 900 333-33-33',
            'status' => 'processing',
        ]);
        PartnerLead::create([
            'name'   => 'Продажа',
            'phone'  => '+7 900 444-44-44',
            'status' => 'sale',
        ]);

        $response = $this->getJson(route('admin.partner-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'statuses' => ['processing'],
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('В обработке', $response->json('data.0.name'));
        $this->assertSame([
            'total'      => 4,
            'new'        => 2,
            'processing' => 1,
        ], $response->json('stats'));
    }

    public function test_datatable_filters_by_multiple_statuses(): void
    {
        PartnerLead::create([
            'name'   => 'Новый',
            'phone'  => '+7 900 111-11-11',
            'status' => 'new',
        ]);
        PartnerLead::create([
            'name'   => 'Обработка',
            'phone'  => '+7 900 222-22-22',
            'status' => 'processing',
        ]);
        PartnerLead::create([
            'name'   => 'Продажа',
            'phone'  => '+7 900 333-33-33',
            'status' => 'sale',
        ]);

        $response = $this->getJson(route('admin.partner-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'statuses' => ['new', 'processing'],
        ]));

        $response->assertOk();
        $this->assertEquals(3, $response->json('recordsTotal'));
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $names = array_column($response->json('data'), 'name');
        $this->assertEqualsCanonicalizing(['Новый', 'Обработка'], $names);
    }

    public function test_datatable_search_by_name_email_and_phone(): void
    {
        PartnerLead::create([
            'name'    => 'УникальноеИмяXYZ',
            'phone'   => '+7 900 111-11-11',
            'email'   => 'unique_lead@example.test',
            'status'  => 'new',
        ]);
        PartnerLead::create([
            'name'   => 'Другой',
            'phone'  => '+7 900 222-22-22',
            'email'  => 'other@example.test',
            'status' => 'new',
        ]);

        $this->getJson(route('admin.partner-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'search' => ['value' => 'УникальноеИмяXYZ'],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'УникальноеИмяXYZ');

        $this->getJson(route('admin.partner-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'search' => ['value' => 'unique_lead@example.test'],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.email', 'unique_lead@example.test');

        $this->getJson(route('admin.partner-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'search' => ['value' => '900 111'],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.phone', '+7 900 111-11-11');
    }

    public function test_datatable_pagination_returns_correct_slice(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            PartnerLead::create([
                'name'   => 'Лид ' . $i,
                'phone'  => '+7 900 000-00-0' . $i,
                'status' => 'new',
            ]);
        }

        $response = $this->getJson(route('admin.partner-leads.data', [
            'draw'   => 1,
            'start'  => 2,
            'length' => 2,
        ]));

        $response->assertOk();
        $this->assertEquals(5, $response->json('recordsTotal'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_datatable_row_contains_expected_fields(): void
    {
        PartnerLead::create([
            'name'    => 'Полный лид',
            'phone'   => '+7 900 111-11-11',
            'email'   => 'full@example.test',
            'website' => 'https://example.com',
            'message' => 'Текст сообщения',
            'status'  => 'new',
            'comment' => 'Коммент',
        ]);

        $response = $this->getJson(route('admin.partner-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $row = $response->json('data.0');
        $this->assertSame([
            'id',
            'name',
            'phone',
            'email',
            'website',
            'message',
            'status',
            'status_label',
            'comment',
            'created_at',
        ], array_keys($row));
        $this->assertSame('Полный лид', $row['name']);
        $this->assertSame('Новый', $row['status_label']);
        $this->assertNotNull($row['created_at']);
    }

    public function test_datatable_excludes_soft_deleted_from_totals(): void
    {
        $active = PartnerLead::create([
            'name'   => 'Активный',
            'phone'  => '+7 900 111-11-11',
            'status' => 'new',
        ]);

        $deleted = PartnerLead::create([
            'name'   => 'Удалённый',
            'phone'  => '+7 900 222-22-22',
            'status' => 'new',
        ]);
        $deleted->delete();

        $response = $this->getJson(route('admin.partner-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsTotal'));
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($deleted->id, $ids);
    }

    public function test_columns_settings_rejects_invalid_payload(): void
    {
        $this->postJson(route('admin.partner-leads.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_columns_settings_persist_per_user(): void
    {
        $other = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->grantPartnerLeadsView((int) $other->role_id);

        $this->postJson(route('admin.partner-leads.columns-settings.save'), [
            'columns' => ['name' => true, 'phone' => false, 'email' => true],
        ])->assertOk();

        $this->actingAs($other);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.partner-leads.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'partner_leads_index')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame([
            'name'  => true,
            'phone' => false,
            'email' => true,
        ], $setting->columns);
    }

    private function grantPartnerLeadsView(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('partnerLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
