<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\User;
use App\Support\SystemMonitors;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Общие хелперы переключателя системных мониторов.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class SystemMonitorsTestCase extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    protected function toggleUrl(): string
    {
        return route('cabinet.system-monitors.update');
    }

    protected function onlineUsersUrl(): string
    {
        return route('cabinet.system-monitors.online-users');
    }

    protected function opsUrl(): string
    {
        return route('cabinet.system-monitors.ops');
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

    protected function grantSystemMonitorsView(User $actor): void
    {
        $this->grantPermissionToActor($actor, SystemMonitors::PERMISSION);
    }

    protected function actingInCurrentPartner(User $actor): self
    {
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $actor->partner_id ?? $this->partner->id,
            '2fa:passed' => true,
        ]);

        return $this;
    }
}
