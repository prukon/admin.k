<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\User;
use App\Services\SmsRuService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Crm\CrmTestCase;

/**
 * account.user.phone.verify: скрытое, не в базовых ролях (по умолчанию выкл.).
 */
abstract class AccountPhoneVerifyPermissionTestCase extends CrmTestCase
{
    protected const PERMISSION = 'account.user.phone.verify';

    protected const DESCRIPTION = 'ЛК: подтверждение телефона (SMS)';

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

    protected function grantVerifyToBaseRoles(): void
    {
        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $this->grantPermissionToRole($roleName);
        }
    }

    protected function revokePhoneUpdateFromRole(string $roleName): void
    {
        DB::table('permission_role')
            ->where('role_id', $this->roleId($roleName))
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $this->permissionId('account.user.phone.update'))
            ->delete();
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

    protected function grantPermissionToActor(User $actor): void
    {
        $now = now();

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => (int) $actor->partner_id,
            'role_id' => (int) $actor->role_id,
            'permission_id' => $this->permissionId(self::PERMISSION),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function withPendingPhoneCode(
        User $user,
        string $digits,
        string $code,
        $expiresAt = null
    ): void {
        $user->forceFill([
            'two_factor_phone_pending' => '+'.$digits,
            'phone_change_new_code' => Hash::make($code),
            'phone_change_new_expires_at' => $expiresAt ?? now()->addMinutes(10),
        ])->save();
    }

    protected function mockSmsSendOk(): void
    {
        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->andReturn(true);
        });
    }

    protected function mockSmsSendNever(): void
    {
        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });
    }

    protected function phoneInputTag(string $html): string
    {
        $this->assertMatchesRegularExpression(
            '/<input[^>]*\bid="phone"[^>]*>/',
            $html,
            'Нет поля #phone'
        );
        preg_match('/<input[^>]*\bid="phone"[^>]*>/', $html, $m);

        return $m[0];
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

    protected function sendCodeUrl(User $target): string
    {
        return route('account.user.phoneSendCode', $target);
    }

    protected function confirmCodeUrl(User $target): string
    {
        return route('account.user.phoneConfirmCode', $target);
    }

    /**
     * @return list<array{method: string, url: string}>
     */
    protected function phoneVerifyWrongMethods(User $target): array
    {
        return [
            ['method' => 'GET', 'url' => $this->sendCodeUrl($target)],
            ['method' => 'PUT', 'url' => $this->sendCodeUrl($target)],
            ['method' => 'PATCH', 'url' => $this->sendCodeUrl($target)],
            ['method' => 'DELETE', 'url' => $this->sendCodeUrl($target)],
            ['method' => 'GET', 'url' => $this->confirmCodeUrl($target)],
            ['method' => 'PUT', 'url' => $this->confirmCodeUrl($target)],
            ['method' => 'PATCH', 'url' => $this->confirmCodeUrl($target)],
            ['method' => 'DELETE', 'url' => $this->confirmCodeUrl($target)],
        ];
    }
}
