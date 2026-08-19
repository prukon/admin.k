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

    /**
     * Подпись под именем в шапке личного диалога.
     * Онлайн — окно 120 секунд. 2–5 минут назад — относительная фраза.
     * Дальше — «был(а) в сети в ЧЧ:ММ D месяца YYYY». Никогда не пинговал — пусто.
     */
    public function dialogStatusLabel(?CarbonInterface $lastSeenAt): string
    {
        if ($lastSeenAt === null) {
            return '';
        }

        $now = now();
        if ($lastSeenAt->gte($now->copy()->subSeconds(self::ONLINE_WITHIN_SECONDS))) {
            return 'онлайн';
        }

        $minutes = (int) $lastSeenAt->diffInMinutes($now);
        if ($minutes < 2) {
            $minutes = 2;
        }
        if ($minutes <= 5) {
            return 'был(а) в сети '.$minutes.' '.$this->minutesWord($minutes).' назад';
        }

        return 'был(а) в сети в '.$lastSeenAt
            ->copy()
            ->timezone((string) config('app.timezone'))
            ->locale('ru')
            ->translatedFormat('H:i j F Y');
    }

    private function minutesWord(int $n): string
    {
        $n10 = $n % 10;
        $n100 = $n % 100;
        if ($n10 === 1 && $n100 !== 11) {
            return 'минуту';
        }
        if ($n10 >= 2 && $n10 <= 4 && ($n100 < 12 || $n100 > 14)) {
            return 'минуты';
        }

        return 'минут';
    }
}
