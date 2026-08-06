<?php

namespace App\Support;

/**
 * @deprecated Используйте {@see Money::formatRubFixed()}.
 */
class TinkoffMoney
{
    public static function rub(int $cents): string
    {
        return Money::formatRubFixed($cents);
    }
}
