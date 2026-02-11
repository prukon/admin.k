<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

//Класс для наследования в контроллерах
class PartnerContext
{
    protected ?Partner $cachedPartner = null;

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
        if ($this->cachedPartner !== null) {
            return $this->cachedPartner;
        }

        $user = $this->user();

        // если супер-админ переключил партнёра — берём из сессии
        $currentPartnerId = session('current_partner');

        if ($currentPartnerId) {
            $this->cachedPartner = Partner::find($currentPartnerId);
            return $this->cachedPartner;
        }

        // иначе — партнёр текущего пользователя
        if ($user?->partner_id) {
            $this->cachedPartner = Partner::find($user->partner_id);
            return $this->cachedPartner;
        }

        return null;
    }

    public function partner3(): ?Partner
    {
        if ($this->cachedPartner !== null) {
            return $this->cachedPartner;
        }

        $user = $this->user();

        $currentPartnerId = session('current_partner');

        if ($currentPartnerId) {
            $this->cachedPartner = Partner::find($currentPartnerId);
            return $this->cachedPartner;
        }

        if ($user) {
            if ($user->partner_id) {
                $this->cachedPartner = Partner::find($user->partner_id);
                return $this->cachedPartner;
            }

            // вот тут можно логировать/кидать исключение
            \Log::error('User has no partner_id', ['user_id' => $user->id]);
            // throw new \LogicException('User has no partner assigned');
        }

        return null;
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