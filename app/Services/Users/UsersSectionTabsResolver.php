<?php

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\PartnerContext;
use Illuminate\Support\Collection;

class UsersSectionTabsResolver
{
    /** @var list<string> */
    private const DEDICATED_SYSTEM_ROLE_ROUTES = [
        'user'    => 'admin.user1',
        'trainer' => 'admin.trainers.index',
        'admin'   => 'admin.administrators.index',
    ];

    /** @var array<string, string> */
    private const SYSTEM_ROLE_TAB_LABELS = [
        'user'    => 'Пользователи',
        'trainer' => 'Тренеры',
        'admin'   => 'Администраторы',
    ];

    /** @var array<string, string> */
    private const SYSTEM_ROLE_TAB_IDS = [
        'user'    => 'users',
        'trainer' => 'trainers',
        'admin'   => 'administrators',
    ];

    public function __construct(
        private readonly PartnerContext $partnerContext,
    ) {
    }

    /**
     * @return list<array{id: string, label: string, route: string, route_params: array<string, string>}>
     */
    public function resolve(): array
    {
        $actor = $this->partnerContext->user();
        $partnerId = $this->partnerContext->partnerId();

        if (!$actor instanceof User || !$partnerId) {
            return [];
        }

        $tabs = [];

        foreach ($this->partnerRoles((int) $partnerId) as $role) {
            $tab = $this->tabForRole($role, $actor);
            if ($tab !== null) {
                $tabs[] = $tab;
            }
        }

        return $tabs;
    }

    /**
     * @return Collection<int, Role>
     */
    private function partnerRoles(int $partnerId): Collection
    {
        $isSuperadmin = $this->partnerContext->isSuperAdmin();

        return Role::query()
            ->exceptSuperadmin()
            ->when(!$isSuperadmin, fn ($q) => $q->where('is_visible', 1))
            ->where(function ($q) use ($partnerId) {
                $q->where('is_sistem', 1)
                    ->orWhereHas('partners', fn ($q2) => $q2->where('partner_role.partner_id', $partnerId));
            })
            ->orderBy('order_by')
            ->get();
    }

    /**
     * @return array{id: string, label: string, route: string, route_params: array<string, string>}|null
     */
    private function tabForRole(Role $role, User $actor): ?array
    {
        $name = (string) $role->name;

        if ($name === 'trainer') {
            if (!$actor->can('trainers.view')) {
                return null;
            }

            return [
                'id'           => self::SYSTEM_ROLE_TAB_IDS['trainer'],
                'label'        => self::SYSTEM_ROLE_TAB_LABELS['trainer'],
                'route'        => self::DEDICATED_SYSTEM_ROLE_ROUTES['trainer'],
                'route_params' => [],
            ];
        }

        if (!$actor->can('users.role.update')) {
            return null;
        }

        if ($name === 'user') {
            return [
                'id'           => self::SYSTEM_ROLE_TAB_IDS['user'],
                'label'        => self::SYSTEM_ROLE_TAB_LABELS['user'],
                'route'        => self::DEDICATED_SYSTEM_ROLE_ROUTES['user'],
                'route_params' => [],
            ];
        }

        if ($name === 'admin') {
            return [
                'id'           => self::SYSTEM_ROLE_TAB_IDS['admin'],
                'label'        => self::SYSTEM_ROLE_TAB_LABELS['admin'],
                'route'        => self::DEDICATED_SYSTEM_ROLE_ROUTES['admin'],
                'route_params' => [],
            ];
        }

        if ($role->is_sistem) {
            return null;
        }

        return [
            'id'           => 'role-' . $name,
            'label'        => (string) $role->label,
            'route'        => 'admin.roles.users.index',
            'route_params' => ['role' => $name],
        ];
    }
}
