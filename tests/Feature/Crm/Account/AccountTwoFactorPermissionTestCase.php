<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

abstract class AccountTwoFactorPermissionTestCase extends CrmTestCase
{
    protected const PERMISSION = 'account.user.two_factor.update';

    protected const LABEL = 'Двухфакторная аутентификация (SMS)';

    protected const DESCRIPTION = 'ЛК: двухфакторная аутентификация (SMS)';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    protected function revokePermissionFromRole(string $roleName): void
    {
        DB::table('permission_role')
            ->where('role_id', $this->roleId($roleName))
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $this->permissionId(self::PERMISSION))
            ->delete();
    }

    protected function grantPermissionToRole(string $roleName): void
    {
        $now = now();

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId($roleName),
            'permission_id' => $this->permissionId(self::PERMISSION),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function grantTwoFactorToBaseRoles(): void
    {
        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $this->grantPermissionToRole($roleName);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function accountPayload(User $actor, array $extra = []): array
    {
        return array_merge([
            'name' => $actor->name,
            'lastname' => $actor->lastname,
            'phone' => $actor->phone,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function patchAccountJson(array $payload)
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->patchJson(route('account.user.update'), $payload);
    }

    protected function grantAccountUserView(User $actor): void
    {
        $now = now();

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => (int) $actor->partner_id,
            'role_id' => (int) $actor->role_id,
            'permission_id' => $this->permissionId('account.user.view'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function withVerifiedPhone(User $actor, string $phone = '+79990001122'): User
    {
        $actor->forceFill([
            'phone' => $phone,
            'phone_verified_at' => now(),
        ])->save();

        return $actor->refresh();
    }

    protected function enableForce2faAdmins(): void
    {
        Setting::query()->updateOrCreate(
            ['name' => 'force_2fa_admins', 'partner_id' => null],
            ['status' => 1]
        );
    }

    protected function disableForce2faAdmins(): void
    {
        Setting::query()->updateOrCreate(
            ['name' => 'force_2fa_admins', 'partner_id' => null],
            ['status' => 0]
        );
    }

    /**
     * @return array<string, string>
     */
    protected function accountAjaxHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    protected function extractTwoFactorBlock(string $html): string
    {
        $start = strpos($html, self::LABEL);
        $this->assertNotFalse($start, 'На странице нет блока SMS-2FA');
        $slice = substr($html, $start, 2500);
        foreach (['Данные родителя', 'id="parent_lastname"', 'Изменить пароль'] as $cutNeedle) {
            $cut = strpos($slice, $cutNeedle);
            if ($cut !== false) {
                $slice = substr($slice, 0, $cut);
                break;
            }
        }

        return $slice;
    }

    protected function twoFactorCheckboxTag(string $html): string
    {
        $this->assertMatchesRegularExpression(
            '/<input[^>]*\bid="two_factor_enabled"[^>]*>/',
            $html,
            'Нет чекбокса #two_factor_enabled'
        );
        preg_match('/<input[^>]*\bid="two_factor_enabled"[^>]*>/', $html, $m);

        return $m[0];
    }

    /**
     * @return list<array{method: string, url: string}>
     */
    protected function accountUserUpdateWrongMethods(): array
    {
        $url = route('account.user.update');

        return [
            ['method' => 'GET', 'url' => $url],
            ['method' => 'POST', 'url' => $url],
            ['method' => 'PUT', 'url' => $url],
            ['method' => 'DELETE', 'url' => $url],
        ];
    }
}
