<?php

namespace App\Helpers;

use App\Models\TinkoffPayment;

class TinkoffDetailsHelper
{
    public static function makeDetailsForPeriod(TinkoffPayment $payment): string
    {
        // Возьми нужные данные из связей или payload:
        $period = $payment->payload['last_webhook']['DATA']['PaymentDate'] ?? null; // если есть
        // или из своих сущностей/форм — подставь корректно

        $periodText = $period ? $period : 'текущий период';

        // Шаблон из ТЗ:
        // «Возмещение по договору МР-08.25/УЕА_01 от 21.08.2025 за услуги [период/описание], без НДС.»
        return "Возмещение по договору МР-08.25/УЕА_01 от 21.08.2025 за услуги {$periodText}, без НДС.";
    }
}
