<?php

namespace Tests\Feature\Crm\Partners;

use App\Models\MyLog;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

class PartnerControllerTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Не завязываемся на 2FA-состояние (в некоторых окружениях оно может быть принудительным).
        $this->withSession(['2fa:passed' => true]);

        // Реальные права: суперадмин проходит любые can:* (Gate::before в AuthServiceProvider)
        $this->asSuperadmin();

        // Стабильно фиксируем текущего партнёра (SetPartner обычно делает то же)
        $this->withSession(['current_partner' => $this->partner->id]);
    }

    private function validPartnerPayload(array $overrides = []): array
    {
        $email = $overrides['email'] ?? ('partner_' . Str::lower(Str::random(8)) . '@example.test');

        return array_merge([
            'business_type' => 'company',
            'title' => 'Тестовый партнёр',
            'organization_name' => 'ООО Тест',
            'tax_id' => '1234567890',
            'kpp' => '123456789',
            'registration_number' => '1234567890123',
            'sms_name' => 'TESTPARTNER',
            'city' => 'СПб',
            'zip' => '197350',
            'address' => 'Невский пр., 1',
            'phone' => '+79990001122',
            'email' => $email,
            'website' => 'https://example.test',
            'bank_name' => 'Банк',
            'bank_bik' => '123456789',
            'bank_account' => '12345678901234567890',
            'order_by' => 10,
            'is_enabled' => true,
            'ceo' => [
                'lastName' => 'Иванов',
                'firstName' => 'Иван',
                'middleName' => 'Иванович',
                'phone' => '+79991112233',
            ],
        ], $overrides);
    }

    public function test_guest_cannot_access_partner_routes(): void
    {
        auth()->logout();

        $partner = Partner::factory()->create();

        // HTML страница чаще редиректит на логин
        $this->get(route('admin.partner.index'))->assertStatus(302);

        // JSON ручки для гостя обычно дают 401
        $this->postJson(route('admin.partner.store'), [])->assertStatus(401);
        $this->getJson(route('admin.partner.edit', $partner))->assertStatus(401);
        $this->patchJson(route('admin.partner.update', $partner), [])->assertStatus(401);
        $this->deleteJson(route('admin.partner.delete', $partner))->assertStatus(401);
        $this->getJson(route('logs.data.partner'))->assertStatus(401);
    }

    public function test_user_without_partner_view_permission_gets_403_for_all_partner_routes(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view');

        $this->actingAs($actor);
        $this->withSession(['current_partner' => $actor->partner_id, '2fa:passed' => true]);

        $partner = Partner::factory()->create();

        $this->get(route('admin.partner.index'))->assertStatus(403);
        $this->postJson(route('admin.partner.store'), $this->validPartnerPayload())->assertStatus(403);
        $this->getJson(route('admin.partner.edit', $partner))->assertStatus(403);
        $this->patchJson(route('admin.partner.update', $partner), $this->validPartnerPayload())->assertStatus(403);
        $this->deleteJson(route('admin.partner.delete', $partner))->assertStatus(403);
        $this->getJson(route('logs.data.partner'))->assertStatus(403);
    }

    public function test_setpartner_blocks_user_without_partner(): void
    {
        $u = User::factory()->create(['partner_id' => null]);

        $this->actingAs($u);
        $this->flushSession(); // убираем current_partner, чтобы SetPartner точно отработал

        $res = $this->from('/admin')->get(route('admin.partner.index'));

        $res->assertStatus(302);
        $res->assertSessionHasErrors(['partner']);
    }

    public function test_setpartner_blocks_when_session_current_partner_points_to_missing_partner_for_superadmin(): void
    {
        $this->asSuperadmin();

        $res = $this->from('/admin')
            ->withSession(['current_partner' => 999999, '2fa:passed' => true])
            ->get(route('admin.partner.index'));

        $res->assertStatus(302);
        $res->assertSessionHasErrors(['partner']);
    }

    public function test_index_ok_for_superadmin(): void
    {
        $this->asSuperadmin();

        $this->get(route('admin.partner.index'))
            ->assertOk()
            ->assertSee('Партнеры', escape: false);
    }

    public function test_store_creates_partner_and_writes_log_scoped_by_current_partner(): void
    {
        $this->asSuperadmin();

        $beforeLogs = MyLog::query()
            ->where('type', 80)
            ->where('action', 81)
            ->where('partner_id', $this->partner->id)
            ->count();

        $payload = $this->validPartnerPayload([
            'title' => 'Партнёр для store',
        ]);

        $res = $this->postJson(route('admin.partner.store'), $payload)
            ->assertStatus(201)
            ->assertJsonPath('message', 'Партнёр успешно создан');

        $createdId = (int) $res->json('partner.id');
        $this->assertGreaterThan(0, $createdId);

        $this->assertDatabaseHas('partners', [
            'id' => $createdId,
            'email' => $payload['email'],
            'title' => $payload['title'],
        ]);

        $afterLogs = MyLog::query()
            ->where('type', 80)
            ->where('action', 81)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->assertSame($beforeLogs + 1, $afterLogs);

        $this->assertDatabaseHas('my_logs', [
            'type' => 80,
            'action' => 81,
            'partner_id' => $this->partner->id,
            'author_id' => $this->user->id,
            'target_type' => Partner::class,
            'target_id' => $createdId,
            'target_label' => 'Партнёр для store',
        ]);
    }

    public function test_edit_returns_ceo_in_camelcase_even_when_stored_in_snake_case(): void
    {
        $this->asSuperadmin();

        $partner = Partner::factory()->create([
            'ceo' => [
                'last_name' => 'Петров',
                'first_name' => 'Пётр',
                'middle_name' => 'Петрович',
                'phone' => '+70000000000',
            ],
        ]);

        $this->getJson(route('admin.partner.edit', $partner))
            ->assertOk()
            ->assertJsonPath('id', $partner->id)
            ->assertJsonPath('ceo.lastName', 'Петров')
            ->assertJsonPath('ceo.firstName', 'Пётр')
            ->assertJsonPath('ceo.middleName', 'Петрович')
            ->assertJsonPath('ceo.phone', '+70000000000');
    }

    public function test_update_updates_partner_and_writes_log_scoped_by_current_partner(): void
    {
        $this->asSuperadmin();

        $partner = Partner::factory()->create([
            'title' => 'Старое название',
            'email' => 'old_' . Str::lower(Str::random(6)) . '@example.test',
        ]);

        $before = MyLog::query()
            ->where('type', 80)
            ->where('action', 82)
            ->where('partner_id', $this->partner->id)
            ->count();

        $payload = $this->validPartnerPayload([
            'title' => 'Новое название',
            'email' => 'new_' . Str::lower(Str::random(6)) . '@example.test',
        ]);

        $this->patchJson(route('admin.partner.update', $partner), $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Партнёр успешно обновлён')
            ->assertJsonPath('partner.id', $partner->id);

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'title' => 'Новое название',
            'email' => $payload['email'],
        ]);

        $after = MyLog::query()
            ->where('type', 80)
            ->where('action', 82)
            ->where('partner_id', $this->partner->id)
            ->count();

        // Лог пишется только если реально были изменения.
        $this->assertSame($before + 1, $after);
    }

    public function test_update_same_payload_does_not_write_log(): void
    {
        $this->asSuperadmin();

        // Важно: payload должен совпадать со ВСЕМИ значимыми полями в БД,
        // иначе PartnerController корректно создаст лог "были изменения".
        $payload = $this->validPartnerPayload([
            'title' => 'Без изменений',
            'email' => 'same_' . Str::lower(Str::random(6)) . '@example.test',
        ]);

        $partner = Partner::factory()->create($payload);

        $before = MyLog::query()
            ->where('type', 80)
            ->where('action', 82)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->patchJson(route('admin.partner.update', $partner), $payload)->assertOk();

        $after = MyLog::query()
            ->where('type', 80)
            ->where('action', 82)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->assertSame($before, $after);
    }

    public function test_destroy_soft_deletes_partner_and_writes_log_scoped_by_current_partner(): void
    {
        $this->asSuperadmin();

        $partner = Partner::factory()->create([
            'title' => 'Удаляемый партнёр',
            'email' => 'del_' . Str::lower(Str::random(6)) . '@example.test',
        ]);

        $before = MyLog::query()
            ->where('type', 80)
            ->where('action', 83)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->deleteJson(route('admin.partner.delete', $partner))
            ->assertOk()
            ->assertJsonPath('message', 'Партнёр удалён');

        $this->assertSoftDeleted('partners', [
            'id' => $partner->id,
        ]);

        $after = MyLog::query()
            ->where('type', 80)
            ->where('action', 83)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->assertSame($before + 1, $after);
    }

    public function test_logs_data_returns_datatables_json_and_includes_partner_logs_for_current_partner(): void
    {
        $this->asSuperadmin();

        // Создаём лог через реальный store, чтобы проверить связку controller -> MyLog -> logs-data.
        $payload = $this->validPartnerPayload(['title' => 'Партнёр для логов']);
        $res = $this->postJson(route('admin.partner.store'), $payload)->assertStatus(201);

        $createdId = (int) $res->json('partner.id');
        $this->assertGreaterThan(0, $createdId);

        $response = $this->getJson(route('logs.data.partner', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]));

        $response->assertOk();

        $json = $response->json();
        $this->assertArrayHasKey('draw', $json);
        $this->assertArrayHasKey('recordsTotal', $json);
        $this->assertArrayHasKey('recordsFiltered', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertIsArray($json['data']);

        $targetIds = collect($json['data'])->pluck('target_id')->all();
        $this->assertContains($createdId, $targetIds);
    }
}

