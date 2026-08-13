<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\User;
use App\Support\Money;

/**
 * Персональная скидка ученика: целый процент 1–100, вариант А (Money).
 * Текущее правило — на users; снимок на начислении/назначении.
 */
final class UserPercentDiscount
{
    /**
     * @return array{discount_percent: null, discount_comment: null}
     */
    public static function emptySnapshot(): array
    {
        return [
            'discount_percent' => null,
            'discount_comment' => null,
        ];
    }

    public static function percent(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        $raw = $user->discount_percent;
        if ($raw === null || $raw === '') {
            return 0;
        }

        $percent = (int) $raw;
        if ($percent < 1) {
            return 0;
        }
        if ($percent > 100) {
            return 100;
        }

        return $percent;
    }

    public static function comment(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $comment = trim((string) ($user->discount_comment ?? ''));

        return $comment !== '' ? $comment : null;
    }

    public static function hasDiscount(?User $user): bool
    {
        return self::percent($user) >= 1;
    }

    public static function payableCents(int $priceCents, int $percent): int
    {
        if ($percent < 1) {
            return $priceCents;
        }

        return Money::payableAfterDiscountCents($priceCents, $percent);
    }

    public static function payableCentsForUser(int $priceCents, ?User $user): int
    {
        return self::payableCents($priceCents, self::percent($user));
    }

    /**
     * @return array{discount_percent: int|null, discount_comment: string|null}
     */
    public static function snapshotFromUser(?User $user): array
    {
        $percent = self::percent($user);
        if ($percent < 1) {
            return self::emptySnapshot();
        }

        return [
            'discount_percent' => $percent,
            'discount_comment' => self::comment($user),
        ];
    }

    /**
     * Автоскидка, если сумма совпадает с формулой от каталога; иначе override (снимок пустой).
     *
     * @return array{discount_percent: int|null, discount_comment: string|null}
     */
    public static function snapshotIfMatchesCatalog(int $catalogCents, int $submittedCents, ?User $user): array
    {
        $percent = self::percent($user);
        if ($percent < 1) {
            return self::emptySnapshot();
        }

        $expected = self::payableCents($catalogCents, $percent);
        if ($submittedCents !== $expected) {
            return self::emptySnapshot();
        }

        return self::snapshotFromUser($user);
    }

    public static function tooltip(?int $percent, ?string $comment): ?string
    {
        $p = (int) ($percent ?? 0);
        if ($p < 1) {
            return null;
        }

        $text = 'Скидка '.$p.'%.';
        $reason = trim((string) ($comment ?? ''));
        if ($reason !== '') {
            $text .= ' '.$reason;
        }

        return $text;
    }

    public static function snapshotTooltip(?int $percent, ?string $comment): ?string
    {
        return self::tooltip($percent, $comment);
    }
}
