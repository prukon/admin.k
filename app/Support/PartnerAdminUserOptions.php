<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class PartnerAdminUserOptions
{
    /**
     * Активные пользователи партнёра с системной ролью admin.
     *
     * @return Collection<int, User>
     */
    public static function forPartner(int $partnerId): Collection
    {
        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');
        if ($adminRoleId <= 0) {
            return new Collection();
        }

        return User::query()
            ->where('partner_id', $partnerId)
            ->where('role_id', $adminRoleId)
            ->where('is_enabled', true)
            ->orderBy('lastname')
            ->orderBy('name')
            ->get(['id', 'name', 'lastname']);
    }

    public static function systemAdminRoleId(): ?int
    {
        $roleId = Role::query()->where('name', 'admin')->value('id');

        return $roleId !== null ? (int) $roleId : null;
    }
}
