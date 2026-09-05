<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Способы оплаты платформы (кошелёк и абонплата CRM), не витрина родителей.
 */
final class PlatformPaymentMethods
{
    public const PERM_TBANK_SBP = 'platformPayments.method.tbankSbp';

    public const PERM_YOOKASSA = 'platformPayments.method.yookassa';

    public const METHOD_TBANK_SBP = 'tinkoff_sbp';

    public const METHOD_YOOKASSA = 'yookassa';

    /**
     * @return list<string>
     */
    public static function allowedMethods(?Authorizable $user): array
    {
        if ($user === null) {
            return [];
        }

        $allowed = [];
        if ($user->can(self::PERM_TBANK_SBP)) {
            $allowed[] = self::METHOD_TBANK_SBP;
        }
        if ($user->can(self::PERM_YOOKASSA)) {
            $allowed[] = self::METHOD_YOOKASSA;
        }

        return $allowed;
    }

    public static function defaultMethod(?Authorizable $user): ?string
    {
        $allowed = self::allowedMethods($user);
        if (in_array(self::METHOD_TBANK_SBP, $allowed, true)) {
            return self::METHOD_TBANK_SBP;
        }

        return $allowed[0] ?? null;
    }

    /**
     * @return array{
     *     canPayTbankSbp: bool,
     *     canPayYookassa: bool,
     *     platformPaymentDefaultMethod: ?string
     * }
     */
    public static function viewState(?Authorizable $user): array
    {
        $allowed = self::allowedMethods($user);

        return [
            'canPayTbankSbp' => in_array(self::METHOD_TBANK_SBP, $allowed, true),
            'canPayYookassa' => in_array(self::METHOD_YOOKASSA, $allowed, true),
            'platformPaymentDefaultMethod' => self::defaultMethod($user),
        ];
    }
}
