<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт GET/POST /admin/settings/tbank-commissions/columns-settings:
 * JSON 200/422, ключи а не индексы, page_length не затирает колонки.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see TbankCommissionsColumnsSettingsFeatureTest
 */
final class TbankCommissionsColumnsSettingsAjaxContractFeatureTest extends CrmTestCase
{
    private const TABLE_KEY = 'tbank_commissions_index';

    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_get_returns_empty_object_when_user_has_no_saved_columns(): void
    {
        UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->delete();

        $this->getJson(route('admin.setting.tbankCommissions.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_ajax_save_returns_success_json_and_persists_named_keys(): void
    {
        UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->delete();

        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'columns' => [
                'partner_title' => true,
                'method' => false,
                'actions' => 1,
                'auto_payout' => 'false',
            ],
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->firstOrFail();

        $this->assertSame([
            'partner_title' => true,
            'method' => false,
            'actions' => true,
            'auto_payout' => false,
        ], $setting->columns);

        $payload = $this->getJson(route('admin.setting.tbankCommissions.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('method', false)
            ->assertJsonPath('partner_title', true)
            ->json();

        $this->assertArrayNotHasKey('page_length', $payload);
        $this->assertArrayNotHasKey(0, $payload);
        $this->assertArrayNotHasKey(1, $payload);
    }

    public function test_ajax_save_without_columns_returns_422_with_field_error(): void
    {
        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns'])
            ->assertJsonPath('errors.columns.0', 'Передайте настройки колонок.');
    }

    public function test_ajax_save_with_non_array_columns_returns_422_with_field_error(): void
    {
        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'columns' => 'partner_title',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_ajax_save_with_empty_columns_array_returns_422_with_field_error(): void
    {
        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'columns' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_ajax_save_normalizes_unknown_boolean_strings_to_false(): void
    {
        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'columns' => [
                'method' => 'yes',
                'actions' => 'abc',
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->firstOrFail();

        $this->assertSame([
            'method' => true,
            'actions' => false,
        ], $setting->columns);
    }

    public function test_ajax_save_page_length_does_not_wipe_hidden_columns(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => self::TABLE_KEY],
            ['columns' => ['method' => false, 'partner_title' => true]]
        );

        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'page_length' => 100,
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->firstOrFail();

        $this->assertSame(100, (int) $setting->page_length);
        $this->assertSame(false, $setting->columns['method'] ?? null);
        $this->assertSame(true, $setting->columns['partner_title'] ?? null);

        $payload = $this->getJson(route('admin.setting.tbankCommissions.columns-settings.get'))
            ->assertOk()
            ->json();
        $this->assertArrayNotHasKey('page_length', $payload);
        $this->assertFalse($payload['method']);
    }

    public function test_empty_columns_together_with_page_length_does_not_wipe_hidden_columns(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => self::TABLE_KEY],
            [
                'columns' => ['method' => false],
                'page_length' => 10,
            ]
        );

        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'columns' => [],
            'page_length' => 50,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->firstOrFail();
        $this->assertSame(10, (int) $setting->page_length);
        $this->assertFalse($setting->columns['method'] ?? null);
    }

    public function test_ajax_invalid_page_length_returns_422_with_field_error(): void
    {
        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'page_length' => 15,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page_length'])
            ->assertJsonPath('errors.page_length.0', 'Можно показать 10, 20, 50 или 100 записей.');

        foreach ([0, -1, 25, 'abc', 10.5] as $invalid) {
            $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
                'page_length' => $invalid,
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['page_length']);
        }

        $this->assertSame(
            0,
            UserTableSetting::query()
                ->where('user_id', $this->user->id)
                ->where('table_key', self::TABLE_KEY)
                ->count()
        );
    }

    public function test_ajax_save_accepts_each_allowed_page_length(): void
    {
        foreach (UserTableSetting::PAGE_LENGTHS as $length) {
            $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
                'page_length' => $length,
            ])
                ->assertOk()
                ->assertJson(['success' => true]);

            $this->assertSame(
                $length,
                UserTableSetting::query()
                    ->where('user_id', $this->user->id)
                    ->where('table_key', self::TABLE_KEY)
                    ->value('page_length')
            );
        }
    }

    public function test_changing_show_by_does_not_persist_page_number(): void
    {
        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'page_length' => 20,
            'start' => 40,
            'draw' => 3,
        ])->assertOk();

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->firstOrFail();

        $this->assertSame(20, (int) $setting->page_length);
        $this->assertArrayNotHasKey('start', $setting->getAttributes());
        $this->assertArrayNotHasKey('draw', $setting->getAttributes());
    }

    public function test_ajax_save_page_length_without_columns_leaves_columns_empty_and_get_omits_page_length(): void
    {
        UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->delete();

        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'page_length' => 20,
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->firstOrFail();
        $this->assertSame(20, (int) $setting->page_length);
        $this->assertNull($setting->columns);

        $payload = $this->getJson(route('admin.setting.tbankCommissions.columns-settings.get'))
            ->assertOk()
            ->json();
        $this->assertSame([], $payload);
        $this->assertArrayNotHasKey('page_length', $payload);
    }

    public function test_get_does_not_return_another_users_column_visibility(): void
    {
        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
        ]);

        UserTableSetting::updateOrCreate(
            ['user_id' => $other->id, 'table_key' => self::TABLE_KEY],
            ['columns' => ['method' => false, 'partner_title' => false]]
        );
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => self::TABLE_KEY],
            ['columns' => ['method' => true, 'actions' => true]]
        );

        $this->getJson(route('admin.setting.tbankCommissions.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([
                'method' => true,
                'actions' => true,
            ]);
    }

    public function test_get_returns_empty_array_when_stored_columns_are_not_an_array(): void
    {
        DB::table('user_table_settings')->updateOrInsert(
            [
                'user_id' => $this->user->id,
                'table_key' => self::TABLE_KEY,
            ],
            [
                'columns' => json_encode('broken', JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->getJson(route('admin.setting.tbankCommissions.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);
    }
}
