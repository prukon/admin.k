<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Способы оплаты платформы (кошелёк и абонплата CRM), не витрина родителей.
 *
 * Обычный эквайринг T‑Bank — префикс acquiring (терминал tbank_acquiring).
 * Мультирасчёты — payment.method.tbankCard / payment.method.tbankSBP и терминал tbank.
 */
final class PlatformPaymentMethods
{
    public const PERM_ACQUIRING_SBP = 'platformPayments.method.acquiringSbp';

    public const PERM_ACQUIRING_CARD = 'platformPayments.method.acquiringCard';

    public const PERM_YOOKASSA = 'platformPayments.method.yookassa';

    public const METHOD_ACQUIRING_SBP = 'acquiring_sbp';

    public const METHOD_ACQUIRING_CARD = 'acquiring_card';

    public const METHOD_YOOKASSA = 'yookassa';

    /** Старое значение радио СБП. Принимаем и приводим к acquiring_sbp. */
    public const LEGACY_METHOD_SBP = 'tinkoff_sbp';

    public static function canonicalize(?string $method): ?string
    {
        if ($method === null || $method === '') {
            return $method;
        }

        if ($method === self::LEGACY_METHOD_SBP) {
            return self::METHOD_ACQUIRING_SBP;
        }

        return $method;
    }

    public static function isAcquiringSbp(?string $method): bool
    {
        return self::canonicalize($method) === self::METHOD_ACQUIRING_SBP;
    }

    /**
     * Кошелёк: СБП и карта обычного эквайринга, ЮKassa.
     *
     * @return list<string>
     */
    public static function allowedMethods(?Authorizable $user): array
    {
        if ($user === null) {
            return [];
        }

        $allowed = [];
        if ($user->can(self::PERM_ACQUIRING_SBP)) {
            $allowed[] = self::METHOD_ACQUIRING_SBP;
        }
        if ($user->can(self::PERM_ACQUIRING_CARD)) {
            $allowed[] = self::METHOD_ACQUIRING_CARD;
        }
        if ($user->can(self::PERM_YOOKASSA)) {
            $allowed[] = self::METHOD_YOOKASSA;
        }

        return $allowed;
    }

    /**
     * Абонплата CRM: карта кошелька сюда не входит.
     *
     * @return list<string>
     */
    public static function allowedServiceMethods(?Authorizable $user): array
    {
        return array_values(array_filter(
            self::allowedMethods($user),
            static fn (string $method): bool => $method !== self::METHOD_ACQUIRING_CARD
        ));
    }

    public static function defaultMethod(?Authorizable $user): ?string
    {
        $allowed = self::allowedMethods($user);
        if (in_array(self::METHOD_ACQUIRING_SBP, $allowed, true)) {
            return self::METHOD_ACQUIRING_SBP;
        }
        if (in_array(self::METHOD_ACQUIRING_CARD, $allowed, true)) {
            return self::METHOD_ACQUIRING_CARD;
        }

        return $allowed[0] ?? null;
    }

    public static function defaultServiceMethod(?Authorizable $user): ?string
    {
        $allowed = self::allowedServiceMethods($user);
        if (in_array(self::METHOD_ACQUIRING_SBP, $allowed, true)) {
            return self::METHOD_ACQUIRING_SBP;
        }

        return $allowed[0] ?? null;
    }

    /**
     * @return array{
     *     canPayAcquiringSbp: bool,
     *     canPayAcquiringCard: bool,
     *     canPayYookassa: bool,
     *     canPayTbankSbp: bool,
     *     platformPaymentDefaultMethod: ?string,
     *     platformServiceDefaultMethod: ?string
     * }
     */
    public static function viewState(?Authorizable $user): array
    {
        $allowed = self::allowedMethods($user);

        $canSbp = in_array(self::METHOD_ACQUIRING_SBP, $allowed, true);

        return [
            'canPayAcquiringSbp' => $canSbp,
            'canPayAcquiringCard' => in_array(self::METHOD_ACQUIRING_CARD, $allowed, true),
            'canPayYookassa' => in_array(self::METHOD_YOOKASSA, $allowed, true),
            'canPayTbankSbp' => $canSbp,
            'platformPaymentDefaultMethod' => self::defaultServiceMethod($user),
            'platformServiceDefaultMethod' => self::defaultServiceMethod($user),
        ];
    }
}
