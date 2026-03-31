<?php

namespace App\Services\Tinkoff;

class TinkoffSignature
{
    public static function makeToken2(array $fields, string $secret): string
    {
        // 1) убрать Token
        unset($fields['Token']);
        // 2) добавить Password в общий набор
        $fields['Password'] = $secret;
        // 3) сортировка ключей
        ksort($fields, SORT_STRING);

        // 4) конкат значений с нормализацией типов
        $concat = '';
        foreach ($fields as $k => $v) {
            if (is_array($v) || is_object($v)) {
                // массивы/объекты в подпись не входят
                continue;
            }
            // НОРМАЛИЗАЦИЯ: bool → "true"/"false", числам — "123", null пропускаем
            if (is_bool($v)) {
                $concat .= $v ? 'true' : 'false';
            } elseif (is_null($v)) {
                // пропускаем
                continue;
            } else {
                // приведение к строке без локали
                $concat .= (string)$v;
            }
        }

        return hash('sha256', $concat);
    }
    public static function makeToken(array $fields, string $secret): string
    {
        unset($fields['Token']);
        $fields['Password'] = $secret;
        ksort($fields, SORT_STRING);

        $concat = '';
        foreach ($fields as $k => $v) {
            if (is_array($v) || is_object($v)) continue;
            if (is_bool($v))      $concat .= $v ? 'true' : 'false'; // ВАЖНО: bool → "true"/"false"
            elseif (is_null($v))  continue;
            else                  $concat .= (string)$v;
        }
        return hash('sha256', $concat);
    }


    public static function verify(array $payload, string $secret): bool
    {
        if (!isset($payload['Token'])) return false;
        $expected = self::makeToken($payload, $secret);
        return hash_equals($payload['Token'], $expected);
    }


}
