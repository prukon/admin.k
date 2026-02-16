<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use App\Services\PartnerContext;

abstract class AdminBaseController extends Controller
{
    protected PartnerContext $partnerContext;

    public function __construct(PartnerContext $partnerContext)
    {
        $this->partnerContext = $partnerContext;
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = $this->partnerContext->user();
        return $user;
    }

    protected function isSuperAdmin(?User $user = null): bool
    {
        return $this->partnerContext->isSuperAdmin($user);
    }

    protected function partnerId(): ?int
    {
        return $this->partnerContext->partnerId();
    }

    /**
     * Обязательное наличие текущего партнёра для partner-scoped страниц.
     * Возвращает ID или прерывает запрос 400 (если партнёр не определён).
     */
    protected function requirePartnerId(): int
    {
        $partnerId = $this->partnerId();

        if (!$partnerId) {
            abort(400, 'Текущий партнёр не определён');
        }

        return (int) $partnerId;
    }

    /**
     * Обязательное наличие текущего партнёра (объект Partner).
     */
    protected function requirePartner(): Partner
    {
        $partner = $this->partnerContext->partner();

        if (!$partner) {
            abort(400, 'Текущий партнёр не определён');
        }

        return $partner;
    }

    /**
     * Удобно для запросов:
     * $this->scopeByPartner(User::query())->get();
     */
    protected function scopeByPartner($query, string $column = 'partner_id')
    {
        return $this->partnerContext->scopeByPartner($query, $column);
    }
}