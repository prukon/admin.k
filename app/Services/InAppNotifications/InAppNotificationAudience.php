<?php

declare(strict_types=1);

namespace App\Services\InAppNotifications;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

final class InAppNotificationAudience
{
    /**
     * @return list<string>
     */
    public function systemRoleNames(): array
    {
        return array_values(array_filter(
            (array) config('in_app_notifications.system_role_names', ['user', 'admin', 'trainer'])
        ));
    }

    /**
     * Роли, допустимые в форме при выбранных школах.
     *
     * @param  list<int>  $partnerIds
     * @return Collection<int, Role>
     */
    public function availableRoles(array $partnerIds, bool $allPartners): Collection
    {
        $systemNames = $this->systemRoleNames();

        $query = Role::query()
            ->where('name', '!=', 'superadmin')
            ->orderBy('order_by')
            ->orderBy('id');

        $singlePartnerId = (! $allPartners && count($partnerIds) === 1)
            ? (int) $partnerIds[0]
            : 0;

        if ($singlePartnerId > 0) {
            $query->where(function ($q) use ($systemNames, $singlePartnerId): void {
                $q->where(function ($inner) use ($systemNames): void {
                    $inner->where('is_sistem', true)
                        ->whereIn('name', $systemNames);
                })->orWhere(function ($inner) use ($singlePartnerId): void {
                    $inner->where('is_sistem', false)
                        ->whereHas('partners', function ($partners) use ($singlePartnerId): void {
                            $partners->where('partners.id', $singlePartnerId);
                        });
                });
            });
        } else {
            $query->where('is_sistem', true)->whereIn('name', $systemNames);
        }

        return $query->get(['id', 'name', 'label', 'is_sistem']);
    }

    /**
     * @param  list<int>  $roleIds
     * @param  list<int>  $partnerIds
     * @return list<int>
     */
    public function allowedRoleIds(array $roleIds, array $partnerIds, bool $allPartners): array
    {
        $allowed = $this->availableRoles($partnerIds, $allPartners)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $requested = array_values(array_unique(array_map('intval', $roleIds)));

        return array_values(array_intersect($requested, $allowed));
    }

    /**
     * Id системных ролей по именам (пересечение с config system_role_names).
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    public function systemRoleIdsByNames(array $names): array
    {
        $requested = array_values(array_unique(array_filter(array_map('strval', $names))));
        if ($requested === []) {
            return [];
        }

        $allowed = array_values(array_intersect($requested, $this->systemRoleNames()));
        if ($allowed === []) {
            return [];
        }

        return Role::query()
            ->where('is_sistem', true)
            ->whereIn('name', $allowed)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Снимок получателей: включённые пользователи выбранных ролей в выбранных школах.
     *
     * @param  list<int>  $partnerIds
     * @param  list<int>  $roleIds
     * @return list<int>
     */
    public function resolveRecipientUserIds(array $partnerIds, array $roleIds): array
    {
        $partnerIds = array_values(array_unique(array_filter(array_map('intval', $partnerIds))));
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

        if ($partnerIds === [] || $roleIds === []) {
            return [];
        }

        $superadminRoleId = Role::superadminRoleId();

        $query = User::query()
            ->whereIn('partner_id', $partnerIds)
            ->whereIn('role_id', $roleIds)
            ->where('is_enabled', 1);

        if ($superadminRoleId !== null) {
            $query->where('role_id', '!=', $superadminRoleId);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return list<int>
     */
    public function superadminUserIds(): array
    {
        $roleId = Role::superadminRoleId();
        if ($roleId === null) {
            return [];
        }

        return User::query()
            ->where('role_id', $roleId)
            ->where('is_enabled', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
