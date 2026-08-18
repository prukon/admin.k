<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait AccountOwnPasswordUpdateTestHelpers
{
    protected function passwordUpdateUrl(): string
    {
        return route('account.user.password.update');
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ];
    }

    protected function setKnownPassword(User $user, string $plain = 'current-pass-8'): void
    {
        $user->password = $plain;
        $user->save();
    }

    protected function grantAccountUserView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => (int) ($actor->partner_id ?? $this->partner->id),
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('account.user.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    protected function passwordUpdateHttpMethods(): array
    {
        $url = $this->passwordUpdateUrl();

        return [
            ['method' => 'GET', 'url' => $url],
            ['method' => 'POST', 'url' => $url, 'data' => ['password' => 'new-pass-88']],
            ['method' => 'PATCH', 'url' => $url, 'data' => ['password' => 'new-pass-88']],
            ['method' => 'DELETE', 'url' => $url],
            ['method' => 'PUT', 'url' => $url, 'data' => ['password' => 'new-pass-88']],
        ];
    }
}
