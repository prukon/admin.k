<?php

namespace Tests\Feature\Crm;

use App\Models\MyLog;
use App\Models\Partner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RuleControllerTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Обходим can:settings-roles-view (и любые другие can:* на роутинге)
        Gate::before(fn () => true);

        // Стабильно фиксируем текущего партнёра (SetPartner обычно делает то же)
        $this->withSession(['current_partner' => $this->partner->id]);
    }

    private function makeSuperadmin(): void
    {
        $super = Role::where('name', 'superadmin')->first();
        if ($super) {
            $this->user->role_id = $super->id;
            $this->user->save();
        }
    }

    private function createUserWithoutPartner(): User
    {
        return User::factory()->create(['partner_id' => null]);
    }

    public function test_guest_cannot_access_rules_pages_and_actions(): void
    {
        auth()->logout();

        // HTML страница чаще редиректит на логин
        $this->get(route('admin.setting.rule'))->assertStatus(302);

        // JSON/POST ручки обычно дают 401
        $this->postJson(route('admin.setting.rule.toggle'), [])->assertStatus(401);
        $this->postJson(route('admin.setting.role.create'), [])->assertStatus(401);

        // DELETE тоже 401
        $this->deleteJson(route('admin.setting.role.delete'), [])->assertStatus(401);

        // DataTables JSON — тоже 401
        $this->getJson(route('logs.data.rule'))->assertStatus(401);
    }

    public function test_user_without_partner_is_blocked_by_set_partner_middleware(): void
    {
        $u = $this->createUserWithoutPartner();

        $this->actingAs($u);
        $this->flushSession(); // убираем current_partner, чтобы SetPartner точно отработал

        $res = $this->from('/admin')->get(route('admin.setting.rule'));

        // По твоему SetPartner: redirect()->back() + session errors
        $res->assertStatus(302);
        $res->assertSessionHasErrors(['partner']);
    }

    public function test_toggle_permission_attaches_with_current_partner_id_and_logs_once_and_is_idempotent(): void
    {
        $this->makeSuperadmin();

        $role = Role::where('name', 'admin')->first() ?? Role::firstOrFail();
        $perm = Permission::firstOrFail();

        // Чистим на всякий случай
        DB::table('permission_role')
            ->where('role_id', $role->id)
            ->where('permission_id', $perm->id)
            ->where('partner_id', $this->partner->id)
            ->delete();

        $beforeLogs = MyLog::where('type', 700)
            ->where('action', 741)
            ->where('partner_id', $this->partner->id)
            ->count();

        // attach
        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
            'value'         => 'true',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('permission_role', [
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
            'partner_id'    => $this->partner->id,
        ]);

        $afterLogs = MyLog::where('type', 700)
            ->where('action', 741)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->assertSame($beforeLogs + 1, $afterLogs);

        // повторный attach (идемпотентность)
        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
            'value'         => 'true',
        ])->assertOk()->assertJson(['success' => true]);

        $countPivot = DB::table('permission_role')
            ->where('role_id', $role->id)
            ->where('permission_id', $perm->id)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->assertSame(1, $countPivot);

        $afterLogs2 = MyLog::where('type', 700)
            ->where('action', 741)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->assertSame($afterLogs, $afterLogs2);
    }

    public function test_toggle_permission_detaches_only_for_current_partner_and_logs_once_and_is_idempotent(): void
    {
        $this->makeSuperadmin();

        $role = Role::where('name', 'admin')->first() ?? Role::firstOrFail();
        $perm = Permission::firstOrFail();

        $partnerB = Partner::factory()->create();

        // Вставляем 2 записи для разных партнёров
        DB::table('permission_role')->updateOrInsert(
            [
                'role_id'       => $role->id,
                'permission_id' => $perm->id,
                'partner_id'    => $this->partner->id,
            ],
            ['created_at' => now(), 'updated_at' => now()]
        );

        DB::table('permission_role')->updateOrInsert(
            [
                'role_id'       => $role->id,
                'permission_id' => $perm->id,
                'partner_id'    => $partnerB->id,
            ],
            ['created_at' => now(), 'updated_at' => now()]
        );

        $beforeLogs = MyLog::where('type', 700)
            ->where('action', 742)
            ->where('partner_id', $this->partner->id)
            ->count();

        // detach для текущего партнёра
        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
            'value'         => 'false',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('permission_role', [
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
            'partner_id'    => $this->partner->id,
        ]);

        $this->assertDatabaseHas('permission_role', [
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
            'partner_id'    => $partnerB->id,
        ]);

        $afterLogs = MyLog::where('type', 700)
            ->where('action', 742)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->assertSame($beforeLogs + 1, $afterLogs);

        // повторный detach (идемпотентность)
        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
            'value'         => 'false',
        ])->assertOk()->assertJson(['success' => true]);

        $afterLogs2 = MyLog::where('type', 700)
            ->where('action', 742)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->assertSame($afterLogs, $afterLogs2);
    }

    public function test_create_role_creates_role_attaches_to_partner_and_logs(): void
    {
        $this->makeSuperadmin();

        $before = MyLog::where('type', 700)
            ->where('action', 710)
            ->where('partner_id', $this->partner->id)
            ->count();

        $res = $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Моя роль',
        ])->assertOk()->assertJson(['success' => true]);

        $roleId = $res->json('role.id');
        $this->assertNotEmpty($roleId);

        $role = Role::findOrFail($roleId);

        $this->assertSame('Моя роль', $role->label);

        // Привязка к партнёру (таблица по коду контроллера -> partner_role)
        $this->assertDatabaseHas('partner_role', [
            'partner_id' => $this->partner->id,
            'role_id'    => $role->id,
        ]);

        $after = MyLog::where('type', 700)
            ->where('action', 710)
            ->where('partner_id', $this->partner->id)
            ->count();

        $this->assertSame($before + 1, $after);
    }

    public function test_create_role_generates_unique_machine_name_on_conflict(): void
    {
        $this->makeSuperadmin();

        $r1 = $this->postJson(route('admin.setting.role.create'), ['name' => 'Одинаковая роль'])
            ->assertOk()
            ->json('role');

        $this->assertNotEmpty($r1['name'] ?? null);

        $r2 = $this->postJson(route('admin.setting.role.create'), ['name' => 'Одинаковая роль'])
            ->assertOk()
            ->json('role');

        $this->assertNotEmpty($r2['name'] ?? null);

        $this->assertNotSame($r1['name'], $r2['name']);
    }

    public function test_delete_role_forbids_system_role(): void
    {
        $this->makeSuperadmin();

        $systemRole = Role::where('is_sistem', 1)->firstOrFail();

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $systemRole->id,
        ])->assertStatus(400)->assertJson([
            'success' => false,
        ]);
    }

    public function test_delete_role_reassigns_users_to_default_user_role_and_deletes_permissions(): void
    {
        $this->makeSuperadmin();

        // Несистемная роль (без description — у тебя поля нет)
        $role = Role::create([
            'name'       => 'tmp_role_for_delete',
            'label'      => 'Tmp role',
            'is_sistem'  => 0,
            'is_visible' => 1,
            'order_by'   => (Role::max('order_by') ?? 0) + 10,
        ]);

        DB::table('partner_role')->insert([
            'partner_id' => $this->partner->id,
            'role_id'    => $role->id,
        ]);

        $u = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $role->id,
        ]);

        $perm = Permission::firstOrFail();

        DB::table('permission_role')->insert([
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
            'partner_id'    => $this->partner->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $role->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->id]);

        $defaultRole = Role::where('name', 'user')->firstOrFail();
        $u->refresh();
        $this->assertSame($defaultRole->id, $u->role_id);
    }

    public function test_log_rules_returns_only_current_partner_type_700(): void
    {
        $this->makeSuperadmin();

        $partnerB = Partner::factory()->create();

        MyLog::create([
            'type'        => 700,
            'action'      => 710,
            'author_id'   => $this->user->id,
            'partner_id'  => $this->partner->id,
            'description' => 'A',
            'created_at'  => now(),
        ]);

        MyLog::create([
            'type'        => 700,
            'action'      => 710,
            'author_id'   => $this->user->id,
            'partner_id'  => $partnerB->id,
            'description' => 'B',
            'created_at'  => now(),
        ]);

        MyLog::create([
            'type'        => 123,
            'action'      => 999,
            'author_id'   => $this->user->id,
            'partner_id'  => $this->partner->id,
            'description' => 'NOT 700',
            'created_at'  => now(),
        ]);

        $res = $this->getJson(route('logs.data.rule'))->assertOk();
        $json = $res->json();

        $this->assertArrayHasKey('data', $json);

        $descriptions = array_map(
            fn ($row) => $row['description'] ?? null,
            $json['data']
        );

        $this->assertContains('A', $descriptions);
        $this->assertNotContains('B', $descriptions);
        $this->assertNotContains('NOT 700', $descriptions);
    }
}