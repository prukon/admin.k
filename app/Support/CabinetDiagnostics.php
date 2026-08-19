<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Флаг оверлея статуса Reverb: кнопка и бейдж через can(settings.reverbOverlay.manage).
 * Право невидимое и никому не выдаётся; superadmin проходит Gate::before.
 */
final class CabinetDiagnostics
{
    public const SETTING = 'cabinet_diagnostics';

    public const PERMISSION = 'settings.reverbOverlay.manage';

    public static function actorCanManage(?User $user): bool
    {
        return $user !== null && $user->can(self::PERMISSION);
    }

    public static function isEnabled(): bool
    {
        return Setting::getBool(self::SETTING, false, null);
    }

    public static function shouldShow(?User $user): bool
    {
        return self::isEnabled() && self::actorCanManage($user);
    }
}
