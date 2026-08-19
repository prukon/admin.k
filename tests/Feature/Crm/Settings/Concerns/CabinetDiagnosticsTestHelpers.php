<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Settings\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait CabinetDiagnosticsTestHelpers
{
    protected function withPartnerSession(?User $actor = null): void
    {
        $partnerId = $actor?->partner_id ?? $this->partner->id;

        $this->withSession([
            'current_partner' => $partnerId,
            '2fa:passed' => true,
        ]);
    }

    protected function toggleUrl(): string
    {
        return route('settings.cabinetDiagnostics');
    }

    protected function settingsUrl(): string
    {
        return route('admin.setting.setting');
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    protected function grantPermissionToActor(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $actor->partner_id ?? $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId($permissionName),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $actor->unsetRelation('role');
    }

    protected function grantPermissionToCurrentRole(string $permissionName): void
    {
        $this->grantPermissionToActor($this->user, $permissionName);
    }

    /**
     * @return list<string>
     */
    protected function overlayToggleWrongMethods(): array
    {
        return ['GET', 'PATCH', 'PUT', 'DELETE'];
    }

    protected function assertDeniedWithoutServerError(\Illuminate\Testing\TestResponse $response, string $context): void
    {
        $this->assertNotSame(500, $response->getStatusCode(), $context.' не 500');
        $this->assertNotSame(200, $response->getStatusCode(), $context.' не успешный 200');
        $this->assertContains(
            $response->getStatusCode(),
            [302, 401, 403, 404, 405, 419],
            $context.' → '.$response->getStatusCode()
        );
    }
}
