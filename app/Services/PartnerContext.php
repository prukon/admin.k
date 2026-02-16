<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\User;
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
        $partnerId = $this->partnerId();

        if ($partnerId) {
            $query->where($column, $partnerId);
        }

        return $query;
    }
}