<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait SuccessToastInsteadOfModalTestHelpers
{
    /** @param list<string> $permissions */
    protected function grantPermissionsTo(User $actor, array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id'    => $this->partner->id,
                'role_id'       => $actor->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    /** @param list<string> $permissions */
    protected function asAdminWith(array $permissions): void
    {
        $this->asAdmin();
        $this->grantPermissionsTo($this->user, $permissions);
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

    /**
     * Страницы, где успех без reload должен идти во всплывайку #kidsMainToast.
     *
     * @return list<array{key: string, route: string, grant: list<string>, deny: string}>
     */
    protected function toastPageSpecs(): array
    {
        return [
            [
                'key'   => 'users',
                'route' => 'admin.user1',
                'grant' => ['users.view'],
                'deny'  => 'users.view',
            ],
            [
                'key'   => 'trainers',
                'route' => 'admin.trainers.index',
                'grant' => ['trainers.view'],
                'deny'  => 'trainers.view',
            ],
            [
                'key'   => 'administrators',
                'route' => 'admin.administrators.index',
                'grant' => ['users.view', 'users.role.update'],
                'deny'  => 'users.view',
            ],
            [
                'key'   => 'legal-entities',
                'route' => 'admin.legal-entities.index',
                'grant' => ['legal_entities.view'],
                'deny'  => 'legal_entities.view',
            ],
            [
                'key'   => 'locations',
                'route' => 'admin.locations.index',
                'grant' => ['locations.view'],
                'deny'  => 'locations.view',
            ],
            [
                'key'   => 'school-leads',
                'route' => 'admin.school-leads',
                'grant' => ['schoolLeads.view'],
                'deny'  => 'schoolLeads.view',
            ],
            [
                'key'   => 'custom-payments',
                'route' => 'admin.settingPrices.customPayments',
                'grant' => ['setPrices.view', 'setPrices.customPayments.view'],
                'deny'  => 'setPrices.customPayments.view',
            ],
            [
                'key'   => 'monthly-prices',
                'route' => 'admin.settingPrices.indexMenu',
                'grant' => ['setPrices.view'],
                'deny'  => 'setPrices.view',
            ],
            [
                'key'   => 'roles',
                'route' => 'admin.setting.rule',
                'grant' => ['settings.roles.view'],
                'deny'  => 'settings.roles.view',
            ],
            [
                'key'   => 'account-user',
                'route' => 'account.user.edit',
                'grant' => ['account.user.view'],
                'deny'  => 'account.user.view',
            ],
        ];
    }

    protected function assertResponseNeverEmptySuccessOrServerError(\Illuminate\Testing\TestResponse $response, string $label): void
    {
        $status = $response->getStatusCode();
        $this->assertNotSame(500, $status, "{$label} → 500");

        if ($status === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()), "{$label} → пустой 200");
        }
    }
}
