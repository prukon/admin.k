<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\User;
use App\Models\UserTableSetting;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт настроек видимости колонок таблицы заявок: JSON 200/422, ключи а не индексы.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see SchoolLeadsTableColumnsFeatureTest
 */
final class SchoolLeadsTableColumnsAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_get_returns_empty_object_when_user_has_no_saved_columns(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'school_leads_index')
            ->delete();

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_ajax_save_returns_success_json_and_persists_named_keys(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'school_leads_index')
            ->delete();

        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [
                'child_full_name' => true,
                'name'            => true,
                'status'          => true,
                'phone'           => false,
                'team_title'      => 1,
                'utm'             => 'false',
            ],
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'school_leads_index')
            ->firstOrFail();

        $this->assertSame([
            'child_full_name' => true,
            'name'            => true,
            'status'          => true,
            'phone'           => false,
            'team_title'      => true,
            'utm'             => false,
        ], $setting->columns);

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('phone', false)
            ->assertJsonPath('child_full_name', true);

        $payload = $this->getJson(route('admin.school-leads.columns-settings.get'))->json();
        $this->assertArrayNotHasKey(0, $payload);
        $this->assertArrayNotHasKey(1, $payload);
    }

    public function test_ajax_save_without_columns_returns_422_with_field_error(): void
    {
        $this->postJson(route('admin.school-leads.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_ajax_save_with_non_array_columns_returns_422_with_field_error(): void
    {
        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => 'phone',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_ajax_save_with_empty_columns_array_returns_422_with_field_error(): void
    {
        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_ajax_save_normalizes_unknown_boolean_strings_to_false(): void
    {
        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [
                'phone' => 'yes',
                'name'  => 'abc',
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'school_leads_index')
            ->firstOrFail();

        $this->assertSame([
            'phone' => true,
            'name'  => false,
        ], $setting->columns);
    }

    public function test_get_does_not_return_another_users_column_visibility(): void
    {
        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->user->role_id,
        ]);

        UserTableSetting::updateOrCreate(
            ['user_id' => $other->id, 'table_key' => 'school_leads_index'],
            ['columns' => ['phone' => false, 'name' => false]]
        );

        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'school_leads_index'],
            ['columns' => ['phone' => true, 'child_full_name' => true]]
        );

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([
                'phone'           => true,
                'child_full_name' => true,
            ]);
    }

    public function test_get_returns_empty_array_when_stored_columns_are_not_an_array(): void
    {
        DB::table('user_table_settings')->updateOrInsert(
            [
                'user_id'   => $this->user->id,
                'table_key' => 'school_leads_index',
            ],
            [
                'columns'    => json_encode('broken', JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);
    }
}
