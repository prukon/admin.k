<?php

namespace App\Support;

use App\Models\PartnerLegalEntity;

final class PartnerLegalEntityMode
{
    public static function activeCount(int $partnerId): int
    {
        return PartnerLegalEntity::query()
            ->where('partner_id', $partnerId)
            ->active()
            ->count();
    }

    public static function isMultiEntity(int $partnerId): bool
    {
        return self::activeCount($partnerId) >= 2;
    }
}
