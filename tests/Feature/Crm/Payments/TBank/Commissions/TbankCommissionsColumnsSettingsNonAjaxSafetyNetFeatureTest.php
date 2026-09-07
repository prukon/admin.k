<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P1] Non-AJAX safety-net для сохранения колонок комиссий Т‑Банк.
 * Успешный save без X-Requested-With возвращает JSON (не пустой 200 и не 500);
 * валидация — 302 с errors[field].
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see TbankCommissionsColumnsSettingsAjaxContractFeatureTest
 */
final class TbankCommissionsColumnsSettingsNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_non_ajax_save_persists_visibility_and_does_not_return_empty_200(): void
    {
        UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->delete();

        $response = $this->from(route('admin.setting.tbankCommissions'))
            ->post(route('admin.setting.tbankCommissions.columns-settings.save'), [
                'columns' => [
                    'partner_title' => '1',
                    'method' => '0',
                    'actions' => '1',
                ],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->firstOrFail();

        $this->assertSame([
            'partner_title' => true,
            'method' => false,
            'actions' => true,
        ], $setting->columns);
    }

    public function test_non_ajax_save_page_length_returns_json_and_invalid_redirects_with_field_error(): void
    {
        $ok = $this->from(route('admin.setting.tbankCommissions'))
            ->post(route('admin.setting.tbankCommissions.columns-settings.save'), [
                'page_length' => '50',
            ]);

        $this->assertSame(200, $ok->getStatusCode());
        $this->assertNotSame('', trim((string) $ok->getContent()));
        $ok->assertJson(['success' => true]);
        $this->assertSame(
            50,
            UserTableSetting::query()
                ->where('user_id', $this->user->id)
                ->where('table_key', self::TABLE_KEY)
                ->value('page_length')
        );

        $this->from(route('admin.setting.tbankCommissions'))
            ->post(route('admin.setting.tbankCommissions.columns-settings.save'), [
                'page_length' => 7,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['page_length']);
    }

    public function test_non_ajax_save_without_columns_redirects_back_with_field_error(): void
    {
        UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->delete();

        $this->from(route('admin.setting.tbankCommissions'))
            ->post(route('admin.setting.tbankCommissions.columns-settings.save'), [])
            ->assertStatus(302)
            ->assertSessionHasErrors(['columns']);

        $this->assertSame(
            0,
            UserTableSetting::query()
                ->where('user_id', $this->user->id)
                ->where('table_key', self::TABLE_KEY)
                ->count()
        );
    }

    public function test_non_ajax_save_with_invalid_columns_redirects_back_with_field_error(): void
    {
        $this->from(route('admin.setting.tbankCommissions'))
            ->post(route('admin.setting.tbankCommissions.columns-settings.save'), [
                'columns' => 'not-array',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['columns']);
    }

    public function test_guest_non_ajax_save_is_denied_and_does_not_persist(): void
    {
        Auth::logout();

        $response = $this->from(route('admin.setting.tbankCommissions'))
            ->post(route('admin.setting.tbankCommissions.columns-settings.save'), [
                'columns' => ['method' => false],
            ]);

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(
            0,
            UserTableSetting::query()->where('table_key', self::TABLE_KEY)->count()
        );
    }

    public function test_user_without_permission_non_ajax_save_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('settings.commission', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->from(route('admin.setting.tbankCommissions'))
            ->post(route('admin.setting.tbankCommissions.columns-settings.save'), [
                'columns' => ['method' => false],
            ])
            ->assertForbidden();
    }
}
