<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Role;
use App\Models\User;

/**
 * В чате роль superadmin видна всем как одна «Служба поддержки».
 * Канонический пользователь — активный superadmin с минимальным id.
 * Список контактов школы не расширяем OR по всем суперадминам:
 * канонический ряд подмешивается отдельно (PK / LIMIT 1).
 */
class ChatSupportIdentity
{
    public const DISPLAY_NAME = 'Служба поддержки';

    private bool $resolved = false;

    private ?User $canonical = null;

    public function canonicalUser(): ?User
    {
        if ($this->resolved) {
            return $this->canonical;
        }

        $this->resolved = true;
        $roleId = Role::superadminRoleId();
        if ($roleId === null) {
            $this->canonical = null;

            return null;
        }

        $this->canonical = User::query()
            ->where('role_id', $roleId)
            ->where('is_enabled', 1)
            ->orderBy('id')
            ->first();

        return $this->canonical;
    }

    public function canonicalUserId(): ?int
    {
        $user = $this->canonicalUser();

        return $user ? (int) $user->id : null;
    }

    public function isCanonicalUserId(int $userId): bool
    {
        $canonicalId = $this->canonicalUserId();

        return $canonicalId !== null && $userId === $canonicalId;
    }

    public function isSupportRoleId(?int $roleId): bool
    {
        return Role::isSuperadminRoleId($roleId === null ? null : (int) $roleId);
    }

    public function isSupportUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (isset($user->role_name) && (string) $user->role_name === 'superadmin') {
            return true;
        }

        return $this->isSupportRoleId((int) ($user->role_id ?? 0));
    }

    public function displayName(?User $user, string $fallback = ''): string
    {
        if ($this->isSupportUser($user)) {
            return self::DISPLAY_NAME;
        }

        $fullName = trim((string) ($user?->full_name ?: ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $name = trim((string) ($user?->name ?: ''));

        return $name !== '' ? $name : $fallback;
    }

    public function displayRoleLabel(?string $roleName, ?string $roleLabel, ?int $roleId = null): string
    {
        if ($roleName === 'superadmin' || $this->isSupportRoleId($roleId)) {
            return self::DISPLAY_NAME;
        }

        return trim((string) ($roleLabel ?: $roleName ?: ''));
    }

    public function searchMatches(string $q): bool
    {
        $q = trim($q);
        if ($q === '') {
            return true;
        }

        return mb_stripos(self::DISPLAY_NAME, $q) !== false;
    }

    public function appearsInTeamFilter(string $teamFilter): bool
    {
        return $teamFilter === '' || $teamFilter === 'none';
    }

    public function shouldAppearInContacts(int $actorId, string $q, string $teamFilter, array $excludeUserIds): bool
    {
        $canonicalId = $this->canonicalUserId();
        if ($canonicalId === null || $canonicalId === $actorId) {
            return false;
        }

        if (in_array($canonicalId, array_map('intval', $excludeUserIds), true)) {
            return false;
        }

        if (! $this->appearsInTeamFilter($teamFilter)) {
            return false;
        }

        return $this->searchMatches($q);
    }

    /**
     * Подменяет id любого superadmin на канонический, не трогая повторы и нули
     * (их ловят distinct / exists в Form Request).
     *
     * @param  list<mixed>  $ids
     * @return list<mixed>
     */
    public function mapSupportPeerIds(array $ids): array
    {
        $intIds = [];
        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $intIds[] = (int) $id;
            }
        }
        $intIds = array_values(array_unique(array_filter($intIds, fn (int $id) => $id > 0)));
        if ($intIds === []) {
            return $ids;
        }

        $roleId = Role::superadminRoleId();
        if ($roleId === null) {
            return $ids;
        }

        $supportIds = User::query()
            ->whereIn('id', $intIds)
            ->where('role_id', $roleId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($supportIds === []) {
            return $ids;
        }

        $canonicalId = $this->canonicalUserId();
        if ($canonicalId === null) {
            return $ids;
        }

        $supportSet = array_fill_keys($supportIds, true);
        $mapped = [];
        foreach ($ids as $id) {
            $asInt = is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
            $mapped[] = ($asInt !== null && isset($supportSet[$asInt])) ? $canonicalId : $id;
        }

        return $mapped;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function normalizePeerIds(array $ids): array
    {
        $mapped = $this->mapSupportPeerIds(array_map('intval', $ids));

        return array_values(array_unique(array_filter(
            array_map('intval', $mapped),
            fn (int $id) => $id > 0
        )));
    }

    public function normalizePeerId(int $peerId): int
    {
        $normalized = $this->normalizePeerIds([$peerId]);

        return $normalized[0] ?? $peerId;
    }

    public function isAllowedPeerInPartner(User $peer, int $partnerId): bool
    {
        if ($this->isCanonicalUserId((int) $peer->id)) {
            return true;
        }

        if ($this->isSupportUser($peer)) {
            return false;
        }

        return (int) $peer->partner_id === $partnerId;
    }

    public function constrainExcludeSupportRole($query): void
    {
        $roleId = Role::superadminRoleId();
        if ($roleId === null) {
            return;
        }

        $query->where(function ($w) use ($roleId) {
            $w->where('users.role_id', '<>', $roleId)
                ->orWhereNull('users.role_id');
        });
    }

    public function constrainVisibleMembers($query): void
    {
        $roleId = Role::superadminRoleId();
        if ($roleId === null) {
            return;
        }

        $canonicalId = $this->canonicalUserId();
        $query->where(function ($w) use ($roleId, $canonicalId) {
            $w->where('users.role_id', '<>', $roleId)
                ->orWhereNull('users.role_id');
            if ($canonicalId !== null) {
                $w->orWhere('users.id', $canonicalId);
            }
        });
    }
}
