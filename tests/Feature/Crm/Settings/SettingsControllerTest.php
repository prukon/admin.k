<?php

namespace Tests\Feature\Crm\Settings;

use App\Models\MenuItem;
use App\Models\MyLog;
use App\Models\PartnerSocialLink;
use App\Models\Setting;
use App\Models\SocialNetwork;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class SettingsControllerTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Все маршруты из этого блока находятся под middleware ['auth','2fa'].
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermissionToCurrentRole(string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);
        $now = now();

        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $permId,
            ],
            [
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function asUserWithoutPermission(string $permissionName): User
    {
        $u = $this->createUserWithoutPermission($permissionName, $this->partner);
        $this->actingAs($u);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        return $u;
    }

    public function test_settings_page_requires_settings_view_permission(): void
    {
        $this->asUserWithoutPermission('settings.view');

        $this->get(route('admin.setting.setting'))->assertStatus(403);
    }

    public function test_settings_page_redirects_back_when_user_has_no_partner(): void
    {
        $u = User::factory()->create(['partner_id' => null]);
        $this->actingAs($u);
        $this->flushSession(); // чтобы SetPartner точно не имел current_partner

        $res = $this->from('/admin')->get(route('admin.setting.setting'));
        $res->assertStatus(302);
        $this->assertGuest();
        $res->assertSessionHasErrors([
            'email' => 'Ваша организация недоступна.',
        ]);
    }

    public function test_settings_page_renders_and_initializes_partner_social_links_for_enabled_networks(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        // В тестовой БД справочник может уже быть заполнен миграцией.
        // Добавим заведомо выключенную соцсеть и убедимся, что для неё не создадут ссылку.
        $disabled = SocialNetwork::query()->create([
            'code' => 'test_disabled_' . uniqid(),
            'title' => 'Disabled',
            'domain' => 'disabled.test',
            'icon' => 'fa fa-ban',
            'sort' => 999,
            'is_enabled' => 0,
        ]);

        $enabledCount = SocialNetwork::query()->where('is_enabled', 1)->count();

        // 1-й заход — создаёт недостающие
        $res = $this->get(route('admin.setting.setting'));
        $res->assertOk();
        $res->assertViewHas('socialSettingsItems');

        // выключенная соцсеть не инициализируется
        $this->assertDatabaseMissing('partner_social_links', [
            'partner_id' => $this->partner->id,
            'social_network_id' => $disabled->id,
        ]);

        // Создано ровно столько ссылок, сколько включённых соцсетей в справочнике
        $count = PartnerSocialLink::query()->where('partner_id', $this->partner->id)->count();
        $this->assertSame($enabledCount, $count);

        // 2-й заход — без дублей
        $this->get(route('admin.setting.setting'))->assertOk();
        $count2 = PartnerSocialLink::query()->where('partner_id', $this->partner->id)->count();
        $this->assertSame($enabledCount, $count2);
    }

    public function test_registration_activity_patch_updates_setting_and_creates_log(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $beforeLogs = MyLog::query()->where('action', 70)->where('partner_id', $this->partner->id)->count();

        $resp = $this->patchJson(route('registrationActivity'), [
            'isRegistrationActivity' => true,
        ]);

        $resp->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('settings', [
            'name' => 'registrationActivity',
            'partner_id' => $this->partner->id,
            'status' => 1,
        ]);

        $afterLogs = MyLog::query()->where('action', 70)->where('partner_id', $this->partner->id)->count();
        $this->assertSame($beforeLogs + 1, $afterLogs);
    }

    public function test_text_for_users_accepts_json_and_creates_log(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $beforeLogs = MyLog::query()->where('action', 70)->where('partner_id', $this->partner->id)->count();

        $resp = $this->postJson(route('textForUsers'), [
            'textForUsers' => 'Hello users',
        ]);

        $resp->assertOk()->assertJsonPath('success', true)->assertJsonPath('textForUsers', 'Hello users');

        $this->assertDatabaseHas('settings', [
            'name' => 'textForUsers',
            'partner_id' => $this->partner->id,
            'text' => 'Hello users',
        ]);

        $afterLogs = MyLog::query()->where('action', 70)->where('partner_id', $this->partner->id)->count();
        $this->assertSame($beforeLogs + 1, $afterLogs);
    }

    public function test_text_for_users_accepts_form_data_too(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $resp = $this->post(route('textForUsers'), [
            'textForUsers' => 'Form value',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('settings', [
            'name' => 'textForUsers',
            'partner_id' => $this->partner->id,
            'text' => 'Form value',
        ]);
    }

    public function test_save_menu_items_validates_and_returns_bracket_keys(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $resp = $this->postJson(route('settings.saveMenuItems'), [
            'menu_items' => [
                'new_1' => [
                    'name' => '',
                    'link' => 'notaurl',
                    'target_blank' => 1,
                ],
            ],
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('success', false);
        $resp->assertJsonPath('errors.menu_items[new_1][name].0', 'Заполните название.');
        $resp->assertJsonPath('errors.menu_items[new_1][link].0', 'Введите корректный URL.');
    }

    public function test_save_menu_items_creates_new_items_and_logs_once(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $beforeLogs = MyLog::query()->where('action', 70)->where('partner_id', $this->partner->id)->count();

        $resp = $this->postJson(route('settings.saveMenuItems'), [
            'menu_items' => [
                'new_1' => [
                    'name' => 'Главная',
                    'link' => '/home',
                    'target_blank' => true,
                ],
            ],
        ]);

        $resp->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('menu_items', [
            'partner_id' => $this->partner->id,
            'name' => 'Главная',
            'link' => '/home',
            'target_blank' => 1,
        ]);

        $afterLogs = MyLog::query()->where('action', 70)->where('partner_id', $this->partner->id)->count();
        $this->assertSame($beforeLogs + 1, $afterLogs);
    }

    public function test_save_menu_items_updates_existing_item_only_for_current_partner(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $item = MenuItem::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Old',
            'link' => '/old',
            'target_blank' => 0,
        ]);

        $resp = $this->postJson(route('settings.saveMenuItems'), [
            'menu_items' => [
                (string)$item->id => [
                    'name' => 'New',
                    'link' => 'https://example.test',
                    'target_blank' => false,
                ],
            ],
        ]);

        $resp->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'partner_id' => $this->partner->id,
            'name' => 'New',
            'link' => 'https://example.test',
            'target_blank' => 0,
        ]);
    }

    public function test_save_menu_items_returns_404_when_updating_foreign_or_missing_id(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $resp = $this->postJson(route('settings.saveMenuItems'), [
            'menu_items' => [
                '999999' => [
                    'name' => 'X',
                    'link' => '/x',
                    'target_blank' => false,
                ],
            ],
        ]);

        $resp->assertStatus(404);
        $resp->assertJsonPath('success', false);
        $resp->assertJsonPath('message', 'Not found');
    }

    public function test_save_menu_items_deletes_only_partner_items(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $toDelete = MenuItem::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Del',
            'link' => '/del',
            'target_blank' => 0,
        ]);

        $resp = $this->postJson(route('settings.saveMenuItems'), [
            'menu_items' => [],
            'deleted_items' => [$toDelete->id],
        ]);

        $resp->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('menu_items', ['id' => $toDelete->id]);
    }

    public function test_save_social_items_updates_partner_links_validates_and_logs(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $sn = SocialNetwork::query()->where('is_enabled', 1)->firstOrFail();

        $link = PartnerSocialLink::query()->create([
            'partner_id' => $this->partner->id,
            'social_network_id' => $sn->id,
            'url' => null,
            'is_enabled' => 1,
            'sort' => 10,
        ]);

        $beforeLogs = MyLog::query()->where('action', 70)->where('partner_id', $this->partner->id)->count();

        $resp = $this->postJson(route('settings.saveSocialItems'), [
            'partner_social_links' => [
                (string)$link->id => [
                    'url' => 'https://vk.com/test',
                    'is_enabled' => true,
                    'sort' => 5,
                ],
            ],
        ]);

        $resp->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('partner_social_links', [
            'id' => $link->id,
            'partner_id' => $this->partner->id,
            'url' => 'https://vk.com/test',
            'is_enabled' => 1,
            'sort' => 5,
        ]);

        $afterLogs = MyLog::query()->where('action', 70)->where('partner_id', $this->partner->id)->count();
        $this->assertSame($beforeLogs + 1, $afterLogs);
    }

    public function test_save_social_items_returns_422_with_bracket_key_on_bad_url(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $sn = SocialNetwork::query()->where('is_enabled', 1)->firstOrFail();

        $link = PartnerSocialLink::query()->create([
            'partner_id' => $this->partner->id,
            'social_network_id' => $sn->id,
            'url' => null,
            'is_enabled' => 1,
            'sort' => 10,
        ]);

        $resp = $this->postJson(route('settings.saveSocialItems'), [
            'partner_social_links' => [
                (string)$link->id => [
                    'url' => 'bad',
                    'is_enabled' => true,
                    'sort' => 0,
                ],
            ],
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('errors.partner_social_links[' . $link->id . '][url].0', 'Введите корректный URL.');
    }

    public function test_save_social_items_returns_404_when_id_not_belongs_to_partner(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $sn = SocialNetwork::query()->where('is_enabled', 1)->firstOrFail();

        $foreignLink = PartnerSocialLink::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'social_network_id' => $sn->id,
            'url' => 'https://vk.com/foreign',
            'is_enabled' => 1,
            'sort' => 10,
        ]);

        $resp = $this->postJson(route('settings.saveSocialItems'), [
            'partner_social_links' => [
                (string)$foreignLink->id => [
                    'url' => 'https://vk.com/attack',
                    'is_enabled' => true,
                    'sort' => 1,
                ],
            ],
        ]);

        $resp->assertStatus(404);
        $resp->assertJsonPath('success', false);
        $resp->assertJsonPath('message', 'Not found');
    }

    public function test_force2fa_admins_requires_gate_and_updates_global_setting(): void
    {
        // 1) без прав — 403
        $this->asUserWithoutPermission('settings.force2fa.admins');
        $this->postJson(route('settings.force2fa.admins'), ['force2faAdmins' => 1])->assertStatus(403);

        // 2) выдаём право роли — 200 и запись в settings (partner_id = null)
        $this->actingAs($this->user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.force2fa.admins');

        $resp = $this->postJson(route('settings.force2fa.admins'), ['force2faAdmins' => 1]);
        $resp->assertOk()->assertJsonPath('success', true)->assertJsonPath('value', true);

        $this->assertDatabaseHas('settings', [
            'name' => 'force_2fa_admins',
            'partner_id' => null,
            'status' => 1,
        ]);

        // переключаем обратно
        $resp2 = $this->postJson(route('settings.force2fa.admins'), ['force2faAdmins' => 0]);
        $resp2->assertOk()->assertJsonPath('success', true)->assertJsonPath('value', false);

        $this->assertDatabaseHas('settings', [
            'name' => 'force_2fa_admins',
            'partner_id' => null,
            'status' => 0,
        ]);
    }

    public function test_logs_data_requires_viewing_all_logs_permission(): void
    {
        $this->asUserWithoutPermission('viewing.all.logs');
        $this->get(route('settings.logs.data'))->assertStatus(403);

        $this->actingAs($this->user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->get(route('settings.logs.data'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');
    }
}

