<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\User;
use Carbon\CarbonInterface;

class UserPresence
{
    public const ONLINE_WITHIN_SECONDS = 120;

    public const TOUCH_THROTTLE_SECONDS = 30;

    public function touch(User $user): void
    {
        $now = now();
        if ($user->last_seen_at instanceof CarbonInterface
            && $user->last_seen_at->gt($now->copy()->subSeconds(self::TOUCH_THROTTLE_SECONDS))) {
            return;
        }

        User::query()->whereKey($user->id)->update(['last_seen_at' => $now]);
        $user->last_seen_at = $now;
    }

    public function isOnline(?User $user): bool
    {
        return $user !== null && $user->isOnline();
    }
}
