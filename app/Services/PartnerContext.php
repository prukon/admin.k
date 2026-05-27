<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\User;
use App\Support\PartnerScopeMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

//Класс для наследования в контроллерах
class PartnerContext
{
    protected ?Partner $cachedPartner = null;
    protected bool $cachedPartnerInitialized = false;
    protected ?int $cachedKeyUserId = null;
    protected ?int $cachedKeyPartnerId = null;

    /**
     * Текущий пользователь (или null, если гость).
     */
    public function user(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user;
    }

    /**
     * Является ли текущий пользователь супер-админом.
     */
    public function isSuperAdmin(?User $user = null): bool
    {
        $user ??= $this->user();
        return ($user?->role->name ?? null) === 'superadmin';
    }

    /**
     * Текущий Partner (с учётом session('current_partner') для суперюзера).
     */
    public function partner(): ?Partner
    {
        $user = $this->user();

        $keyUserId = $user?->id ? (int) $user->id : null;
        $resolvedPartnerId = null;

        // Супер-админ может переключать партнёра через сессию,
        // остальные пользователи — только в пределах своего partner_id.
        if ($this->isSuperAdmin($user)) {
            $currentPartnerId = session('current_partner');

            if ($currentPartnerId) {
                $resolvedPartnerId = (int) $currentPartnerId;
            }
        } else {
            // иначе — партнёр текущего пользователя
            if ($user?->partner_id) {
                $resolvedPartnerId = (int) $user->partner_id;
            }
        }

        // ВАЖНО: PartnerContext зарегистрирован как singleton.
        // В тестах/консоли один и тот же инстанс может жить дольше одного HTTP-запроса,
        // поэтому кэш должен инвалидироваться при смене пользователя/партнёра.
        if (
            $this->cachedPartnerInitialized
            && $this->cachedKeyUserId === $keyUserId
            && $this->cachedKeyPartnerId === $resolvedPartnerId
        ) {
            return $this->cachedPartner;
        }

        $this->cachedPartnerInitialized = true;
        $this->cachedKeyUserId = $keyUserId;
        $this->cachedKeyPartnerId = $resolvedPartnerId;

        $this->cachedPartner = $resolvedPartnerId ? Partner::find($resolvedPartnerId) : null;
        return $this->cachedPartner;
    }

  

    /**
     * ID партнёра или null.
     */
    public function partnerId(): ?int
    {
        return $this->partner()?->id;
    }

    /**
     * Применить фильтр по партнёру к запросу (если партнёр определён).
     */
    public function scopeByPartner($query, string $column = 'partner_id')
    {
        return $this->applyPartnerScope($query, $column, PartnerScopeMode::STRICT_CURRENT);
    }

    /**
     * Ограничение выборки по partner_id с учётом роли и режима.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     * @param  string|null  $filterPartnerIdRaw  Значение filter_partner_id (all — все партнёры для superadmin).
     */
    public function applyPartnerScope(
        $query,
        string $column = 'partner_id',
        PartnerScopeMode $mode = PartnerScopeMode::STRICT_CURRENT,
        ?string $filterPartnerIdRaw = null,
    ) {
        if ($mode === PartnerScopeMode::SUPERADMIN_ALL_OR_FILTER && $this->isSuperAdmin()) {
            $raw = $filterPartnerIdRaw ?? request()->input('filter_partner_id');
            if ($raw === null || $raw === '' || $raw === 'all') {
                return $query;
            }
            if (ctype_digit((string) $raw)) {
                $partnerId = (int) $raw;
                if ($partnerId > 0) {
                    $query->where($column, $partnerId);
                }
            }

            return $query;
        }

        $partnerId = $this->partnerId();
        if ($partnerId) {
            $query->where($column, $partnerId);
        }

        return $query;
    }
}