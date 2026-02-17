<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class AccountUpdateUserTest extends CrmTestCase
{
    protected User $actorUser;
    protected User $actorAdmin;

    protected int $roleAdminId;
    protected int $roleUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdminId = $this->roleId('admin');
        $this->roleUserId  = $this->roleId('user');

        // Используем текущего пользователя/партнёра из CrmTestCase (с настоящими правами).
        $this->actorUser = $this->user;
        $this->actorUser->forceFill([
            'name'       => 'Ivan',
            'lastname'   => 'Petrov',
        ])->save();

        $this->actorAdmin = $this->createUserWithRole('admin', $this->partner, [
            'name'       => 'Admin',
            'lastname'   => 'Boss',
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. УДАЛЕНИЕ ТЕЛЕФОНА: 2FA ON + телефон подтверждён => запрещено (422)
    // -------------------------------------------------------------------------

    public function test_update_blocks_phone_delete_when_2fa_enabled_and_phone_verified(): void
    {
        $this->actingAs($this->actorUser);

        $this->actorUser->forceFill([
            'phone'              => '+79990001122',
            'phone_verified_at'  => now(),
            'two_factor_enabled' => 1,
        ])->save();

        $payload = [
            'name'               => $this->actorUser->name,
            'lastname'           => $this->actorUser->lastname,
            'phone'              => null,
            'two_factor_enabled' => true,
        ];

        $resp = $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true, // ✅ иначе EnsureTwoFactorIsVerified отдаст 302
        ])
            ->withHeaders([
                'Accept'             => 'application/json',
                'X-Requested-With'   => 'XMLHttpRequest',
            ])
            ->patchJson(route('account.user.update'), $payload);

        $resp->assertStatus(422);
        $resp->assertJsonPath(
            'errors.phone.0',
            'Нельзя удалить подтверждённый телефон при включённой 2FA.'
        );
    }

    // -------------------------------------------------------------------------
    // 2. УДАЛЕНИЕ ТЕЛЕФОНА: 2FA ON + телефон НЕ подтверждён => разрешено (200)
    //    (и в update() ты должен выключить 2FA, иначе ниже сработает "телефон обязателен")
    // -------------------------------------------------------------------------

    public function test_update_allows_phone_delete_when_2fa_enabled_but_phone_not_verified(): void
    {
        $this->actingAs($this->actorUser);

        $this->actorUser->forceFill([
            'phone'              => '+79990001122',
            'phone_verified_at'  => null,
            'two_factor_enabled' => 1,
        ])->save();

        $payload = [
            'name'               => $this->actorUser->name,
            'lastname'           => $this->actorUser->lastname,
            'phone'              => null,
            'two_factor_enabled' => true, // просим оставить 2FA, но телефон удаляем => update() должен выключить 2FA сам
        ];

        $resp = $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true, // ✅ иначе будет 302
        ])
            ->withHeaders([
                'Accept'             => 'application/json',
                'X-Requested-With'   => 'XMLHttpRequest',
            ])
            ->patchJson(route('account.user.update'), $payload);

        $resp->assertStatus(200);
        $resp->assertJsonPath('success', true);

        $this->actorUser->refresh();

        $this->assertNull($this->actorUser->phone);
        $this->assertNull($this->actorUser->phone_verified_at);
        $this->assertSame(0, (int) $this->actorUser->two_factor_enabled);
    }

    // -------------------------------------------------------------------------
    // 3. FORCE 2FA ДЛЯ АДМИНА (глобальная настройка)
    // -------------------------------------------------------------------------

    public function test_update_forces_2fa_for_admin_when_global_setting_enabled(): void
    {
        $this->actingAs($this->actorAdmin);

        Setting::query()->updateOrCreate(
            ['name' => 'force_2fa_admins', 'partner_id' => null],
            ['status' => 1]
        );

        $this->actorAdmin->forceFill([
            'phone'              => '+79991112233',
            'two_factor_enabled' => 0,
        ])->save();

        $payload = [
            'name'               => $this->actorAdmin->name,
            'lastname'           => $this->actorAdmin->lastname,
            'role_id'            => $this->roleAdminId,
            'two_factor_enabled' => false, // пытаемся выключить
            'phone'              => '+79991112233',
        ];

        $resp = $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true, // ✅ иначе middleware может увести на challenge
        ])
            ->withHeaders([
                'Accept'             => 'application/json',
                'X-Requested-With'   => 'XMLHttpRequest',
            ])
            ->patchJson(route('account.user.update'), $payload);

        $resp->assertStatus(200);
        $resp->assertJsonPath('success', true);

        $this->actorAdmin->refresh();
        $this->assertSame(1, (int) $this->actorAdmin->two_factor_enabled);
    }

    // -------------------------------------------------------------------------
    // 4. CUSTOM FIELDS: нельзя сохранять, если роль не разрешена pivot'ом
    // -------------------------------------------------------------------------

    public function test_update_custom_field_does_not_save_when_role_not_allowed(): void
    {
        $this->actingAs($this->actorUser);

        $field = UserField::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Рост',
            'slug'       => 'rost',
        ]);

        DB::table('user_field_role')->insert([
            'user_field_id' => $field->id,
            'role_id'       => $this->roleAdminId,
        ]);

        $payload = [
            'name'     => $this->actorUser->name,
            'lastname' => $this->actorUser->lastname,
            'custom'   => [
                'rost' => '180',
            ],
        ];

        $resp = $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ])
            ->withHeaders([
                'Accept'             => 'application/json',
                'X-Requested-With'   => 'XMLHttpRequest',
            ])
            ->patchJson(route('account.user.update'), $payload);

        $resp->assertStatus(200);
        $resp->assertJsonPath('success', true);

        $this->assertDatabaseMissing('user_field_values', [
            'user_id'  => $this->actorUser->id,
            'field_id' => $field->id,
            'value'    => '180',
        ]);
    }

    // -------------------------------------------------------------------------
    // 5. CUSTOM FIELDS: если pivot пустой => можно всем => значение сохраняется
    // -------------------------------------------------------------------------

    public function test_update_custom_field_saves_when_roles_pivot_empty_allows_everyone(): void
    {
        $this->actingAs($this->actorUser);

        $field = UserField::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Город',
            'slug'       => 'city',
        ]);

        $payload = [
            'name'     => $this->actorUser->name,
            'lastname' => $this->actorUser->lastname,
            'custom'   => [
                'city' => 'Moscow',
            ],
        ];

        $resp = $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ])
            ->withHeaders([
                'Accept'             => 'application/json',
                'X-Requested-With'   => 'XMLHttpRequest',
            ])
            ->patchJson(route('account.user.update'), $payload);

        $resp->assertStatus(200);
        $resp->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_field_values', [
            'user_id'  => $this->actorUser->id,
            'field_id' => $field->id,
            'value'    => 'Moscow',
        ]);
    }
}