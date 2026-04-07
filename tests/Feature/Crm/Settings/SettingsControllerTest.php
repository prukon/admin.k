<?php

namespace Tests\Feature\Crm\Settings;

use App\Models\MenuItem;
use App\Models\MyLog;
use App\Models\PartnerSocialLink;
use App\Models\Setting;
use App\Models\SocialNetwork;
use App\Models\TinkoffPayout;
use App\Models\User;
use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        $this->grantPermissionToCurrentRole('settings.registration.manage');

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

    public function test_registration_activity_patch_requires_registration_manage_permission(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $this->patchJson(route('registrationActivity'), [
            'isRegistrationActivity' => true,
        ])->assertForbidden();
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

    public function test_queues_page_requires_separate_permission(): void
    {
        // Есть доступ в "Настройки", но нет отдельного права "Очереди".
        $this->grantPermissionToCurrentRole('settings.view');

        $this->asUserWithoutPermission('settings.queues.view');
        $this->grantPermissionToCurrentRole('settings.view');

        $this->get(route('admin.setting.queues'))->assertStatus(403);
    }

    public function test_queues_status_requires_permission_and_returns_json(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');

        $this->asUserWithoutPermission('settings.queues.view');
        $this->grantPermissionToCurrentRole('settings.view');
        $this->get(route('admin.setting.queues.status'))->assertStatus(403);

        $this->actingAs($this->user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');

        $this->get(route('admin.setting.queues.status'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'jobs_count',
                    'failed_jobs_count',
                    'worker_status',
                    'scheduler_status',
                    'overdue_scheduled_payouts_count',
                    'overdue_scheduled_payouts_sample',
                    'job_groups',
                ],
            ]);
    }

    public function test_queues_status_includes_overdue_scheduled_payouts_for_current_partner(): void
    {
        $this->actingAs($this->user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');

        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'deal_id' => 'test-deal-overdue-' . uniqid(),
            'amount' => 100,
            'is_final' => false,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->subHour(),
            'completed_at' => null,
        ]);

        $this->get(route('admin.setting.queues.status'))
            ->assertOk()
            ->assertJsonPath('data.overdue_scheduled_payouts_count', 1);
    }

    public function test_queues_status_reflects_scheduler_heartbeat(): void
    {
        $this->actingAs($this->user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');

        SchedulerHeartbeat::touch();

        $this->get(route('admin.setting.queues.status'))
            ->assertOk()
            ->assertJsonPath('data.scheduler_status.code', 'alive');
    }

    public function test_queues_restart_requires_manage_permission(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');

        $this->asUserWithoutPermission('settings.queues.manage');
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');

        $this->postJson(route('admin.setting.queues.restart'))->assertStatus(403);
    }

    public function test_queues_page_is_available_with_permissions_and_returns_200(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');

        $this->get(route('admin.setting.queues'))
            ->assertOk()
            ->assertSee('Очереди');
    }

    public function test_queues_status_returns_expected_metrics_and_200_for_allowed_user(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{"displayName":"App\\\\Jobs\\\\SendCloudKassirReceiptJob"}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->subMinutes(2)->timestamp,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{"displayName":"App\\\\Jobs\\\\TinkoffProcessRefundJob"}',
            'exception' => 'Test failure',
            'failed_at' => now(),
        ]);

        Setting::setInt('queue_monitor_last_success_at', now()->subMinute()->timestamp, null);
        Setting::setInt('queue_monitor_last_failed_at', now()->subSeconds(30)->timestamp, null);
        Cache::put('queue:monitor:last_heartbeat_at', now()->subSeconds(20)->timestamp, now()->addMinutes(5));

        $this->get(route('admin.setting.queues.status'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.jobs_count', 1)
            ->assertJsonPath('data.failed_jobs_count', 1)
            ->assertJsonPath('data.worker_status.code', 'alive');
    }

    public function test_queues_logs_returns_queue_channel_file_and_200_for_allowed_user(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');

        $dir = storage_path('logs');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $logPath = storage_path('logs/queue.log');
        File::put($logPath, "line-one\nline-two\n");

        $this->get(route('admin.setting.queues.logs'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lines.0', 'line-one');
    }

    public function test_queues_restart_returns_200_for_user_with_manage_permission(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');
        $this->grantPermissionToCurrentRole('settings.queues.manage');

        $response = $this->postJson(route('admin.setting.queues.restart'));
        $response->assertOk()
            ->assertJsonPath('success', true);

        // Дополнительно убеждаемся, что установлен "restart timestamp" для воркеров.
        $this->assertNotNull(Cache::get('illuminate:queue:restart'));
    }

    public function test_queues_status_does_not_count_foreign_partner_overdue_scheduled_payouts(): void
    {
        $this->actingAs($this->user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');

        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->foreignPartner->id,
            'deal_id' => 'foreign-overdue-' . uniqid(),
            'amount' => 100,
            'is_final' => false,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->subHour(),
            'completed_at' => null,
        ]);

        $this->get(route('admin.setting.queues.status'))
            ->assertOk()
            ->assertJsonPath('data.overdue_scheduled_payouts_count', 0);
    }

    /**
     * Сводная проверка: страница «Очереди» и все связанные JSON/действия отвечают 200 при полном наборе прав.
     */
    public function test_queue_monitoring_page_and_actions_all_return_200_with_permissions(): void
    {
        $this->actingAs($this->user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('settings.queues.view');
        $this->grantPermissionToCurrentRole('settings.queues.manage');

        $dir = storage_path('logs');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        File::put(storage_path('logs/queue.log'), "smoke-line\n");

        $this->get(route('admin.setting.queues'))->assertOk();

        $this->get(route('admin.setting.queues.status'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->get(route('admin.setting.queues.logs'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson(route('admin.setting.queues.restart'))
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}

