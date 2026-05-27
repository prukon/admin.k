<?php

namespace App\Support;

enum PartnerScopeMode
{
    /** Всегда ограничить запрос текущим партнёром из контекста. */
    case STRICT_CURRENT;

    /**
     * Superadmin: все партнёры или один по filter_partner_id (= all|пусто — все).
     * Остальные роли: как STRICT_CURRENT.
     */
    case SUPERADMIN_ALL_OR_FILTER;
}
