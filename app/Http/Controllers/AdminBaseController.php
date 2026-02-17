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
     * Возвращает ID. Пользовательский флоу "партнёр не выбран" должен обрабатываться middleware `SetPartner`.
     * Если метод вызван вне web-middleware конвейера — это ошибка конфигурации/вызова.
     */
    protected function requirePartnerId(): int
    {
        $partnerId = $this->partnerId();

        if (!$partnerId) {
            throw new \RuntimeException('Текущий партнёр не определён. Ожидается, что middleware SetPartner установит current_partner.');
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
            throw new \RuntimeException('Текущий партнёр не определён. Ожидается, что middleware SetPartner установит current_partner.');
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