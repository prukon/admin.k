<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
     * Удобно для запросов:
     * $this->scopeByPartner(User::query())->get();
     */
    protected function scopeByPartner($query, string $column = 'partner_id')
    {
        return $this->partnerContext->scopeByPartner($query, $column);
    }
}