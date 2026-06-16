<?php

namespace Tests\Feature\Crm\Users\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait GrantsUsersSectionPermissions
{
    protected function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    protected function grantUsersView(User $actor): void
    {
        $this->grantPermission($actor, 'users.view');
    }

    protected function grantRoleUpdate(User $actor): void
    {
        $this->grantPermission($actor, 'users.role.update');
    }

    protected function grantTrainersView(User $actor): void
    {
        $this->grantPermission($actor, 'trainers.view');
    }

    protected function grantStaffSectionAccess(User $actor): void
    {
        $this->grantUsersView($actor);
        $this->grantRoleUpdate($actor);
    }

    protected function studentRoleId(): int
    {
        return $this->roleId('user');
    }

    protected function adminRoleId(): int
    {
        return $this->roleId('admin');
    }
}
