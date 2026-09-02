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

    /**
     * Активные админы партнёра с валидным российским телефоном (11 цифр, ведущая 7).
     *
     * @return list<array{id: int, label: string, phone: string, digits: string}>
     */
    public static function phoneOptionsForPartner(int $partnerId): array
    {
        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');
        if ($adminRoleId <= 0) {
            return [];
        }

        $admins = User::query()
            ->where('partner_id', $partnerId)
            ->where('role_id', $adminRoleId)
            ->where('is_enabled', true)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('lastname')
            ->orderBy('name')
            ->get(['id', 'name', 'lastname', 'phone']);

        $options = [];
        foreach ($admins as $admin) {
            $rawPhone = (string) ($admin->phone ?? '');
            $digits = RuPhone::normalizeDigits($rawPhone);
            if ($digits === null || strlen($digits) !== 11 || ! str_starts_with($digits, '7')) {
                continue;
            }

            $formatted = RuPhone::formatForInput($rawPhone);
            $name = trim((string) $admin->full_name);
            $options[] = [
                'id' => (int) $admin->id,
                'label' => $name !== '' ? $name.' — '.$formatted : $formatted,
                'phone' => $formatted,
                'digits' => $digits,
            ];
        }

        return $options;
    }

    public static function systemAdminRoleId(): ?int
    {
        $roleId = Role::query()->where('name', 'admin')->value('id');

        return $roleId !== null ? (int) $roleId : null;
    }
}
