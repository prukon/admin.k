<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Validator;

/**
 * Сумма &gt; 0 в установке цен только вместе с абонементом.
 * Историческая строка (цена &gt; 0, пакета нет) при том же payload — no-op.
 */
final class SettingPricesRequirePackageForPositivePrice
{
    public const MESSAGE = 'Нельзя установить цену без абонемента.';

    public static function rejectIfMissingPackage(
        Validator $validator,
        mixed $price,
        bool $packageKeyPresent,
        mixed $payloadPackageId,
        ?int $existingPriceCents,
        ?int $existingPackageId,
        string $errorKey
    ): void {
        if ($validator->errors()->has($errorKey)) {
            return;
        }

        $priceCents = Money::toCents($price);
        if ($priceCents === null || $priceCents <= 0) {
            return;
        }

        $payloadPkg = self::normalizePackageId($payloadPackageId);
        $existingPkg = self::normalizePackageId($existingPackageId);
        $resultingPkg = $packageKeyPresent ? $payloadPkg : $existingPkg;

        if ($resultingPkg !== null) {
            return;
        }

        $existingCents = $existingPriceCents ?? 0;
        if ($existingPkg === null && $existingCents === $priceCents) {
            return;
        }

        $validator->errors()->add($errorKey, self::MESSAGE);
    }

    private static function normalizePackageId(mixed $packageId): ?int
    {
        if ($packageId === null || $packageId === '' || $packageId === false) {
            return null;
        }

        $id = (int) $packageId;

        return $id > 0 ? $id : null;
    }
}
