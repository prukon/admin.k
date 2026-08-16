<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\InAppNotifications;

use App\Jobs\FanOutInAppNotificationJob;
use App\Models\InAppNotification;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Services\InAppNotifications\InAppNotificationAudience;
use App\Services\InAppNotifications\InAppNotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

abstract class InAppNotificationsTestCase extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        $this->withoutVite();
    }

    protected function asSuperadminReady(): self
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        return $this;
    }

    protected function actingInPartner(User $user, ?Partner $partner = null): self
    {
        $partner ??= $this->partner;
        $this->actingAs($user);
        $this->withSession(['current_partner' => $partner->id]);

        return $this;
    }

    /**
     * @param  list<int>  $partnerIds
     * @param  list<int>  $roleIds
     * @param  array<string, mixed>  $overrides
     */
    protected function dispatchToRoles(
        array $partnerIds,
        array $roleIds,
        bool $allPartners,
        array $overrides = [],
    ): InAppNotification {
        $author = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('superadmin'),
        ]);

        $payload = array_merge([
            'title' => 'Тест',
            'body' => 'Текст уведомления',
            'category' => InAppNotification::CATEGORY_NORMAL,
            'all_partners' => $allPartners,
            'partner_ids' => $partnerIds,
            'role_ids' => $roleIds,
            'ttl_preset' => InAppNotification::TTL_7D,
            'custom_expires_at' => null,
        ], $overrides);

        $notification = app(InAppNotificationDispatcher::class)->dispatchManual($payload, $author);
        (new FanOutInAppNotificationJob((int) $notification->id))
            ->handle(app(InAppNotificationAudience::class));

        return $notification->fresh();
    }

    protected function createCustomRole(Partner $partner, string $label): Role
    {
        $role = Role::query()->create([
            'name' => 'custom_'.Str::lower(Str::random(8)),
            'label' => $label,
            'is_sistem' => 0,
            'is_visible' => 1,
            'order_by' => 80,
        ]);
        DB::table('partner_role')->insert([
            'role_id' => $role->id,
            'partner_id' => $partner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $role;
    }

    protected function grantManageToRole(string $roleName, ?Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $partner->id,
            'role_id' => $this->roleId($roleName),
            'permission_id' => $this->permissionId('inAppNotifications.manage'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
