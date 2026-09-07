<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P1] Доступ к columns-settings комиссий Т‑Банк: гость / без права / с settings.commission;
 * PUT/PATCH/DELETE → 404/405; GET как web — JSON, не пустой 200.
 *
 * @see TbankCommissionsColumnsSettingsAjaxContractFeatureTest
 * @see TbankCommissionsPageFullAccessFeatureTest
 */
final class TbankCommissionsColumnsSettingsFullAccessFeatureTest extends CrmTestCase
{
    private const TABLE_KEY = 'tbank_commissions_index';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function columnsSettingsRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.setting.tbankCommissions'),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.setting.tbankCommissions.columns-settings.get'),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.setting.tbankCommissions.columns-settings.save'),
                'data' => ['columns' => ['partner_title' => true]],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.setting.tbankCommissions.columns-settings.save'),
                'data' => ['page_length' => 20],
            ],
        ];
    }

    public function test_guest_is_denied_on_columns_settings_endpoints_without_500(): void
    {
        Auth::logout();

        foreach ($this->columnsSettingsRoutes() as $item) {
            $response = $this->call($item['method'], $item['url'], $item['data'] ?? []);
            $this->assertNotSame(500, $response->getStatusCode(), $item['method'].' '.$item['url']);
            $this->assertNotSame(200, $response->getStatusCode(), $item['method'].' '.$item['url']);
            $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        }

        $this->assertSame(
            0,
            UserTableSetting::query()->where('table_key', self::TABLE_KEY)->count()
        );
    }

    public function test_user_without_permission_gets_403_on_columns_settings_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('settings.commission', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->columnsSettingsRoutes() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);
            $response->assertForbidden();
        }
    }

    public function test_viewer_with_settings_commission_can_save_show_by_and_sees_it_after_reload(): void
    {
        $actor = $this->createUserWithoutPermission('settings.commission', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSettingsCommission($actor);

        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'page_length' => 50,
            'columns' => ['method' => false, 'actions' => true],
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $html = $this->get(route('admin.setting.tbankCommissions'))
            ->assertOk()
            ->assertViewHas('tbankCommissionsPageLength', 50)
            ->getContent();

        $this->assertStringContainsString('persistPageLength: true', $html);
        $this->assertMatchesRegularExpression('/pageLength:\s*50\b/', $html);
        $this->assertStringContainsString('id="tbankCommissionsColumnsDropdown"', $html);

        $payload = $this->getJson(route('admin.setting.tbankCommissions.columns-settings.get'))
            ->assertOk()
            ->json();
        $this->assertArrayNotHasKey('page_length', $payload);
        $this->assertFalse($payload['method']);
        $this->assertTrue($payload['actions']);
    }

    public function test_admin_can_save_show_by_via_ajax_and_non_ajax(): void
    {
        $this->asAdmin();
        $this->grantSettingsCommission($this->user);

        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'page_length' => 20,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $nonAjax = $this->from(route('admin.setting.tbankCommissions'))
            ->post(route('admin.setting.tbankCommissions.columns-settings.save'), [
                'page_length' => 100,
            ]);

        $this->assertNotSame(500, $nonAjax->getStatusCode());
        $this->assertSame(200, $nonAjax->getStatusCode());
        $this->assertNotSame('', trim((string) $nonAjax->getContent()));
        $nonAjax->assertJson(['success' => true]);

        $this->assertSame(
            100,
            UserTableSetting::query()
                ->where('user_id', $this->user->id)
                ->where('table_key', self::TABLE_KEY)
                ->value('page_length')
        );
    }

    public function test_unsupported_methods_on_columns_settings_do_not_save_and_do_not_500(): void
    {
        $this->asSuperadmin();

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->json($method, route('admin.setting.tbankCommissions.columns-settings.save'), [
                'page_length' => 50,
            ]);
            $this->assertNotSame(500, $response->getStatusCode(), $method);
            $this->assertContains($response->getStatusCode(), [404, 405], $method);
        }

        $this->assertSame(
            0,
            UserTableSetting::query()
                ->where('user_id', $this->user->id)
                ->where('table_key', self::TABLE_KEY)
                ->count()
        );
    }

    public function test_get_columns_settings_as_web_request_is_json_not_empty_200(): void
    {
        $this->asSuperadmin();

        $response = $this->get(route('admin.setting.tbankCommissions.columns-settings.get'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('page_length', $payload);
    }

    public function test_setpartner_blocks_user_without_partner_id_on_columns_settings(): void
    {
        $u = User::factory()->create(['partner_id' => null]);
        $this->actingAs($u);
        $this->withSession([]);

        $resp = $this->get(route('admin.setting.tbankCommissions.columns-settings.get'));
        $resp->assertStatus(302);
        $this->assertGuest();
        $resp->assertSessionHasErrors([
            'email' => 'Ваша организация недоступна.',
        ]);
    }

    private function grantSettingsCommission(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('settings.commission'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
