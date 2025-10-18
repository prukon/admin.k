<?php
namespace App\Support;
class TinkoffMoney {
    public static function rub(int $cents): string {
        return number_format($cents/100, 2, ',', ' ');
    }
}
