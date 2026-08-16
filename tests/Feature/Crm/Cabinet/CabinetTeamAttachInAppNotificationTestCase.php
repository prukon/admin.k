<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Jobs\FanOutInAppNotificationJob;
use App\Models\InAppNotification;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Services\InAppNotifications\InAppNotificationAudience;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\InAppNotifications\InAppNotificationsTestCase;

/**
 * Общие фикстуры: ЛК attach группы → in-app уведомление admin/trainer школы.
 */
abstract class CabinetTeamAttachInAppNotificationTestCase extends InAppNotificationsTestCase
{
    protected TeamUserSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sync = app(TeamUserSyncService::class);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    protected function actingWith2fa(User $user, ?int $partnerId = null): self
    {
        $this->actingAs($user);
        $this->withSession([
            'current_partner' => $partnerId ?? (int) $user->partner_id,
            '2fa:passed' => true,
        ]);

        return $this;
    }

    protected function fanOutLatestEvent(): InAppNotification
    {
        $notification = InAppNotification::query()
            ->where('source', InAppNotification::SOURCE_EVENT)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);

        (new FanOutInAppNotificationJob((int) $notification->id))
            ->handle(app(InAppNotificationAudience::class));

        $fresh = $notification->fresh();
        $this->assertNotNull($fresh);

        return $fresh;
    }

    protected function eventNotificationCount(): int
    {
        return InAppNotification::query()
            ->where('source', InAppNotification::SOURCE_EVENT)
            ->count();
    }

    /**
     * @return array{0: User, 1: Team, 2: Team}
     */
    protected function makeStudentWithLocationTeams(): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Тестов',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);

        $location = Location::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Тестовый объект',
        ]);

        $current = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'ТекГруппа',
            'is_enabled' => 1,
        ]);
        $candidate = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'НовГруппа',
            'is_enabled' => 1,
        ]);

        $this->sync->attachTeamForStudent($student, (int) $current->id);
        $this->grantPermission($student, 'dashboard.view');

        return [$student, $current, $candidate];
    }

    protected function grantAttachPermission(User $actor): void
    {
        $this->grantPermission($actor, 'account.user.team.update');
    }

    protected function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => (int) $actor->partner_id,
            'role_id' => (int) $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
